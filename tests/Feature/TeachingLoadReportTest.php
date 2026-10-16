<?php

namespace Tests\Feature;

use App\Models\AcademicPeriod;
use App\Models\Course;
use App\Models\PreEnrollmentRequest;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\User;
use Database\Factories\PreEnrollmentRevisionFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class TeachingLoadReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_period_teacher_and_course_reports_sum_independent_hours_and_pending_capacity(): void
    {
        $period = AcademicPeriod::factory()->create();
        $first = Course::factory()->create(['codigo' => 'C01', 'horas_teoria' => 4, 'horas_practica' => 2, 'horas_laboratorio' => 1]);
        $second = Course::factory()->create(['codigo' => 'C02', 'horas_teoria' => 1, 'horas_practica' => 1, 'horas_laboratorio' => 1]);
        $teacher = Teacher::factory()->create(['max_horas_semanales' => 10]);
        TeachingAssignment::factory()->for($period)->for($teacher)->for($first, 'course')->create([
            'horas_teoria_asignadas' => '1.50', 'horas_practica_asignadas' => '0.50', 'horas_laboratorio_asignadas' => '0.25']);
        TeachingAssignment::factory()->for($period)->for($teacher)->for($second, 'course')->create([
            'horas_teoria_asignadas' => '1.00', 'horas_practica_asignadas' => '0.50', 'horas_laboratorio_asignadas' => '0.75']);
        TeachingAssignment::factory()->for($period)->for($first, 'course')->create([
            'horas_teoria_asignadas' => '2.00', 'horas_practica_asignadas' => '0.50', 'horas_laboratorio_asignadas' => '0.50']);
        TeachingAssignment::factory()->for($teacher)->for($first, 'course')->create(['horas_teoria_asignadas' => '2.00']);
        Sanctum::actingAs(User::factory()->coordinator()->create());
        $root = $this->root($period);

        $this->getJson($root.'/teachers/'.$teacher->id)->assertOk()->assertJsonPath('data.carga.cantidad_cursos', 2)
            ->assertJsonPath('data.carga.horas_teoria_asignadas', '2.50')->assertJsonPath('data.carga.horas_practica_asignadas', '1.00')
            ->assertJsonPath('data.carga.horas_laboratorio_asignadas', '1.00')->assertJsonPath('data.carga.total_semanal', '4.50')
            ->assertJsonPath('data.carga.horas_disponibles', '5.50')->assertJsonPath('data.carga.excede_limite', false)
            ->assertJsonPath('data.paginacion.total', 2)->assertJsonCount(2, 'data.asignaciones');
        $this->getJson($root.'/courses/'.$first->id)->assertOk()->assertJsonPath('data.carga.cantidad_docentes', 2)
            ->assertJsonPath('data.carga.horas_teoria_asignadas', '3.50')->assertJsonPath('data.carga.horas_teoria_pendientes', '0.50')
            ->assertJsonPath('data.carga.horas_practica_pendientes', '1.00')->assertJsonPath('data.carga.horas_laboratorio_pendientes', '0.25')
            ->assertJsonPath('data.carga.total_semanal', '5.25')->assertJsonCount(2, 'data.asignaciones');
        $this->getJson($root)->assertOk()->assertJsonPath('data.totales.asignaciones', 3)
            ->assertJsonPath('data.totales.docentes_con_carga', 2)->assertJsonPath('data.totales.cursos_con_asignacion', 2)
            ->assertJsonPath('data.totales.total_semanal', '7.50')->assertJsonPath('data.totales.horas_practica_pendientes', '1.50')
            ->assertJsonPath('data.totales.docentes_exceden_limite', 0);
    }

    public function test_demand_comparison_uses_current_registered_revisions_without_multiplying_teaching_hours(): void
    {
        $period = AcademicPeriod::factory()->create();
        $course = Course::factory()->create(['codigo' => 'C01']);
        $unassigned = Course::factory()->create(['codigo' => 'C02']);
        TeachingAssignment::factory()->for($period)->for($course)->create(['horas_teoria_asignadas' => 1]);
        $this->registerDemand($period, $course, 'VALIDA');
        $this->registerDemand($period, $course, 'REQUIERE_AJUSTE');
        $this->registerDemand($period, $unassigned, 'PENDIENTE_RESULTADOS');
        $draft = PreEnrollmentRequest::factory()->for($period)->create();
        $draft->details()->create(['course_id' => $course->id]);
        Sanctum::actingAs(User::factory()->coordinator()->create());

        $this->getJson($this->root($period).'/courses')->assertOk()->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.demanda_registrada', 2)->assertJsonPath('data.0.demanda_valida', 1)
            ->assertJsonPath('data.0.demanda_requiere_ajuste', 1)->assertJsonPath('data.0.total_semanal', '1.00')
            ->assertJsonPath('data.1.demanda_registrada', 1)->assertJsonPath('data.1.cantidad_docentes', 0)
            ->assertJsonMissingPath('data.0.grupos')->assertJsonMissingPath('data.0.secciones')
            ->assertJsonPath('contexto.advertencia_demanda', 'Estimaciones de demanda de prematrícula; no constituyen matrícula definitiva.');
    }

    public function test_reports_detect_existing_excesses_and_support_filters_and_pagination(): void
    {
        $period = AcademicPeriod::factory()->closed()->create();
        $overloaded = Teacher::factory()->inactive()->create(['codigo_institucional' => 'DOC01', 'max_horas_semanales' => 1]);
        TeachingAssignment::factory()->for($period)->for($overloaded, 'teacher')->create(['horas_teoria_asignadas' => 2]);
        Teacher::factory()->create(['codigo_institucional' => 'DOC02']);
        Course::factory()->create(['codigo' => 'OTHER', 'ciclo' => 4]);
        Sanctum::actingAs(User::factory()->coordinator()->create());
        $root = $this->root($period);

        $this->getJson($root.'/teachers?excede_limite=1&estado=INACTIVO&per_page=1')->assertOk()
            ->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.docente.id', $overloaded->id)
            ->assertJsonPath('data.0.excede_limite', true)->assertJsonPath('data.0.horas_disponibles', '0.00');
        $this->getJson($root.'/teachers?excede_limite=0')->assertOk()->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.total_semanal', '0.00')->assertJsonPath('data.0.horas_disponibles', null);
        $this->getJson($root.'/teachers?q=DOC01')->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson($root.'/courses?ciclo=4&q=other&per_page=1')->assertOk()->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.codigo', 'OTHER');
        $this->getJson($root)->assertOk()->assertJsonPath('data.totales.docentes_exceden_limite', 1);
    }

    public function test_logically_deleted_assignments_free_capacity_but_remain_in_history(): void
    {
        $assignment = TeachingAssignment::factory()->create();
        Sanctum::actingAs(User::factory()->coordinator()->create());

        $this->deleteJson('/api/v1/coordinator/teaching-assignments/'.$assignment->id)->assertOk();
        $this->getJson($this->root($assignment->academicPeriod).'/courses/'.$assignment->course_id)->assertOk()
            ->assertJsonPath('data.carga.cantidad_docentes', 0)->assertJsonPath('data.carga.horas_teoria_pendientes', '2.00')
            ->assertJsonPath('data.paginacion.total', 0);
        $this->getJson($this->root($assignment->academicPeriod).'/teachers/'.$assignment->teacher_id)->assertOk()
            ->assertJsonPath('data.carga.total_semanal', '0.00')->assertJsonPath('data.carga.cantidad_cursos', 0);
        $this->assertSoftDeleted($assignment);
        $this->assertDatabaseCount('teaching_assignment_changes', 1);
    }

    public function test_empty_period_has_zero_totals_and_explicit_model_limitations(): void
    {
        $period = AcademicPeriod::factory()->create();
        Sanctum::actingAs(User::factory()->coordinator()->create());

        $this->getJson($this->root($period))->assertOk()->assertJsonPath('data.totales.asignaciones', 0)
            ->assertJsonPath('data.totales.total_semanal', '0.00')->assertJsonPath('data.totales.cursos_sin_asignacion', 0)
            ->assertJsonPath('data.advertencia', 'Modelo simplificado de una unidad docente por curso y período. No representa grupos paralelos, turnos, horarios ni conflictos de tiempo.');
    }

    #[TestWith(['per_page=101'])]
    #[TestWith(['page=0'])]
    #[TestWith(['teacher_id=9999999'])]
    #[TestWith(['estado=DESACTIVADO'])]
    #[TestWith(['q[]=test'])]
    #[TestWith(['excede_limite=invalid'])]
    public function test_invalid_report_filters_return_422(string $query): void
    {
        $period = AcademicPeriod::factory()->create();
        Sanctum::actingAs(User::factory()->coordinator()->create());

        $this->getJson($this->root($period).'/teachers?'.$query)->assertUnprocessable();
    }

    public function test_assignment_and_report_query_counts_do_not_grow_with_the_number_of_teachers(): void
    {
        $period = AcademicPeriod::factory()->create();
        $course = Course::factory()->create();
        TeachingAssignment::factory()->for($period)->for($course)->create();
        Sanctum::actingAs(User::factory()->coordinator()->create());
        $paths = ['/api/v1/coordinator/teaching-assignments?academic_period_id='.$period->id,
            $this->root($period).'/teachers', $this->root($period).'/courses'];
        $initial = [];
        foreach ($paths as $path) {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->getJson($path)->assertOk();
            $initial[] = count(DB::getQueryLog());
            DB::disableQueryLog();
        }
        TeachingAssignment::factory()->count(4)->for($period)->for($course)->create(['horas_teoria_asignadas' => '0.10']);
        foreach ($paths as $index => $path) {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->getJson($path)->assertOk();
            $this->assertSame($initial[$index], count(DB::getQueryLog()));
            DB::disableQueryLog();
        }
    }

    private function root(AcademicPeriod $period): string
    {
        return '/api/v1/coordinator/academic-periods/'.$period->id.'/teaching-load';
    }

    private function registerDemand(AcademicPeriod $period, Course $course, string $state): void
    {
        $request = PreEnrollmentRequest::factory()->for($period)->create(['estado' => 'ENVIADA']);
        $snapshot = PreEnrollmentRevisionFactory::new()->raw()['snapshot'];
        $snapshot['detalles'] = [['course_id' => $course->id, 'codigo' => $course->codigo, 'nombre' => $course->nombre,
            'creditos' => $course->creditos, 'preferencia_turno' => 'MANANA']];
        $revision = PreEnrollmentRevisionFactory::new()->for($request)->create(['snapshot' => $snapshot]);
        $revision->courses()->attach($course);
        $request->update(['current_revision_id' => $revision->id]);
        $request->academicValidation()->create(['estado' => $state, 'revision_id' => $revision->id, 'evaluated_at' => now(),
            'cursos' => [['course_id' => $course->id, 'estado' => $state]]]);
    }
}
