<?php

namespace Tests\Feature\UseCases\PreEnrollment;

use App\Enums\AcademicPeriodStatus;
use App\Enums\PreEnrollmentStatus;
use App\Enums\RecordStatus;
use App\Enums\UserRole;
use App\Models\AcademicPeriod;
use App\Models\Course;
use App\Models\PreEnrollmentDetail;
use App\Models\PreEnrollmentRequest;
use App\Models\Student;
use App\Models\User;
use App\UseCases\PreEnrollment\SubmitPreEnrollment;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubmitPreEnrollmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_does_not_submit_an_empty_pre_enrollment(): void
    {
        [$student, $request] = $this->createScenario();

        $useCase = app(SubmitPreEnrollment::class);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage(
            'Debe seleccionar al menos un curso.'
        );

        $useCase($request);
    }

    public function test_it_submits_a_valid_pre_enrollment(): void
    {
        [$student, $request] = $this->createScenario();

        $course = $this->createCourse();

        PreEnrollmentDetail::create([
            'pre_enrollment_request_id' => $request->id,
            'course_id' => $course->id,
            'preferencia_turno' => 'SIN_PREFERENCIA',
        ]);

        $useCase = app(SubmitPreEnrollment::class);

        $result = $useCase($request);

        $this->assertSame(
            PreEnrollmentStatus::ENVIADA,
            $result->estado
        );
    }

    public function test_it_registers_submission_date(): void
    {
        [$student, $request] = $this->createScenario();

        $course = $this->createCourse();

        PreEnrollmentDetail::create([
            'pre_enrollment_request_id' => $request->id,
            'course_id' => $course->id,
            'preferencia_turno' => 'SIN_PREFERENCIA',
        ]);

        $useCase = app(SubmitPreEnrollment::class);

        $result = $useCase($request);

        $this->assertNotNull(
            $result->fecha_envio
        );
    }

    public function test_it_does_not_allow_submitting_twice(): void
    {
        [$student, $request] = $this->createScenario();

        $course = $this->createCourse();

        PreEnrollmentDetail::create([
            'pre_enrollment_request_id' => $request->id,
            'course_id' => $course->id,
            'preferencia_turno' => 'SIN_PREFERENCIA',
        ]);

        $useCase = app(SubmitPreEnrollment::class);

        $useCase($request);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage(
            'Solo una solicitud en borrador puede ser enviada.'
        );

        $useCase($request->refresh());
    }

    private function createScenario(): array
    {
        $user = User::create([
            'correo_institucional' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'rol' => UserRole::ESTUDIANTE,
            'estado' => RecordStatus::ACTIVO,
        ]);

        $student = Student::create([
            'user_id' => $user->id,
            'codigo_universitario' => fake()->unique()->numerify('##########'),
            'nombres' => 'Test',
            'apellidos' => 'Student',
            'creditos_maximos' => 24,
            'estado' => RecordStatus::ACTIVO,
        ]);

        $period = AcademicPeriod::create([
            'codigo' => '2027-I',
            'fecha_inicio' => now()->addMonths(3)->toDateString(),
            'fecha_fin' => now()->addMonths(7)->toDateString(),
            'fecha_inicio_prematricula' => now()->subDay(),
            'fecha_fin_prematricula' => now()->addWeek(),
            'estado' => AcademicPeriodStatus::PREMATRICULA,
        ]);

        $request = PreEnrollmentRequest::create([
            'student_id' => $student->id,
            'academic_period_id' => $period->id,
            'estado' => PreEnrollmentStatus::BORRADOR,
        ]);

        return [$student, $request];
    }

    private function createCourse(): Course
    {
        return Course::create([
            'codigo' => 'INF301',
            'nombre' => 'Base de Datos',
            'creditos' => 4,
            'ciclo' => 3,
            'estado' => RecordStatus::ACTIVO,
        ]);
    }
}
