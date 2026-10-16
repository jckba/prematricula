<?php

namespace Tests\Feature;

use App\Models\AcademicHistory;
use App\Models\AcademicPeriod;
use App\Models\Course;
use App\Models\PreEnrollmentRequest;
use App\Models\PreEnrollmentWindow;
use App\Models\Student;
use App\Services\PreEnrollmentReceipt;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class StudentPreEnrollmentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_creates_a_single_draft_and_registers_an_advanced_course_without_approval(): void
    {
        [$student, $period] = $this->scenario();
        $course = Course::factory()->create(['ciclo' => 99]);
        $created = $this->postJson('/api/v1/student/pre-enrollment')->assertCreated()->json('data.id');
        $this->postJson('/api/v1/student/pre-enrollment')->assertOk()->assertJsonPath('data.id', $created);
        $this->postJson('/api/v1/student/pre-enrollment/courses', ['course_id' => $course->id, 'preferencia_turno' => 'MANANA'])->assertCreated();

        $this->postJson('/api/v1/student/pre-enrollment/submit')->assertOk()
            ->assertJsonPath('data.estado', 'ENVIADA')->assertJsonPath('data.revision_vigente.revision_number', 1)
            ->assertJsonPath('data.revision_vigente.receipt_available', true);

        $request = PreEnrollmentRequest::sole();
        $this->assertDatabaseCount('pre_enrollment_requests', 1);
        $this->assertDatabaseCount('pre_enrollment_revisions', 1);
        $this->assertNull($request->reviewed_by);
        $this->assertNull($request->fecha_revision);
        $this->assertNotNull($request->fecha_envio);
        $this->assertSame($period->windows()->sole()->id, $request->currentRevision->window_id);
    }

    #[TestWith(['APROBADO', true])]
    #[TestWith(['EN_CURSO', true])]
    #[TestWith(['DESAPROBADO', false])]
    #[TestWith(['RETIRADO', false])]
    public function test_official_prerequisite_status_controls_eligibility_and_submission(string $state, bool $eligible): void
    {
        [$student] = $this->scenario();
        $base = Course::factory()->create(['codigo' => 'BASE']);
        $advanced = Course::factory()->create(['codigo' => 'ADV', 'ciclo' => 20]);
        $advanced->prerequisites()->attach($base);
        $historyPeriod = AcademicPeriod::factory()->closed()->create(['fecha_inicio' => '2026-04-01', 'fecha_fin' => '2026-07-31']);
        AcademicHistory::factory()->for($student)->for($base, 'course')->for($historyPeriod, 'academicPeriod')
            ->create(['estado' => $state, 'nota_final' => in_array($state, ['APROBADO', 'DESAPROBADO'], true) ? 15 : null]);

        $response = $this->postJson('/api/v1/student/pre-enrollment/courses', ['course_id' => $advanced->id]);

        if ($eligible) {
            $response->assertCreated();
            $this->postJson('/api/v1/student/pre-enrollment/submit')->assertOk()
                ->assertJsonPath('data.validacion_academica.estado', $state === 'EN_CURSO' ? 'PENDIENTE_RESULTADOS' : 'VALIDA');
        } else {
            $response->assertUnprocessable();
            $this->assertDatabaseCount('pre_enrollment_details', 0);
            $this->assertDatabaseCount('pre_enrollment_revisions', 0);
        }
    }

    #[TestWith(['APROBADO'])]
    #[TestWith(['EN_CURSO'])]
    public function test_already_approved_or_in_progress_courses_are_not_offered_again(string $state): void
    {
        [$student] = $this->scenario();
        $course = Course::factory()->create();
        $historyPeriod = AcademicPeriod::factory()->closed()->create(['fecha_inicio' => '2026-04-01', 'fecha_fin' => '2026-07-31']);
        AcademicHistory::factory()->for($student)->for($course)->for($historyPeriod)->create(['estado' => $state]);
        $this->getJson('/api/v1/student/pre-enrollment/available-courses')->assertOk()->assertJsonCount(0, 'data.cursos');

        $this->postJson('/api/v1/student/pre-enrollment/courses', ['course_id' => $course->id])->assertUnprocessable();

        $this->assertDatabaseCount('pre_enrollment_details', 0);
    }

    public function test_duplicates_and_credit_overflow_are_rejected(): void
    {
        [$student] = $this->scenario();
        $student->update(['creditos_maximos' => 4]);
        $first = Course::factory()->create(['creditos' => 4]);
        $second = Course::factory()->create(['creditos' => 1]);
        $this->postJson('/api/v1/student/pre-enrollment/courses', ['course_id' => $first->id])->assertCreated();

        $this->postJson('/api/v1/student/pre-enrollment/courses', ['course_id' => $first->id])->assertUnprocessable();
        $this->postJson('/api/v1/student/pre-enrollment/courses', ['course_id' => $second->id])->assertUnprocessable();

        $this->assertDatabaseCount('pre_enrollment_details', 1);
    }

    #[TestWith(['MANANA'])]
    #[TestWith(['TARDE'])]
    #[TestWith(['SIN_PREFERENCIA'])]
    public function test_supported_preferences_are_recorded_in_the_immutable_revision(string $preference): void
    {
        $this->scenario();
        $course = Course::factory()->create();
        $detailId = $this->postJson('/api/v1/student/pre-enrollment/courses', ['course_id' => $course->id])->assertCreated()->json('data.detail_id');
        $this->patchJson('/api/v1/student/pre-enrollment/courses/'.$detailId.'/preference', ['preferencia_turno' => $preference])->assertOk();

        $this->postJson('/api/v1/student/pre-enrollment/submit')->assertOk()
            ->assertJsonPath('data.revision_vigente.snapshot.detalles.0.preferencia_turno', $preference);
    }

    public function test_night_preference_is_rejected(): void
    {
        $this->scenario();
        $course = Course::factory()->create();

        $this->postJson('/api/v1/student/pre-enrollment/courses', ['course_id' => $course->id, 'preferencia_turno' => 'NOCHE'])
            ->assertUnprocessable()->assertJsonValidationErrors('preferencia_turno');
        $this->assertDatabaseCount('pre_enrollment_details', 0);
    }

    public function test_any_submitted_student_can_start_an_explicit_correction_during_an_extraordinary_window(): void
    {
        [$student, $period] = $this->scenario();
        $first = Course::factory()->create(['codigo' => 'ORIGINAL']);
        $this->postJson('/api/v1/student/pre-enrollment/courses', ['course_id' => $first->id])->assertCreated();
        $this->postJson('/api/v1/student/pre-enrollment/submit')->assertOk();
        $request = PreEnrollmentRequest::sole();
        $original = $request->currentRevision;
        $this->extraordinary($period);

        $this->postJson('/api/v1/student/pre-enrollments/'.$request->id.'/corrections')->assertOk()
            ->assertJsonPath('data.estado', 'BORRADOR')->assertJsonPath('data.current_revision_id', $original->id);
        $this->postJson('/api/v1/student/pre-enrollments/'.$request->id.'/corrections')->assertOk();
        $this->assertDatabaseCount('pre_enrollment_revisions', 1);
        $this->deleteJson('/api/v1/student/pre-enrollment/courses/'.$request->details()->sole()->id)->assertOk();
        $second = Course::factory()->create(['codigo' => 'NUEVO']);
        $this->postJson('/api/v1/student/pre-enrollment/courses', ['course_id' => $second->id])->assertCreated();

        $this->postJson('/api/v1/student/pre-enrollment/submit')->assertOk()
            ->assertJsonPath('data.estado', 'ENVIADA')->assertJsonPath('data.revision_vigente.revision_number', 2)
            ->assertJsonPath('data.revision_vigente.snapshot.detalles.0.codigo', 'NUEVO');

        $request->refresh();
        $this->assertDatabaseCount('pre_enrollment_requests', 1);
        $this->assertDatabaseCount('pre_enrollment_revisions', 2);
        $this->assertSame($original->snapshot, $original->fresh()->snapshot);
        $this->assertTrue($request->fecha_envio->gt($original->submitted_at));
        $this->assertNotSame($original->receipt_code, $request->currentRevision->receipt_code);
        $this->assertNull($request->correction_window_id);
    }

    public function test_failed_resubmission_keeps_the_latest_registered_revision_and_pdf(): void
    {
        [$student, $period] = $this->scenario();
        $course = Course::factory()->create();
        $this->postJson('/api/v1/student/pre-enrollment/courses', ['course_id' => $course->id])->assertCreated();
        $this->postJson('/api/v1/student/pre-enrollment/submit')->assertOk();
        $request = PreEnrollmentRequest::sole();
        $original = $request->currentRevision;
        $this->extraordinary($period);
        $this->postJson('/api/v1/student/pre-enrollments/'.$request->id.'/corrections')->assertOk();
        $student->update(['creditos_maximos' => 1]);

        $this->postJson('/api/v1/student/pre-enrollment/submit')->assertUnprocessable();

        $this->assertSame($original->id, $request->fresh()->current_revision_id);
        $this->assertSame('BORRADOR', $request->fresh()->estado->value);
        $this->assertSame($original->submitted_at->toISOString(), $request->fresh()->fecha_envio->toISOString());
        $this->assertSame($original->pdf_sha256, $original->fresh()->pdf_sha256);
        $this->assertDatabaseCount('pre_enrollment_revisions', 1);
    }

    public function test_pdf_generation_failure_rolls_back_the_submission(): void
    {
        $this->scenario();
        $course = Course::factory()->create();
        $this->postJson('/api/v1/student/pre-enrollment/courses', ['course_id' => $course->id])->assertCreated();
        $this->mock(PreEnrollmentReceipt::class)->shouldReceive('render')->once()->andThrow(new DomainException('Error de emisión de prueba.'));

        $this->postJson('/api/v1/student/pre-enrollment/submit')->assertUnprocessable();

        $request = PreEnrollmentRequest::sole();
        $this->assertSame('BORRADOR', $request->estado->value);
        $this->assertNull($request->current_revision_id);
        $this->assertNull($request->fecha_envio);
        $this->assertDatabaseCount('pre_enrollment_revisions', 0);
    }

    #[TestWith(['POST', '/api/v1/student/pre-enrollment'])]
    #[TestWith(['POST', '/api/v1/student/pre-enrollment/courses'])]
    #[TestWith(['PATCH', '/api/v1/student/pre-enrollment/courses/{detail}/preference'])]
    #[TestWith(['DELETE', '/api/v1/student/pre-enrollment/courses/{detail}'])]
    #[TestWith(['POST', '/api/v1/student/pre-enrollment/submit'])]
    #[TestWith(['POST', '/api/v1/student/pre-enrollments/{request}/corrections'])]
    public function test_every_student_write_is_blocked_after_window_closes(string $method, string $path): void
    {
        $this->scenario();
        $course = Course::factory()->create();
        $id = $this->postJson('/api/v1/student/pre-enrollment/courses', ['course_id' => $course->id])->assertCreated()->json('data.detail_id');
        $request = PreEnrollmentRequest::sole();
        $this->travel(2)->days();
        $path = str_replace(['{detail}', '{request}'], [(string) $id, (string) $request->id], $path);

        $this->json($method, $path, ['course_id' => $course->id, 'preferencia_turno' => 'TARDE'])->assertUnprocessable();

        $this->assertDatabaseCount('pre_enrollment_details', 1);
        $this->assertDatabaseCount('pre_enrollment_revisions', 0);
        $this->getJson('/api/v1/student/pre-enrollment')->assertOk();
    }

    public function test_unsubmitted_draft_can_be_sent_during_global_extraordinary_window(): void
    {
        [$student, $period] = $this->scenario();
        $course = Course::factory()->create();
        $this->postJson('/api/v1/student/pre-enrollment/courses', ['course_id' => $course->id])->assertCreated();
        $this->extraordinary($period);

        $this->postJson('/api/v1/student/pre-enrollment/submit')->assertOk()
            ->assertJsonPath('data.revision_vigente.revision_number', 1);
    }

    /** @return array{Student, AcademicPeriod} */
    private function scenario(): array
    {
        $this->travelTo('2026-10-16 15:00:00 UTC');
        $student = Student::factory()->create();
        $period = AcademicPeriod::factory()->create(['fecha_inicio' => '2027-04-01', 'fecha_fin' => '2027-08-01']);
        $this->withToken($student->user->createToken('test')->plainTextToken);

        return [$student, $period];
    }

    private function extraordinary(AcademicPeriod $period): void
    {
        $this->travel(2)->days();
        PreEnrollmentWindow::factory()->for($period)->create(['tipo' => 'EXTRAORDINARIA',
            'fecha_inicio' => now()->subHour(), 'fecha_fin' => now()->addDay()]);
    }
}
