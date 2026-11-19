<?php

namespace Tests\Feature;

use App\Modules\PreMatricula\Models\CronogramaPrematricula;
use App\Modules\PreMatricula\Models\DetalleSolicitud;
use App\Modules\PreMatricula\Models\SolicitudPrematricula;
use App\Modules\PreMatricula\Services\PreMatriculaService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\CreatesPrematriculaAcademicData;
use Tests\TestCase;

final class PrematriculaDatabaseTest extends TestCase
{
    use CreatesPrematriculaAcademicData;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Requiere PostgreSQL para probar esquemas y restricciones.');
        }

        if (! DB::table('information_schema.tables')
            ->where('table_schema', 'prematricula')
            ->where('table_name', 'solicitud_prematricula')
            ->exists()) {
            $this->markTestSkipped('Las migraciones de prematrícula aún no se ejecutaron.');
        }

        $this->createPrematriculaAcademicData();
    }

    public function test_module_contains_the_three_expected_tables(): void
    {
        $tables = DB::table('information_schema.tables')
            ->where('table_schema', 'prematricula')
            ->where('table_type', 'BASE TABLE')
            ->orderBy('table_name')
            ->pluck('table_name')
            ->all();

        $this->assertSame([
            'cronograma_prematricula',
            'detalle_solicitud',
            'solicitud_prematricula',
        ], $tables);
    }

    public function test_service_creates_a_request_and_its_details_atomically(): void
    {
        $solicitud = app(PreMatriculaService::class)->crearSolicitud(
            $this->solicitudData(),
            [
                $this->detalleData(501),
                $this->detalleData(502),
            ],
        );

        $this->assertCount(2, $solicitud->detalles);
        $this->assertSame([501, 502], $solicitud->detalles->pluck('id_curso_plan')->all());
        $this->assertTrue($solicitud->enviada_en->equalTo('2026-10-28 15:00:00'));
        $this->assertTrue($solicitud->detalles->first()->solicitud->is($solicitud));
        $this->assertSame('E2026001', $solicitud->estudiante->codigo_universitario);
        $this->assertSame('2026-II', $solicitud->periodo->codigo);
        $this->assertSame('CP-501', $solicitud->detalles->first()->cursoPlan->curso->codigo);
    }

    public function test_all_seven_module_foreign_keys_are_installed(): void
    {
        $foreignKeys = collect(DB::select(<<<'SQL'
            SELECT c.conname
            FROM pg_constraint c
            JOIN pg_class t ON t.oid = c.conrelid
            JOIN pg_namespace n ON n.oid = t.relnamespace
            WHERE c.contype = 'f' AND n.nspname = 'prematricula'
            ORDER BY c.conname
            SQL))
            ->pluck('conname')
            ->all();

        $this->assertSame([
            'pre_cr_per_fk',
            'pre_de_cur_fk',
            'pre_de_sec_fk',
            'pre_de_sol_fk',
            'pre_so_est_fk',
            'pre_so_per_fk',
            'pre_so_rev_fk',
        ], $foreignKeys);
    }

    #[DataProvider('invalidExternalReferences')]
    public function test_external_foreign_keys_reject_unknown_references(
        string $entity,
        string $column,
    ): void {
        $this->expectException(QueryException::class);

        if ($entity === 'cronograma') {
            CronogramaPrematricula::query()->create([
                'id_periodo' => 999999999,
                'fecha_inicio' => '2026-11-01 08:00:00-05',
                'fecha_fin' => '2026-11-02 08:00:00-05',
                'estado' => 'programado',
            ]);

            return;
        }

        if ($entity === 'solicitud') {
            SolicitudPrematricula::query()->create($this->solicitudData([
                $column => 999999999,
            ]));

            return;
        }

        $solicitud = SolicitudPrematricula::query()->create($this->solicitudData());
        $solicitud->detalles()->create([
            ...$this->detalleData(501),
            $column => 999999999,
        ]);
    }

    /** @return array<string, array{string, string}> */
    public static function invalidExternalReferences(): array
    {
        return [
            'periodo de cronograma' => ['cronograma', 'id_periodo'],
            'estudiante de solicitud' => ['solicitud', 'id_estudiante'],
            'periodo de solicitud' => ['solicitud', 'id_periodo'],
            'revisor de solicitud' => ['solicitud', 'revisado_por'],
            'curso del detalle' => ['detalle', 'id_curso_plan'],
            'seccion preferida' => ['detalle', 'id_seccion_preferida'],
        ];
    }

    public function test_student_cannot_repeat_a_request_in_the_same_period(): void
    {
        SolicitudPrematricula::query()->create($this->solicitudData());

        $this->expectException(QueryException::class);

        SolicitudPrematricula::query()->create($this->solicitudData([
            'estado' => 'reenviada',
        ]));
    }

    public function test_a_course_cannot_be_repeated_in_the_same_request(): void
    {
        $solicitud = SolicitudPrematricula::query()->create($this->solicitudData());
        $solicitud->detalles()->create($this->detalleData(501));

        $this->expectException(QueryException::class);

        $solicitud->detalles()->create($this->detalleData(501));
    }

    public function test_schedule_end_must_be_after_its_start(): void
    {
        $this->expectException(QueryException::class);

        CronogramaPrematricula::query()->create([
            'id_periodo' => 202602,
            'fecha_inicio' => '2026-11-01 08:00:00-05',
            'fecha_fin' => '2026-11-01 07:59:59-05',
            'estado' => 'programado',
        ]);
    }

    public function test_detail_rejects_a_nonexistent_request(): void
    {
        $this->expectException(QueryException::class);

        DetalleSolicitud::query()->create([
            'id_solicitud' => 999999999,
            ...$this->detalleData(501),
        ]);
    }

    public function test_failed_detail_creation_rolls_back_the_request_header(): void
    {
        $service = app(PreMatriculaService::class);

        try {
            $service->crearSolicitud(
                $this->solicitudData(),
                [
                    $this->detalleData(501),
                    $this->detalleData(501),
                ],
            );

            $this->fail('La solicitud duplicada debió fallar.');
        } catch (QueryException) {
            $this->assertDatabaseMissing('prematricula.solicitud_prematricula', [
                'id_estudiante' => 1001,
                'id_periodo' => 202602,
            ]);
        }
    }

    /** @param array<string, mixed> $overrides */
    private function solicitudData(array $overrides = []): array
    {
        return [
            'id_estudiante' => 1001,
            'id_periodo' => 202602,
            'estado' => 'enviada',
            'observacion' => 'Solicitud de prueba funcional.',
            'enviada_en' => '2026-10-28 15:00:00-05',
            'revisado_por' => null,
            'revisado_en' => null,
            ...$overrides,
        ];
    }

    /** @return array<string, mixed> */
    private function detalleData(int $cursoPlan): array
    {
        return [
            'id_curso_plan' => $cursoPlan,
            'id_seccion_preferida' => null,
            'estado' => 'solicitado',
            'motivo_rechazo' => null,
        ];
    }
}
