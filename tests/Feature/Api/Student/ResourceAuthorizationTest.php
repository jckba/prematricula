<?php

namespace Tests\Feature\Api\Student;

use App\Enums\AcademicPeriodStatus;
use App\Enums\CoordinatorPosition;
use App\Enums\PreEnrollmentStatus;
use App\Enums\RecordStatus;
use App\Enums\SchedulePreference;
use App\Models\AcademicPeriod;
use App\Models\Course;
use App\Models\PreEnrollmentDetail;
use App\Models\PreEnrollmentRequest;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class ResourceAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_history_only_contains_the_authenticated_students_resources(): void
    {
        $student = Student::factory()->create();
        $ownDetail = $this->createDetail($student);
        $otherDetail = $this->createDetail(Student::factory()->create());

        $this->withToken($student->user->createToken('test')->plainTextToken)
            ->getJson('/api/v1/student/pre-enrollments?student_id='.$otherDetail->preEnrollmentRequest->student_id)
            ->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $ownDetail->pre_enrollment_request_id)
            ->assertJsonPath('data.0.estudiante.codigo_universitario', $student->codigo_universitario);
    }

    public function test_current_pre_enrollment_ignores_another_students_id(): void
    {
        $student = Student::factory()->create();
        $ownDetail = $this->createDetail($student);
        $otherDetail = $this->createDetail(Student::factory()->create());

        $this->withToken($student->user->createToken('test')->plainTextToken)
            ->getJson('/api/v1/student/pre-enrollment?student_id='.$otherDetail->preEnrollmentRequest->student_id)
            ->assertOk()->assertJsonPath('data.id', $ownDetail->pre_enrollment_request_id);
    }

    public function test_returns_404_without_deleting_another_students_detail(): void
    {
        $student = Student::factory()->create();
        $detail = $this->createDetail(Student::factory()->create());

        $this->withToken($student->user->createToken('test')->plainTextToken)
            ->deleteJson('/api/v1/student/pre-enrollment/courses/'.$detail->id)
            ->assertNotFound();

        $this->assertModelExists($detail);
    }

    public function test_returns_404_without_updating_another_students_preference(): void
    {
        $student = Student::factory()->create();
        $detail = $this->createDetail(Student::factory()->create());

        $this->withToken($student->user->createToken('test')->plainTextToken)
            ->patchJson('/api/v1/student/pre-enrollment/courses/'.$detail->id.'/preference', [
                'preferencia_turno' => 'TARDE',
            ])->assertNotFound();

        $this->assertSame(SchedulePreference::SIN_PREFERENCIA, $detail->refresh()->preferencia_turno);
    }

    public function test_student_can_delete_own_detail(): void
    {
        $student = Student::factory()->create();
        $detail = $this->createDetail($student);

        $this->withToken($student->user->createToken('test')->plainTextToken)
            ->deleteJson('/api/v1/student/pre-enrollment/courses/'.$detail->id)
            ->assertOk();

        $this->assertModelMissing($detail);
    }

    public function test_student_can_update_own_preference(): void
    {
        $student = Student::factory()->create();
        $detail = $this->createDetail($student);

        $this->withToken($student->user->createToken('test')->plainTextToken)
            ->patchJson('/api/v1/student/pre-enrollment/courses/'.$detail->id.'/preference', [
                'preferencia_turno' => 'TARDE',
            ])->assertOk()->assertJsonPath('data.preferencia_turno', 'TARDE');

        $this->assertSame(SchedulePreference::TARDE, $detail->refresh()->preferencia_turno);
    }

    public function test_student_cannot_submit_another_students_request_by_injecting_ids(): void
    {
        $student = Student::factory()->create();
        $otherDetail = $this->createDetail(Student::factory()->create());

        $this->withToken($student->user->createToken('test')->plainTextToken)
            ->postJson('/api/v1/student/pre-enrollment/submit', [
                'student_id' => $otherDetail->preEnrollmentRequest->student_id,
                'pre_enrollment_request_id' => $otherDetail->pre_enrollment_request_id,
            ])->assertNotFound();

        $this->assertSame(PreEnrollmentStatus::BORRADOR, $otherDetail->preEnrollmentRequest->refresh()->estado);
    }

    public function test_adding_a_course_ignores_injected_owner_and_request_ids(): void
    {
        $student = Student::factory()->create();
        $otherDetail = $this->createDetail(Student::factory()->create());

        $response = $this->withToken($student->user->createToken('test')->plainTextToken)
            ->postJson('/api/v1/student/pre-enrollment/courses', [
                'course_id' => $otherDetail->course_id,
                'student_id' => $otherDetail->preEnrollmentRequest->student_id,
                'pre_enrollment_request_id' => $otherDetail->pre_enrollment_request_id,
            ])->assertCreated();

        $createdDetail = PreEnrollmentDetail::findOrFail($response->json('data.detail_id'));
        $this->assertSame($student->id, $createdDetail->preEnrollmentRequest->student_id);
        $this->assertSame(1, $otherDetail->preEnrollmentRequest->details()->count());
        $this->assertModelExists($otherDetail);
    }

    public function test_student_cannot_access_coordinator_endpoints_with_a_real_token(): void
    {
        $student = Student::factory()->create();

        $this->withToken($student->user->createToken('test')->plainTextToken)
            ->getJson('/api/v1/coordinator/pre-enrollments')->assertForbidden();
    }

    #[TestWith([CoordinatorPosition::DIRECTOR_ESCUELA])]
    #[TestWith([CoordinatorPosition::SECRETARIA])]
    public function test_both_coordinator_positions_are_forbidden_from_student_endpoints(CoordinatorPosition $position): void
    {
        $coordinator = User::factory()->coordinator($position)->create();

        $this->withToken($coordinator->createToken('test')->plainTextToken)
            ->getJson('/api/v1/student/pre-enrollments')->assertForbidden();
    }

    #[TestWith([CoordinatorPosition::DIRECTOR_ESCUELA])]
    #[TestWith([CoordinatorPosition::SECRETARIA])]
    public function test_both_coordinator_positions_can_list_pre_enrollments(CoordinatorPosition $position): void
    {
        $coordinator = User::factory()->coordinator($position)->create();

        $this->withToken($coordinator->createToken('test')->plainTextToken)
            ->getJson('/api/v1/coordinator/pre-enrollments')->assertOk()->assertJsonCount(0, 'data');
    }

    private function createDetail(Student $student): PreEnrollmentDetail
    {
        $period = AcademicPeriod::firstOrCreate(['codigo' => '2027-I'], [
            'fecha_inicio' => now()->addMonths(3),
            'fecha_fin' => now()->addMonths(7),
            'fecha_inicio_prematricula' => now()->subDay(),
            'fecha_fin_prematricula' => now()->addWeek(),
            'estado' => AcademicPeriodStatus::PREMATRICULA,
        ]);
        $course = Course::firstOrCreate(['codigo' => 'INF301'], [
            'nombre' => 'Base de Datos',
            'creditos' => 4,
            'ciclo' => 3,
            'estado' => RecordStatus::ACTIVO,
        ]);
        $preEnrollment = PreEnrollmentRequest::create([
            'student_id' => $student->id,
            'academic_period_id' => $period->id,
            'estado' => PreEnrollmentStatus::BORRADOR,
        ]);

        return PreEnrollmentDetail::create([
            'pre_enrollment_request_id' => $preEnrollment->id,
            'course_id' => $course->id,
            'preferencia_turno' => SchedulePreference::SIN_PREFERENCIA,
        ]);
    }
}
