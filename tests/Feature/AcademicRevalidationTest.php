<?php

namespace Tests\Feature;

use App\Enums\AcademicValidationStatus;
use App\Enums\PreEnrollmentStatus;
use App\Models\AcademicHistory;
use App\Models\AcademicPeriod;
use App\Models\Course;
use App\Models\PreEnrollmentRequest;
use App\Models\PreEnrollmentWindow;
use App\Models\Student;
use App\Models\User;
use App\Services\RevalidatePreEnrollments;
use App\UseCases\PreEnrollment\AddCourse;
use App\UseCases\PreEnrollment\GetAvailableCourses;
use App\UseCases\PreEnrollment\GetOrCreateDraft;
use App\UseCases\PreEnrollment\SubmitPreEnrollment;
use Database\Factories\PreEnrollmentRevisionFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\TestWith;
use Shuchkin\SimpleXLSXGen;
use Tests\TestCase;

class AcademicRevalidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_course_deactivation_revalidates_open_requests_without_rewriting_their_revisions(): void
    {
        $this->travelTo('2026-10-16 15:00:00 UTC');
        $course = Course::factory()->create();
        $open = AcademicPeriod::factory()->create();
        $closed = AcademicPeriod::factory()->closed()->create();
        $requests = [];
        foreach ([$open, $closed] as $period) {
            $request = PreEnrollmentRequest::factory()->for($period)->create(['estado' => 'ENVIADA']);
            $snapshot = PreEnrollmentRevisionFactory::new()->raw()['snapshot'];
            $snapshot['detalles'] = [['course_id' => $course->id, 'codigo' => $course->codigo,
                'nombre' => $course->nombre, 'creditos' => $course->creditos, 'preferencia_turno' => 'MANANA']];
            $revision = PreEnrollmentRevisionFactory::new()->for($request)->create(['snapshot' => $snapshot]);
            $revision->courses()->attach($course);
            $request->update(['current_revision_id' => $revision->id]);
            app(RevalidatePreEnrollments::class)->evaluate($request);
            $requests[] = $request;
        }
        $actor = User::factory()->coordinator()->create();
        $this->withToken($actor->createToken('test')->plainTextToken);

        $this->patchJson('/api/v1/coordinator/courses/'.$course->id, ['estado' => 'INACTIVO'])->assertOk();

        $this->assertSame(AcademicValidationStatus::REQUIERE_AJUSTE, $requests[0]->fresh()->academicValidation->estado);
        $this->assertSame(AcademicValidationStatus::VALIDA, $requests[1]->fresh()->academicValidation->estado);
        $this->assertSame($requests[0]->current_revision_id, $requests[0]->fresh()->current_revision_id);
        $this->assertDatabaseCount('pre_enrollment_revisions', 2);
    }

    #[TestWith(['APROBADO', 'VALIDA', 1])]
    #[TestWith(['DESAPROBADO', 'REQUIERE_AJUSTE', 0])]
    #[TestWith(['RETIRADO', 'REQUIERE_AJUSTE', 0])]
    public function test_final_results_revalidate_submitted_selections_without_rewriting_the_submission(string $result, string $validation, int $validDemand): void
    {
        [$student, $base, $advanced, $history, $request] = $this->scenario();
        $this->assertSame(AcademicValidationStatus::PENDIENTE_RESULTADOS, $request->academicValidation->estado);
        $sentAt = $request->fecha_envio->toISOString();
        $details = $request->details()->pluck('id')->all();
        $windows = $request->academicPeriod->windows()->get()->toJson();

        $this->importResult($student, $base, $history->academicPeriod, $result);

        $request->refresh()->load('academicValidation');
        $this->assertSame($validation, $request->academicValidation->estado->value);
        $this->assertSame(PreEnrollmentStatus::ENVIADA, $request->estado);
        $this->assertSame($sentAt, $request->fecha_envio->toISOString());
        $this->assertSame($details, $request->details()->pluck('id')->all());
        $this->assertSame($windows, $request->academicPeriod->windows()->get()->toJson());
        $this->assertSame($advanced->id, $request->academicValidation->cursos[0]['course_id']);
        $this->assertSame($result, $request->academicValidation->cursos[0]['prerrequisitos'][0]['estado_historial']);

        $this->getJson('/api/v1/coordinator/academic-periods/'.$request->academic_period_id.'/demand')->assertOk()
            ->assertJsonPath('data.cursos.0.valida', $validDemand);

        $this->asUser($student->user);
        $this->getJson('/api/v1/student/pre-enrollments')->assertOk()
            ->assertJsonPath('data.0.estado', 'ENVIADA')->assertJsonPath('data.0.validacion_academica.estado', $validation);
    }

    public function test_pending_results_do_not_count_as_finally_valid_demand(): void
    {
        [$student, $base, $advanced, $history, $request] = $this->scenario();
        $this->asUser(User::factory()->coordinator()->create());

        $this->getJson('/api/v1/coordinator/academic-periods/'.$request->academic_period_id.'/demand')->assertOk()
            ->assertJsonPath('data.cursos.0.valida', 0)->assertJsonPath('data.cursos.0.pendiente_resultados', 1);
    }

    public function test_official_grade_corrections_recompute_validation_deterministically(): void
    {
        [$student, $base, $advanced, $history, $request] = $this->scenario();
        $this->importResult($student, $base, $history->academicPeriod, 'DESAPROBADO');
        $this->assertSame('REQUIERE_AJUSTE', $request->fresh()->academicValidation->estado->value);

        $this->importResult($student, $base, $history->academicPeriod, 'APROBADO');

        $this->assertSame('VALIDA', $request->fresh()->academicValidation->estado->value);
        $this->assertDatabaseCount('academic_histories', 1);
        $this->assertDatabaseCount('academic_history_changes', 2);
    }

    public function test_approval_in_an_earlier_attempt_prevails_over_later_failed_attempts(): void
    {
        [$student, $base, $advanced, $history, $request] = $this->scenario();
        $earlier = AcademicPeriod::factory()->closed()->create(['codigo' => '2025-II', 'fecha_inicio' => '2025-08-01', 'fecha_fin' => '2025-12-31']);
        AcademicHistory::factory()->for($student)->for($base, 'course')->for($earlier, 'academicPeriod')->create(['estado' => 'APROBADO', 'nota_final' => 1]);

        $this->importResult($student, $base, $history->academicPeriod, 'DESAPROBADO');

        $this->assertSame('VALIDA', $request->fresh()->academicValidation->estado->value);
    }

    public function test_latest_attempt_controls_non_approved_prerequisites_and_future_results_do_not_qualify(): void
    {
        [$student, $base, $advanced, $history, $request] = $this->scenario();
        $future = AcademicPeriod::factory()->closed()->create(['codigo' => '2028-I', 'fecha_inicio' => '2028-04-01', 'fecha_fin' => '2028-08-01']);
        AcademicHistory::factory()->for($student)->for($base, 'course')->for($future, 'academicPeriod')->create(['estado' => 'APROBADO']);
        $this->importResult($student, $base, $history->academicPeriod, 'RETIRADO');

        $this->assertSame('REQUIERE_AJUSTE', $request->fresh()->academicValidation->estado->value);
        $this->assertFalse(app(GetAvailableCourses::class)($student, $request->academicPeriod)->contains('id', $advanced->id));
    }

    public function test_student_can_correct_only_during_global_extraordinary_window_and_keeps_original_snapshot(): void
    {
        [$student, $base, $advanced, $history, $request] = $this->scenario();
        $sentAt = $request->fecha_envio->toISOString();
        $this->importResult($student, $base, $history->academicPeriod, 'DESAPROBADO');
        $this->asUser($student->user);
        $correctionPath = '/api/v1/student/pre-enrollments/'.$request->id.'/corrections';
        $this->postJson($correctionPath)->assertUnprocessable();
        $this->travel(2)->days();
        PreEnrollmentWindow::factory()->for($request->academicPeriod)->create([
            'tipo' => 'EXTRAORDINARIA', 'fecha_inicio' => now()->subHour(), 'fecha_fin' => now()->addDay(), 'motivo' => 'Resultados finales',
        ]);
        $originalDetail = $request->details()->sole();
        $replacement = Course::factory()->create(['codigo' => 'LIBRE']);
        $this->postJson($correctionPath)->assertOk()->assertJsonPath('data.estado', 'BORRADOR')
            ->assertJsonPath('data.correccion_en_progreso', true);

        $this->deleteJson('/api/v1/student/pre-enrollment/courses/'.$originalDetail->id)->assertOk();
        $this->postJson('/api/v1/student/pre-enrollment/courses', ['course_id' => $replacement->id])->assertCreated();
        $this->postJson('/api/v1/student/pre-enrollment/submit')->assertOk()
            ->assertJsonPath('data.estado', 'ENVIADA')->assertJsonPath('data.correccion_en_progreso', false)
            ->assertJsonPath('data.validacion_academica.estado', 'VALIDA');

        $request->refresh();
        $this->assertTrue($request->fecha_envio->gt($request->revisions()->where('revision_number', 1)->sole()->submitted_at));
        $this->assertSame([$replacement->id], $request->details()->pluck('course_id')->all());
        $this->assertSame(2, $request->revisions()->count());
        $before = $request->revisions()->where('revision_number', 1)->sole();
        $this->assertSame($advanced->id, $before->snapshot['detalles'][0]['course_id']);
        $this->assertSame($sentAt, $before->snapshot['fecha_envio']);
    }

    public function test_correction_in_progress_is_excluded_from_valid_demand_and_expires_with_its_window(): void
    {
        [$student, $base, $advanced, $history, $request] = $this->scenario();
        $this->importResult($student, $base, $history->academicPeriod, 'DESAPROBADO');
        $this->travel(2)->days();
        PreEnrollmentWindow::factory()->for($request->academicPeriod)->create([
            'tipo' => 'EXTRAORDINARIA', 'fecha_inicio' => now()->subHour(), 'fecha_fin' => now()->addHour(),
        ]);
        $this->asUser($student->user);
        $this->postJson('/api/v1/student/pre-enrollments/'.$request->id.'/corrections')->assertOk();
        $replacement = Course::factory()->create(['codigo' => 'LIBRE']);
        $this->deleteJson('/api/v1/student/pre-enrollment/courses/'.$request->details()->sole()->id)->assertOk();
        $this->postJson('/api/v1/student/pre-enrollment/courses', ['course_id' => $replacement->id])->assertCreated();
        $this->asUser(User::factory()->coordinator()->create());

        $this->getJson('/api/v1/coordinator/academic-periods/'.$request->academic_period_id.'/demand')->assertOk()
            ->assertJsonPath('data.cursos.0.valida', 0)->assertJsonPath('data.cursos.0.requiere_ajuste', 1);

        $this->travel(2)->hours();
        $this->asUser($student->user);
        $this->postJson('/api/v1/student/pre-enrollment/submit')->assertUnprocessable();
        $this->assertNotNull($request->fresh()->correction_window_id);
    }

    public function test_other_students_cannot_start_corrections_on_another_request(): void
    {
        [$student, $base, $advanced, $history, $request] = $this->scenario();
        $this->asUser(Student::factory()->create()->user);

        $this->postJson('/api/v1/student/pre-enrollments/'.$request->id.'/corrections')->assertNotFound();

        $this->assertNull($request->fresh()->correction_window_id);
    }

    public function test_closed_period_requests_are_not_revalidated_by_result_imports(): void
    {
        [$student, $base, $advanced, $history, $request] = $this->scenario();
        $request->academicPeriod->update(['estado' => 'CERRADO']);

        $this->importResult($student, $base, $history->academicPeriod, 'DESAPROBADO');

        $this->assertSame('PENDIENTE_RESULTADOS', $request->fresh()->academicValidation->estado->value);
        $this->assertSame(PreEnrollmentStatus::ENVIADA, $request->fresh()->estado);
    }

    public function test_initialization_command_populates_existing_validation_without_altering_submission(): void
    {
        [$student, $base, $advanced, $history, $request] = $this->scenario();
        $request->academicValidation()->delete();
        $sentAt = $request->fecha_envio->toISOString();

        $this->artisan('academic:revalidate')->assertSuccessful();

        $this->assertSame('PENDIENTE_RESULTADOS', $request->fresh()->academicValidation->estado->value);
        $this->assertSame($sentAt, $request->fresh()->fecha_envio->toISOString());
    }

    /** @return array{Student, Course, Course, AcademicHistory, PreEnrollmentRequest} */
    private function scenario(): array
    {
        $this->travelTo('2026-10-16 15:00:00 UTC');
        $student = Student::factory()->create(['codigo_universitario' => '001']);
        $base = Course::factory()->create(['codigo' => 'BASE']);
        $advanced = Course::factory()->create(['codigo' => 'ADV']);
        $advanced->prerequisites()->attach($base);
        $earlier = AcademicPeriod::factory()->closed()->create(['codigo' => '2026-II', 'fecha_inicio' => '2026-08-01', 'fecha_fin' => '2026-12-31']);
        $history = AcademicHistory::factory()->for($student)->for($base, 'course')->for($earlier, 'academicPeriod')->create(['estado' => 'EN_CURSO', 'nota_final' => null]);
        $target = AcademicPeriod::factory()->create(['codigo' => '2027-I', 'fecha_inicio' => '2027-04-01', 'fecha_fin' => '2027-08-01']);
        $request = app(GetOrCreateDraft::class)($student, $target);
        app(AddCourse::class)($request, $advanced);
        app(SubmitPreEnrollment::class)($request);

        return [$student, $base, $advanced, $history, $request->refresh()->load(['academicValidation', 'academicPeriod'])];
    }

    private function importResult(Student $student, Course $course, AcademicPeriod $period, string $state): void
    {
        $this->asUser(User::factory()->coordinator()->create());
        $grade = in_array($state, ['APROBADO', 'DESAPROBADO'], true) ? 1 : null;
        $data = [['codigo_universitario', 'codigo_curso', 'codigo_periodo', 'nota_final', 'estado'],
            [SimpleXLSXGen::raw($student->codigo_universitario), SimpleXLSXGen::raw($course->codigo),
                SimpleXLSXGen::raw($period->codigo), $grade, $state]];
        $file = UploadedFile::fake()->createWithContent('resultados.xlsx', (string) SimpleXLSXGen::fromArray($data));
        $preview = $this->postJson('/api/v1/coordinator/academic-history/validate', ['file' => $file])->assertOk()->json('data');
        $this->postJson('/api/v1/coordinator/academic-history/import', [
            'file' => $file, 'sha256' => $preview['sha256'], 'history_fingerprint' => $preview['history_fingerprint'], 'motivo' => 'Resultados oficiales',
        ])->assertOk();
    }

    private function asUser(User $user): void
    {
        $this->app['auth']->forgetGuards();
        $this->withToken($user->createToken('test')->plainTextToken);
    }

    public function test_revalidation_failure_rolls_back_imported_notes_audits_and_validation_together(): void
    {
        [$student, $base, $advanced, $history, $request] = $this->scenario();
        $this->asUser(User::factory()->coordinator()->create());
        $rows = [['codigo_universitario', 'codigo_curso', 'codigo_periodo', 'nota_final', 'estado'],
            [SimpleXLSXGen::raw('001'), 'BASE', '2026-II', 5, 'DESAPROBADO']];
        $file = UploadedFile::fake()->createWithContent('resultados.xlsx', (string) SimpleXLSXGen::fromArray($rows));
        $preview = $this->postJson('/api/v1/coordinator/academic-history/validate', ['file' => $file])->assertOk()->json('data');
        DB::statement("ALTER TABLE pre_enrollment_validations ADD CONSTRAINT test_revalidation_failure CHECK (estado <> 'REQUIERE_AJUSTE')");

        $this->postJson('/api/v1/coordinator/academic-history/import', [
            'file' => $file, 'sha256' => $preview['sha256'], 'history_fingerprint' => $preview['history_fingerprint'], 'motivo' => 'Resultado final',
        ])->assertInternalServerError();

        $this->assertSame('EN_CURSO', $history->fresh()->estado->value);
        $this->assertNull($history->fresh()->nota_final);
        $this->assertDatabaseCount('academic_history_imports', 0);
        $this->assertDatabaseCount('academic_history_changes', 0);
        $this->assertSame('PENDIENTE_RESULTADOS', $request->fresh()->academicValidation->estado->value);
    }
}
