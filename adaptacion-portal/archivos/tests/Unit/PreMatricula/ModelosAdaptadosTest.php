<?php

namespace Tests\Unit\PreMatricula;

use App\Modules\PreMatricula\Models\CambioCargaDocente;
use App\Modules\PreMatricula\Models\CambioHistorial;
use App\Modules\PreMatricula\Models\CargaDocente;
use App\Modules\PreMatricula\Models\HistorialAcademico;
use App\Modules\PreMatricula\Models\ImportacionHistorial;
use App\Modules\PreMatricula\Models\LimiteCreditosEstudiante;
use App\Modules\PreMatricula\Models\RevisionCurso;
use App\Modules\PreMatricula\Models\RevisionSolicitud;
use App\Modules\PreMatricula\Models\ValidacionSolicitud;
use Illuminate\Database\Eloquent\SoftDeletes;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ModelosAdaptadosTest extends TestCase
{
    #[DataProvider('modelos')]
    public function test_los_modelos_nuevos_usan_tabla_calificada_y_clave_primaria(
        string $clase,
        string $tabla,
        string $clave,
    ): void {
        $modelo = new $clase;

        $this->assertSame($tabla, $modelo->getTable());
        $this->assertSame($clave, $modelo->getKeyName());
        $this->assertStringStartsWith('prematricula.', $modelo->getTable());
    }

    /** @return array<string, array{class-string, string, string}> */
    public static function modelos(): array
    {
        return [
            'revision' => [RevisionSolicitud::class, 'prematricula.revision_solicitud', 'id_revision'],
            'revision_curso' => [RevisionCurso::class, 'prematricula.revision_curso', 'id_revision'],
            'validacion' => [ValidacionSolicitud::class, 'prematricula.validacion_solicitud', 'id_validacion'],
            'limite' => [LimiteCreditosEstudiante::class, 'prematricula.limite_creditos_estudiante', 'id_limite'],
            'historial' => [HistorialAcademico::class, 'prematricula.historial_academico', 'id_historial'],
            'importacion' => [ImportacionHistorial::class, 'prematricula.importacion_historial', 'id_importacion'],
            'cambio_historial' => [CambioHistorial::class, 'prematricula.cambio_historial', 'id_cambio'],
            'carga' => [CargaDocente::class, 'prematricula.carga_docente', 'id_carga'],
            'cambio_carga' => [CambioCargaDocente::class, 'prematricula.cambio_carga_docente', 'id_cambio'],
        ];
    }

    public function test_la_carga_docente_usa_baja_logica_con_columnas_en_espanol(): void
    {
        $carga = new CargaDocente;

        $this->assertContains(SoftDeletes::class, class_uses($carga));
        $this->assertSame('eliminado_en', $carga->getDeletedAtColumn());
        $this->assertSame('creado_en', $carga->getCreatedAtColumn());
        $this->assertSame('actualizado_en', $carga->getUpdatedAtColumn());
    }

    public function test_la_revision_no_expone_el_pdf_al_serializar(): void
    {
        $this->assertContains('pdf_base64', (new RevisionSolicitud)->getHidden());
    }

    public function test_la_clave_de_revision_curso_no_es_autoincremental(): void
    {
        $this->assertFalse((new RevisionCurso)->getIncrementing());
    }
}
