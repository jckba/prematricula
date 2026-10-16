<?php

namespace Tests\Feature\Api\Coordinator;

use App\Enums\CoordinatorPosition;
use App\Models\AcademicHistory;
use App\Models\AcademicPeriod;
use App\Models\Course;
use App\Models\PreEnrollmentRequest;
use App\Models\PreEnrollmentRevision;
use App\Models\PreEnrollmentWindow;
use App\Models\Student;
use App\Models\User;
use App\Services\AcademicHistoryImportValidator;
use App\Services\RevalidatePreEnrollments;
use App\UseCases\AcademicHistory\ImportAcademicHistory;
use Database\Factories\PreEnrollmentRevisionFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class AcademicDemandTest extends TestCase
{
    use RefreshDatabase;

    public function test_counts_only_current_registered_revisions_once_per_student_and_ignores_draft_edits(): void
    {
        $period = AcademicPeriod::factory()->create();
        $course = Course::factory()->create(['codigo' => 'MAT', 'nombre' => 'Matemática', 'ciclo' => 2]);
        $other = Course::factory()->create();
        $request = $this->registered($period, [$course]);
        $this->revision($request, [$other], 2);
        $request->details()->create(['course_id' => $other->id, 'preferencia_turno' => 'TARDE']);
        $this->registered($period, [$course], 'PENDIENTE_RESULTADOS', 'TARDE');
        $this->registered($period, [$course], 'REQUIERE_AJUSTE', 'SIN_PREFERENCIA');
        $this->registered(AcademicPeriod::factory()->create(), [$course]);
        $draft = PreEnrollmentRequest::factory()->for($period)->create();
        $draft->details()->create(['course_id' => $course->id, 'preferencia_turno' => 'MANANA']);
        Sanctum::actingAs(User::factory()->coordinator()->create());

        $this->getJson($this->path($period))->assertOk()->assertJsonCount(1, 'data.cursos')
            ->assertJsonPath('data.cursos.0.course_id', $course->id)
            ->assertJsonPath('data.cursos.0.nombre', 'Matemática')->assertJsonPath('data.cursos.0.ciclo', 2)
            ->assertJsonPath('data.cursos.0.creditos', 4)->assertJsonPath('data.cursos.0.registrada', 3)
            ->assertJsonPath('data.cursos.0.valida', 1)->assertJsonPath('data.cursos.0.pendiente_resultados', 1)
            ->assertJsonPath('data.cursos.0.requiere_ajuste', 1)->assertJsonPath('data.cursos.0.manana', 1)
            ->assertJsonPath('data.cursos.0.tarde', 1)->assertJsonPath('data.cursos.0.sin_preferencia', 1);
    }

    public function test_repeated_course_entries_in_a_revision_count_once_and_keep_one_preference(): void
    {
        $period = AcademicPeriod::factory()->create();
        $course = Course::factory()->create();
        $this->registered($period, [$course, $course]);
        Sanctum::actingAs(User::factory()->coordinator()->create());

        $this->getJson($this->path($period))->assertOk()->assertJsonPath('data.cursos.0.registrada', 1)
            ->assertJsonPath('data.cursos.0.valida', 1)->assertJsonPath('data.cursos.0.manana', 1);
    }

    public function test_any_request_requiring_adjustment_is_excluded_from_validated_demand_for_all_its_courses(): void
    {
        $period = AcademicPeriod::factory()->create();
        $validCourse = Course::factory()->create();
        $invalidCourse = Course::factory()->inactive()->create();
        $request = $this->registered($period, [$validCourse, $invalidCourse]);
        app(RevalidatePreEnrollments::class)->evaluate($request);
        Sanctum::actingAs(User::factory()->coordinator()->create());

        $this->getJson($this->path($period))->assertOk()->assertJsonCount(2, 'data.cursos')
            ->assertJsonPath('data.cursos.0.valida', 0)->assertJsonPath('data.cursos.0.requiere_ajuste', 1)
            ->assertJsonPath('data.cursos.1.valida', 0)->assertJsonPath('data.cursos.1.requiere_ajuste', 1);
    }

    public function test_empty_unsubmitted_and_legacy_captures_are_not_counted_and_missing_validation_is_explicit(): void
    {
        $period = AcademicPeriod::factory()->create();
        $course = Course::factory()->create();
        $this->registered($period, []);
        $unsubmitted = PreEnrollmentRequest::factory()->for($period)->create(['estado' => 'ENVIADA']);
        $revision = PreEnrollmentRevisionFactory::new()->for($unsubmitted)->create([
            'schema_version' => 1, 'submitted_at' => null, 'snapshot' => ['detalles' => [['course_id' => $course->id]]],
        ]);
        $revision->courses()->attach($course);
        $unsubmitted->update(['current_revision_id' => $revision->id]);
        $legacy = PreEnrollmentRequest::factory()->for($period)->create(['estado' => 'ENVIADA']);
        $capture = PreEnrollmentRevisionFactory::new()->for($legacy)->create(['motivo' => 'LEGACY_CAPTURE',
            'snapshot' => ['detalles' => [['course_id' => $course->id]]]]);
        $capture->courses()->attach($course);
        $legacy->update(['current_revision_id' => $capture->id]);
        $missing = $this->registered($period, [$course]);
        $missing->academicValidation()->delete();
        Sanctum::actingAs(User::factory()->coordinator()->create());

        $this->getJson($this->path($period))->assertOk()->assertJsonPath('data.cursos.0.registrada', 1)
            ->assertJsonPath('data.cursos.0.valida', 0)->assertJsonPath('data.cursos.0.sin_validacion', 1);
        $this->getJson($this->path($period, '/summary'))->assertOk()->assertJsonPath('data.alumnos_enviaron', 1)
            ->assertJsonPath('data.alumnos_sin_validacion', 1);
    }

    public function test_validation_from_replaced_revision_is_never_used_for_current_selection(): void
    {
        $period = AcademicPeriod::factory()->create();
        $course = Course::factory()->create();
        $request = $this->registered($period, [$course]);
        $revision = $this->revision($request, [$course], 2);
        $request->update(['current_revision_id' => $revision->id]);
        Sanctum::actingAs(User::factory()->coordinator()->create());

        $this->getJson($this->path($period))->assertOk()->assertJsonPath('data.cursos.0.registrada', 1)
            ->assertJsonPath('data.cursos.0.valida', 0)->assertJsonPath('data.cursos.0.sin_validacion', 1);
    }

    public function test_incomplete_snapshot_with_scalar_details_does_not_break_or_inflate_reports(): void
    {
        $period = AcademicPeriod::factory()->create();
        $request = PreEnrollmentRequest::factory()->for($period)->create(['estado' => 'ENVIADA']);
        $course = Course::factory()->create();
        $attributes = PreEnrollmentRevisionFactory::new()->raw();
        $attributes['snapshot']['detalles'] = null;
        $revision = PreEnrollmentRevisionFactory::new()->for($request)->create(['snapshot' => $attributes['snapshot']]);
        $revision->courses()->attach($course);
        $request->update(['current_revision_id' => $revision->id]);
        Sanctum::actingAs(User::factory()->coordinator()->create());

        $this->getJson($this->path($period))->assertOk()->assertJsonCount(0, 'data.cursos');
        $this->getJson($this->path($period, '/summary'))->assertOk()->assertJsonPath('data.alumnos_enviaron', 0);
    }

    #[TestWith(['incompleto'])]
    #[TestWith([['estado' => 'VALIDA']])]
    public function test_malformed_validation_details_keep_registered_demand_unvalidated(mixed $courses): void
    {
        $period = AcademicPeriod::factory()->create();
        $course = Course::factory()->create();
        $request = $this->registered($period, [$course]);
        $request->academicValidation()->update(['cursos' => json_encode($courses, JSON_THROW_ON_ERROR)]);
        Sanctum::actingAs(User::factory()->coordinator()->create());

        $this->getJson($this->path($period))->assertOk()->assertJsonPath('data.cursos.0.registrada', 1)
            ->assertJsonPath('data.cursos.0.valida', 0)->assertJsonPath('data.cursos.0.sin_validacion', 1);
        $this->getJson($this->path($period).'/export')->assertOk()->assertStreamed();
    }

    public function test_grade_changes_reclassify_current_revision_without_writing_on_report_reads(): void
    {
        $this->travelTo('2026-10-16 15:00:00 UTC');
        $period = AcademicPeriod::factory()->create(['fecha_inicio' => '2027-04-01']);
        $prior = AcademicPeriod::factory()->closed()->create(['fecha_inicio' => '2026-04-01']);
        $base = Course::factory()->create();
        $course = Course::factory()->create();
        $course->prerequisites()->attach($base);
        $request = $this->registered($period, [$course]);
        $history = AcademicHistory::factory()->for($request->student)->for($base, 'course')->for($prior, 'academicPeriod')
            ->create(['estado' => 'EN_CURSO', 'nota_final' => null]);
        $revalidate = app(RevalidatePreEnrollments::class);
        $revalidate->forStudents([$request->student_id]);
        Sanctum::actingAs(User::factory()->coordinator()->create());
        $this->getJson($this->path($period))->assertOk()->assertJsonPath('data.cursos.0.pendiente_resultados', 1);
        $this->importResult($history, 'APROBADO', 15);
        $this->getJson($this->path($period))->assertOk()->assertJsonPath('data.cursos.0.valida', 1);
        $this->importResult($history, 'DESAPROBADO', 8);
        DB::enableQueryLog();

        $this->getJson($this->path($period))->assertOk()->assertJsonPath('data.cursos.0.valida', 0)
            ->assertJsonPath('data.cursos.0.requiere_ajuste', 1);

        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        $this->assertCount(0, array_filter($queries, fn (array $query): bool => (bool) preg_match('/^\s*(insert|update|delete)\b/i', $query['query'])));
        $this->assertSame($request->current_revision_id, $request->fresh()->current_revision_id);
        $this->assertDatabaseCount('pre_enrollment_revisions', 1);
        $this->assertDatabaseCount('academic_history_imports', 2);
        $this->assertDatabaseCount('academic_history_changes', 2);
    }

    public function test_reopening_keeps_registered_courses_until_replacement_is_submitted(): void
    {
        $this->travelTo('2026-10-16 15:00:00 UTC');
        $period = AcademicPeriod::factory()->create();
        $course = Course::factory()->create();
        $replacement = Course::factory()->create();
        $request = $this->registered($period, [$course], 'REQUIERE_AJUSTE');
        $window = PreEnrollmentWindow::factory()->for($period)->create(['tipo' => 'EXTRAORDINARIA']);
        $request->update(['estado' => 'BORRADOR', 'correction_window_id' => $window->id]);
        $request->details()->create(['course_id' => $replacement->id, 'preferencia_turno' => 'TARDE']);
        Sanctum::actingAs(User::factory()->coordinator()->create());
        $this->getJson($this->path($period))->assertOk()->assertJsonCount(1, 'data.cursos')
            ->assertJsonPath('data.cursos.0.course_id', $course->id)->assertJsonPath('data.cursos.0.valida', 0);
        $revision = $this->revision($request, [$replacement], 2, 'TARDE');
        $request->update(['estado' => 'ENVIADA', 'current_revision_id' => $revision->id, 'correction_window_id' => null]);
        app(RevalidatePreEnrollments::class)->evaluate($request);

        $this->getJson($this->path($period))->assertOk()->assertJsonCount(1, 'data.cursos')
            ->assertJsonPath('data.cursos.0.course_id', $replacement->id)->assertJsonPath('data.cursos.0.registrada', 1)
            ->assertJsonPath('data.cursos.0.valida', 1)->assertJsonPath('data.cursos.0.tarde', 1);
    }

    public function test_summary_counts_unique_students_and_cycle_selections_without_inventing_a_period_population(): void
    {
        $period = AcademicPeriod::factory()->create();
        $first = Course::factory()->create(['ciclo' => 1]);
        $second = Course::factory()->create(['ciclo' => 1]);
        $third = Course::factory()->create(['ciclo' => 2]);
        $this->registered($period, [$first, $second, $third]);
        $this->registered($period, [$third], 'REQUIERE_AJUSTE');
        Student::factory()->create();
        Sanctum::actingAs(User::factory()->coordinator()->create());

        $this->getJson($this->path($period, '/summary'))->assertOk()
            ->assertJsonPath('data.alumnos_enviaron', 2)->assertJsonPath('data.alumnos_requieren_ajuste', 1)
            ->assertJsonPath('data.alumnos_no_enviaron', null)->assertJsonPath('data.poblacion_academica', null)
            ->assertJsonPath('data.ciclos.0.registrada', 2)->assertJsonPath('data.ciclos.0.alumnos_enviaron', 1)
            ->assertJsonPath('data.ciclos.1.registrada', 2)->assertJsonPath('data.ciclos.1.alumnos_enviaron', 2);
    }

    public function test_filters_sorting_pagination_and_interested_students_use_the_same_current_revision(): void
    {
        $period = AcademicPeriod::factory()->create();
        $course = Course::factory()->create(['codigo' => 'MAT01', 'nombre' => 'Matemática', 'ciclo' => 2]);
        $other = Course::factory()->create(['codigo' => 'MAT02', 'ciclo' => 2]);
        $this->registered($period, [$course, $other]);
        $this->registered($period, [$course], 'PENDIENTE_RESULTADOS', 'TARDE');
        Sanctum::actingAs(User::factory()->coordinator()->create());

        $this->getJson($this->path($period).'?q=mat&ciclo=2&sort=registrada&direction=desc&per_page=1')
            ->assertOk()->assertJsonPath('data.cursos.0.course_id', $course->id)->assertJsonPath('data.paginacion.total', 2);
        $this->getJson($this->path($period).'?ciclo=1')->assertOk()->assertJsonCount(0, 'data.cursos');
        $this->getJson($this->path($period).'?course_id='.$course->id.'&estado_validacion=PENDIENTE_RESULTADOS&preferencia_turno=TARDE')
            ->assertOk()->assertJsonPath('data.cursos.0.registrada', 1)->assertJsonPath('data.cursos.0.valida', 0)
            ->assertJsonPath('data.cursos.0.pendiente_resultados', 1);
        $this->getJson($this->path($period).'/courses/'.$course->id.'/students?preferencia_turno=TARDE&estado_validacion=PENDIENTE_RESULTADOS&per_page=1')
            ->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.preferencia_turno', 'TARDE')
            ->assertJsonMissingPath('data.0.correo_institucional')->assertJsonMissingPath('data.0.user_id');
    }

    public function test_csv_exports_all_filtered_rows_with_counts_warning_and_formula_escaping(): void
    {
        $period = AcademicPeriod::factory()->create();
        $first = Course::factory()->create(['codigo' => 'CSV01', 'nombre' => '=1+1', 'ciclo' => 2]);
        $second = Course::factory()->create(['codigo' => 'CSV02', 'ciclo' => 2]);
        $excluded = Course::factory()->create(['codigo' => 'NOEXPORT', 'ciclo' => 1]);
        $this->registered($period, [$first, $second, $excluded]);
        Sanctum::actingAs(User::factory()->coordinator()->create());

        $response = $this->get($this->path($period).'/export?ciclo=2&sort=codigo&direction=desc&per_page=1')->assertOk()
            ->assertDownload('demanda-'.$period->id.'.csv')->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $lines = explode("\n", trim($response->streamedContent()));
        $this->assertCount(4, $lines);
        $this->assertStringContainsString('no constituyen matrícula definitiva', $lines[0]);
        $this->assertStringContainsString('CSV02', $lines[2]);
        $row = str_getcsv($lines[3], escape: '');
        $this->assertSame("'=1+1", $row[3]);
        $this->assertSame('1', $row[6]);
        $this->assertStringNotContainsString('NOEXPORT', implode("\n", $lines));
    }

    #[TestWith(['sort=nombre;DROP%20TABLE%20students'])]
    #[TestWith(['direction=sideways'])]
    #[TestWith(['per_page=101'])]
    #[TestWith(['ciclo=0'])]
    #[TestWith(['estado_validacion=APROBADA'])]
    #[TestWith(['preferencia_turno=NOCHE'])]
    public function test_invalid_filters_return_422(string $query): void
    {
        $period = AcademicPeriod::factory()->create();
        Sanctum::actingAs(User::factory()->coordinator()->create());

        $this->getJson($this->path($period).'?'.$query)->assertUnprocessable();
    }

    public function test_all_reporting_endpoints_require_authentication_and_coordinator_role(): void
    {
        $period = AcademicPeriod::factory()->create();
        $course = Course::factory()->create();
        $paths = [$this->path($period), $this->path($period).'/export', $this->path($period, '/summary'),
            $this->path($period).'/courses/'.$course->id.'/students'];
        foreach ($paths as $path) {
            $this->getJson($path)->assertUnauthorized();
        }
        Sanctum::actingAs(Student::factory()->create()->user);
        foreach ($paths as $path) {
            $this->getJson($path)->assertForbidden();
        }
    }

    #[TestWith([CoordinatorPosition::DIRECTOR_ESCUELA])]
    #[TestWith([CoordinatorPosition::SECRETARIA])]
    public function test_both_coordinator_positions_can_consult_academic_records_and_reports(CoordinatorPosition $position): void
    {
        $period = AcademicPeriod::factory()->create();
        $course = Course::factory()->create();
        $request = $this->registered($period, [$course]);
        Sanctum::actingAs(User::factory()->coordinator($position)->create());

        $this->getJson('/api/v1/coordinator/students')->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson('/api/v1/coordinator/students/'.$request->student->codigo_universitario.'/academic?academic_period_id='.$period->id)
            ->assertOk()->assertJsonPath('data.prematricula.revision_id', $request->current_revision_id);
        $this->getJson($this->path($period))->assertOk()->assertJsonPath('data.cursos.0.registrada', 1);
        $this->getJson($this->path($period, '/summary'))->assertOk()->assertJsonPath('data.alumnos_enviaron', 1);
        $this->getJson($this->path($period).'/courses/'.$course->id.'/students')->assertOk()->assertJsonPath('total', 1);
        $this->get($this->path($period).'/export')->assertOk()->assertDownload('demanda-'.$period->id.'.csv');
    }

    public function test_inactive_coordinators_cannot_access_academic_information_or_exports(): void
    {
        $period = AcademicPeriod::factory()->create();
        $course = Course::factory()->create();
        $student = Student::factory()->create();
        Sanctum::actingAs(User::factory()->coordinator()->inactive()->create());
        $paths = ['/api/v1/coordinator/students',
            '/api/v1/coordinator/students/'.$student->codigo_universitario.'/academic?academic_period_id='.$period->id,
            $this->path($period), $this->path($period).'/export', $this->path($period, '/summary'),
            $this->path($period).'/courses/'.$course->id.'/students'];

        foreach ($paths as $path) {
            $this->getJson($path)->assertForbidden();
        }
    }

    public function test_reporting_query_count_is_bounded_as_the_number_of_requests_grows(): void
    {
        $period = AcademicPeriod::factory()->create();
        $course = Course::factory()->create();
        $this->registered($period, [$course]);
        Sanctum::actingAs(User::factory()->coordinator()->create());
        DB::enableQueryLog();
        $this->getJson($this->path($period))->assertOk();
        $initial = count(DB::getQueryLog());
        DB::disableQueryLog();
        for ($index = 0; $index < 5; $index++) {
            $this->registered($period, [$course]);
        }
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->getJson($this->path($period))->assertOk()->assertJsonPath('data.cursos.0.registrada', 6);

        $this->assertSame($initial, count(DB::getQueryLog()));
        DB::disableQueryLog();
    }

    private function path(AcademicPeriod $period, string $suffix = '/demand'): string
    {
        return '/api/v1/coordinator/academic-periods/'.$period->id.$suffix;
    }

    private function importResult(AcademicHistory $history, string $state, int $grade): void
    {
        $rows = [['row' => 2, 'codigo_universitario' => $history->student->codigo_universitario,
            'codigo_curso' => $history->course->codigo, 'codigo_periodo' => $history->academicPeriod->codigo,
            'nota_final' => $grade, 'estado' => $state]];
        $report = app(AcademicHistoryImportValidator::class)->validate($rows);
        app(ImportAcademicHistory::class)($rows, $report['history_fingerprint'], str_repeat('a', 64),
            'Actualización oficial de resultados', User::factory()->coordinator()->create());
    }

    /** @param list<Course> $courses */
    private function registered(AcademicPeriod $period, array $courses, string $state = 'VALIDA', string $preference = 'MANANA'): PreEnrollmentRequest
    {
        $request = PreEnrollmentRequest::factory()->for($period)->create(['estado' => 'ENVIADA', 'fecha_envio' => now()]);
        $revision = $this->revision($request, $courses, 1, $preference);
        $request->update(['current_revision_id' => $revision->id]);
        $request->academicValidation()->create(['estado' => $state, 'revision_id' => $revision->id, 'evaluated_at' => now(),
            'cursos' => array_map(fn (Course $course): array => ['course_id' => $course->id, 'codigo' => $course->codigo, 'estado' => $state], $courses)]);

        return $request;
    }

    /** @param list<Course> $courses */
    private function revision(PreEnrollmentRequest $request, array $courses, int $number, string $preference = 'MANANA'): PreEnrollmentRevision
    {
        $factory = PreEnrollmentRevisionFactory::new()->for($request);
        $attributes = $factory->raw();
        $attributes['snapshot']['detalles'] = array_map(fn (Course $course): array => ['course_id' => $course->id,
            'codigo' => $course->codigo, 'nombre' => $course->nombre, 'creditos' => $course->creditos, 'preferencia_turno' => $preference], $courses);
        $attributes['snapshot']['revision_number'] = $number;
        $revision = $factory->create(['revision_number' => $number, 'receipt_code' => $attributes['receipt_code'], 'snapshot' => $attributes['snapshot']]);
        $revision->courses()->attach(array_values(array_unique(array_map(fn (Course $course): int => $course->id, $courses))));

        return $revision;
    }
}
