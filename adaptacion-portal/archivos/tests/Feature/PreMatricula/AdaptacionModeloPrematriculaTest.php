<?php

namespace Tests\Feature\PreMatricula;

use App\Modules\PreMatricula\Models\CambioHistorial;
use App\Modules\PreMatricula\Models\CargaDocente;
use App\Modules\PreMatricula\Models\CronogramaPrematricula;
use App\Modules\PreMatricula\Models\DetalleSolicitud;
use App\Modules\PreMatricula\Models\HistorialAcademico;
use App\Modules\PreMatricula\Models\ImportacionHistorial;
use App\Modules\PreMatricula\Models\LimiteCreditosEstudiante;
use App\Modules\PreMatricula\Models\RevisionSolicitud;
use App\Modules\PreMatricula\Models\SolicitudPrematricula;
use App\Modules\PreMatricula\Models\ValidacionSolicitud;
use App\Modules\PreMatricula\Services\PreMatriculaService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Support\CreatesPrematriculaAcademicData;
use Tests\TestCase;

/**
 * Verifica que el modelo del proyecto original quedó adaptado al esquema prematricula del portal.
 */
final class AdaptacionModeloPrematriculaTest extends TestCase
{
    use CreatesPrematriculaAcademicData;
    use DatabaseTransactions;

    private int $revisionesCreadas = 0;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Requiere PostgreSQL para probar esquemas y restricciones.');
        }

        if (! DB::table('information_schema.tables')
            ->where('table_schema', 'prematricula')
            ->where('table_name', 'revision_solicitud')
            ->exists()) {
            $this->markTestSkipped('Las migraciones de adaptación aún no se ejecutaron.');
        }

        $this->createPrematriculaAcademicData();
    }

    // ---- Ventanas (cronograma) ----

    public function test_un_periodo_admite_una_ventana_ordinaria_y_varias_extraordinarias(): void
    {
        $this->crearVentana('ORDINARIA', '2026-07-01 08:00', '2026-07-10 23:59');
        $this->crearVentana('EXTRAORDINARIA', '2026-07-15 08:00', '2026-07-17 23:59');
        $this->crearVentana('EXTRAORDINARIA', '2026-07-20 08:00', '2026-07-21 23:59');

        $this->assertSame(3, CronogramaPrematricula::query()->where('id_periodo', 202602)->count());
    }

    public function test_un_periodo_no_admite_dos_ventanas_ordinarias(): void
    {
        $this->crearVentana('ORDINARIA', '2026-07-01 08:00', '2026-07-10 23:59');

        $this->expectException(QueryException::class);
        $this->crearVentana('ORDINARIA', '2026-07-11 08:00', '2026-07-12 23:59');
    }

    public function test_el_tipo_de_ventana_debe_ser_valido(): void
    {
        $this->expectException(QueryException::class);
        $this->crearVentana('ESPECIAL', '2026-07-01 08:00', '2026-07-10 23:59');
    }

    public function test_la_ventana_es_activa_con_inicio_inclusivo_y_fin_exclusivo(): void
    {
        $ventana = $this->crearVentana('ORDINARIA', '2026-07-01 08:00:00', '2026-07-10 08:00:00');

        $this->assertTrue($ventana->estaActiva(now()->parse('2026-07-01 08:00:00')));
        $this->assertFalse($ventana->estaActiva(now()->parse('2026-07-10 08:00:00')));
    }

    // ---- Detalle ----

    public function test_el_detalle_tiene_preferencia_de_turno_por_defecto_y_validada(): void
    {
        $solicitud = $this->crearSolicitud();
        $detalle = DetalleSolicitud::query()->create([
            'id_solicitud' => $solicitud->id_solicitud,
            'id_curso_plan' => 501,
            'estado' => 'SELECCIONADO',
        ]);

        $this->assertSame('SIN_PREFERENCIA', $detalle->fresh()->preferencia_turno);

        $this->expectException(QueryException::class);
        DetalleSolicitud::query()->create([
            'id_solicitud' => $solicitud->id_solicitud,
            'id_curso_plan' => 502,
            'estado' => 'SELECCIONADO',
            'preferencia_turno' => 'NOCHE',
        ]);
    }

    // ---- Revisiones ----

    public function test_un_envio_sin_constancia_completa_es_rechazado(): void
    {
        $solicitud = $this->crearSolicitud();

        $this->expectException(QueryException::class);
        RevisionSolicitud::query()->create([
            'id_solicitud' => $solicitud->id_solicitud,
            'motivo' => 'ENVIO',
            'numero_revision' => 1,
            'snapshot' => ['detalles' => []],
        ]);
    }

    public function test_las_revisiones_enviadas_son_inmutables(): void
    {
        $revision = $this->registrarEnvio($this->crearSolicitud(), [501 => 'MANANA']);

        try {
            DB::table('prematricula.revision_solicitud')
                ->where('id_revision', $revision->id_revision)
                ->update(['motivo' => 'LEGACY_CAPTURE']);
            $this->fail('Se permitió modificar una revisión.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('inmutables', $exception->getMessage());
        }
    }

    public function test_la_revision_vigente_debe_pertenecer_a_la_misma_solicitud(): void
    {
        $primera = $this->crearSolicitud();
        $revisionAjena = $this->registrarEnvio($primera, [501 => 'TARDE']);
        $otra = $this->crearSolicitud($this->crearEstudiante(1002, '70000004', 'E2026002'));

        $this->expectException(QueryException::class);
        $otra->update(['id_revision_vigente' => $revisionAjena->id_revision]);
    }

    public function test_la_solicitud_expone_su_revision_vigente_y_cursos(): void
    {
        $solicitud = $this->crearSolicitud();
        $this->registrarEnvio($solicitud, [501 => 'MANANA']);
        $segunda = $this->registrarEnvio($solicitud, [501 => 'TARDE', 502 => 'SIN_PREFERENCIA']);

        $solicitud->refresh();

        $this->assertSame(2, $solicitud->revisiones()->count());
        $this->assertTrue($solicitud->revisionVigente->is($segunda));
        $this->assertSame(2, $solicitud->revisionVigente->numero_revision);
        $this->assertSame([501, 502], $segunda->cursosPlan->pluck('id_curso_plan')->sort()->values()->all());
        $this->assertArrayNotHasKey('pdf_base64', $segunda->toArray());
    }

    // ---- Validación ----

    public function test_la_validacion_es_unica_por_solicitud_y_con_estados_validos(): void
    {
        $solicitud = $this->crearSolicitud();
        $validacion = ValidacionSolicitud::query()->create([
            'id_solicitud' => $solicitud->id_solicitud,
            'estado' => ValidacionSolicitud::VALIDA,
            'cursos' => [['id_curso_plan' => 501, 'estado' => 'VALIDO']],
            'evaluada_en' => now(),
        ]);

        $this->assertTrue($solicitud->validacion->is($validacion));
        $this->assertSame(501, $validacion->fresh()->cursos[0]['id_curso_plan']);

        $this->expectException(QueryException::class);
        ValidacionSolicitud::query()->create([
            'id_solicitud' => $solicitud->id_solicitud,
            'estado' => 'APROBADA',
            'cursos' => [],
            'evaluada_en' => now(),
        ]);
    }

    // ---- Créditos ----

    public function test_el_limite_de_creditos_usa_22_si_no_hay_registro(): void
    {
        $this->assertSame(22, LimiteCreditosEstudiante::creditosPara(1001));

        LimiteCreditosEstudiante::query()->create(['id_estudiante' => 1001, 'creditos_maximos' => 18]);

        $this->assertSame(18, LimiteCreditosEstudiante::creditosPara(1001));
    }

    // ---- Historial académico e importaciones ----

    public function test_el_historial_es_unico_y_valida_estado_y_nota(): void
    {
        $historial = HistorialAcademico::query()->create($this->historialData());

        $this->assertSame('CP-501', $historial->cursoPlan->curso->codigo);
        $this->assertSame('E2026001', $historial->estudiante->codigo_universitario);

        $this->expectException(QueryException::class);
        HistorialAcademico::query()->create($this->historialData());
    }

    public function test_el_historial_rechaza_una_nota_fuera_de_rango(): void
    {
        $this->expectException(QueryException::class);
        HistorialAcademico::query()->create(['nota_final' => 25] + $this->historialData());
    }

    public function test_una_importacion_registra_el_antes_y_despues_de_cada_cambio(): void
    {
        $historial = HistorialAcademico::query()->create($this->historialData());
        $importacion = ImportacionHistorial::query()->create([
            'creado_por' => 801,
            'sha256' => str_repeat('a', 64),
            'motivo' => 'Carga de actas 2026-II',
            'resumen' => ['filas' => 1, 'cambios' => 1],
        ]);
        CambioHistorial::query()->create([
            'id_importacion' => $importacion->id_importacion,
            'id_historial' => $historial->id_historial,
            'antes' => ['estado' => 'EN_CURSO'],
            'despues' => ['estado' => 'APROBADO', 'nota_final' => 15],
        ]);

        $cambio = $importacion->cambios()->first();
        $this->assertSame('EN_CURSO', $cambio->antes['estado']);
        $this->assertTrue($cambio->historial->is($historial));
        $this->assertSame('revisor@example.test', $importacion->autor->correo_institucional);
    }

    // ---- Carga docente ----

    public function test_la_carga_docente_exige_horas_positivas(): void
    {
        $this->crearDocente();

        $this->expectException(QueryException::class);
        CargaDocente::query()->create($this->cargaData(['horas_teoria' => 0]));
    }

    public function test_la_baja_logica_libera_la_combinacion_periodo_curso_docente(): void
    {
        $this->crearDocente();
        $carga = CargaDocente::query()->create($this->cargaData());

        try {
            // Savepoint: el error de PostgreSQL no invalida la transacción de la prueba.
            DB::transaction(fn () => CargaDocente::query()->create($this->cargaData()));
            $this->fail('Se permitió duplicar una carga vigente.');
        } catch (QueryException) {
            // Esperado: solo una carga vigente por período/curso/docente.
        }

        $carga->delete();

        $nueva = CargaDocente::query()->create($this->cargaData(['horas_practica' => 1.5]));

        $this->assertNotNull($carga->fresh()->eliminado_en);
        $this->assertSame(1, CargaDocente::query()->count());
        $this->assertSame(2, CargaDocente::withTrashed()->count());
        $this->assertSame(3.5, $nueva->totalHoras());
    }

    // ---- Demanda por curso (insumo del dashboard) ----

    public function test_la_demanda_solo_cuenta_la_revision_vigente_de_cada_envio(): void
    {
        // Estudiante A: envía 501 (mañana) y 502 (tarde).
        $a = $this->crearSolicitud();
        $this->registrarEnvio($a, [501 => 'MANANA', 502 => 'TARDE']);

        // Estudiante B: primero envía 501 y luego corrige a solo 502.
        $b = $this->crearSolicitud($this->crearEstudiante(1002, '70000004', 'E2026002'));
        $this->registrarEnvio($b, [501 => 'MANANA']);
        $this->registrarEnvio($b, [502 => 'SIN_PREFERENCIA']);

        // Estudiante C: solo borrador, no cuenta.
        $c = $this->crearSolicitud($this->crearEstudiante(1003, '70000005', 'E2026003'));
        $c->detalles()->create(['id_curso_plan' => 501, 'estado' => 'SELECCIONADO']);

        $demanda = app(PreMatriculaService::class)->demandaPorCurso(202602)->keyBy('codigo');

        $this->assertSame(2, $demanda['CP-502']->estudiantes);
        $this->assertSame(1, $demanda['CP-502']->tarde);
        $this->assertSame(1, $demanda['CP-502']->sin_preferencia);
        $this->assertSame(1, $demanda['CP-501']->estudiantes);
        $this->assertSame(1, $demanda['CP-501']->manana);
        $this->assertSame(['CP-502', 'CP-501'], $demanda->keys()->all());
    }

    // ---- Ayudantes ----

    private function crearVentana(string $tipo, string $inicio, string $fin): CronogramaPrematricula
    {
        return CronogramaPrematricula::query()->create([
            'id_periodo' => 202602,
            'tipo' => $tipo,
            'fecha_inicio' => $inicio,
            'fecha_fin' => $fin,
            'estado' => 'PROGRAMADA',
            'motivo' => 'Prueba',
            'creado_por' => 801,
        ]);
    }

    private function crearSolicitud(int $idEstudiante = 1001): SolicitudPrematricula
    {
        return SolicitudPrematricula::query()->create([
            'id_estudiante' => $idEstudiante,
            'id_periodo' => 202602,
            'estado' => SolicitudPrematricula::ESTADO_BORRADOR,
        ]);
    }

    /** @param  array<int, string>  $cursos  id_curso_plan => preferencia */
    private function registrarEnvio(SolicitudPrematricula $solicitud, array $cursos): RevisionSolicitud
    {
        $numero = $solicitud->revisiones()->count() + 1;
        $this->revisionesCreadas++;

        $revision = RevisionSolicitud::query()->create([
            'id_solicitud' => $solicitud->id_solicitud,
            'motivo' => RevisionSolicitud::MOTIVO_ENVIO,
            'numero_revision' => $numero,
            'codigo_constancia' => sprintf('PRE-2026II-%05d', $this->revisionesCreadas),
            'snapshot' => ['detalles' => array_keys($cursos)],
            'pdf_base64' => base64_encode('%PDF-1.4 prueba'),
            'pdf_sha256' => hash('sha256', 'pdf'.$this->revisionesCreadas),
            'enviada_en' => now(),
        ]);

        foreach ($cursos as $idCursoPlan => $turno) {
            $revision->cursos()->create(['id_curso_plan' => $idCursoPlan, 'preferencia_turno' => $turno]);
        }

        $solicitud->update([
            'estado' => SolicitudPrematricula::ESTADO_ENVIADA,
            'id_revision_vigente' => $revision->id_revision,
            'enviada_en' => now(),
        ]);

        return $revision;
    }

    private function crearEstudiante(int $id, string $dni, string $codigo): int
    {
        DB::table('core.persona')->insert([
            'id_persona' => $id + 2000,
            'tipo_documento' => 'DNI',
            'numero_documento' => $dni,
            'nombres' => 'Estudiante '.$codigo,
            'apellido_paterno' => 'Prueba',
        ]);
        DB::table('core.estudiante')->insert([
            'id_estudiante' => $id,
            'id_persona' => $id + 2000,
            'codigo_universitario' => $codigo,
            'id_escuela' => 20,
            'id_plan' => 50,
            'id_periodo_ingreso' => 202602,
            'id_modalidad_ingreso' => 40,
            'estado' => 'Activo',
        ]);

        return $id;
    }

    private function crearDocente(): void
    {
        DB::table('core.persona')->insert([
            'id_persona' => 1003,
            'tipo_documento' => 'DNI',
            'numero_documento' => '70000003',
            'nombres' => 'Docente',
            'apellido_paterno' => 'Prueba',
        ]);
        DB::table('core.docente')->insert([
            'id_docente' => 3001,
            'id_persona' => 1003,
            'codigo_docente' => 'D-001',
            'id_departamento' => 30,
            'horas_maximas' => 20,
            'estado' => 'Activo',
        ]);
    }

    /** @return array<string, mixed> */
    private function cargaData(array $cambios = []): array
    {
        return $cambios + [
            'id_periodo' => 202602,
            'id_curso_plan' => 501,
            'id_docente' => 3001,
            'horas_teoria' => 2,
            'horas_practica' => 0,
            'horas_laboratorio' => 0,
            'creado_por' => 801,
            'actualizado_por' => 801,
        ];
    }

    /** @return array<string, mixed> */
    private function historialData(): array
    {
        return [
            'id_estudiante' => 1001,
            'id_curso_plan' => 501,
            'id_periodo' => 202602,
            'nota_final' => 14,
            'estado' => HistorialAcademico::APROBADO,
        ];
    }
}
