<?php

namespace Tests\Feature\UseCases\PreEnrollment;

use App\Enums\AcademicPeriodStatus;
use App\Enums\PreEnrollmentStatus;
use App\Enums\RecordStatus;
use App\Enums\SchedulePreference;
use App\Enums\UserRole;
use App\Models\AcademicPeriod;
use App\Models\Course;
use App\Models\PreEnrollmentRequest;
use App\Models\Student;
use App\Models\User;
use App\UseCases\PreEnrollment\AddCourse;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AddCourseTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_adds_an_eligible_course(): void
    {
        [$student, $preEnrollment, $course] = $this->createBaseScenario();

        $useCase = app(AddCourse::class);

        $detail = $useCase(
            $preEnrollment,
            $course,
            SchedulePreference::MANANA
        );

        $this->assertDatabaseHas('pre_enrollment_details', [
            'id' => $detail->id,
            'pre_enrollment_request_id' => $preEnrollment->id,
            'course_id' => $course->id,
            'preferencia_turno' => SchedulePreference::MANANA->value,
        ]);
    }

    public function test_it_does_not_allow_the_same_course_twice(): void
    {
        [$student, $preEnrollment, $course] = $this->createBaseScenario();

        $useCase = app(AddCourse::class);

        $useCase(
            $preEnrollment,
            $course,
            SchedulePreference::SIN_PREFERENCIA
        );

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage(
            'El curso ya fue agregado a la prematrícula.'
        );

        $preEnrollment->unsetRelation('details');

        $useCase(
            $preEnrollment,
            $course,
            SchedulePreference::SIN_PREFERENCIA
        );
    }

    public function test_it_does_not_allow_exceeding_credit_limit(): void
    {
        [$student, $preEnrollment] = $this->createBaseScenario(
            creditLimit: 5
        );

        $course1 = $this->createCourse(
            code: 'INF302',
            name: 'Base de Datos',
            credits: 4
        );

        $course2 = $this->createCourse(
            code: 'INF303',
            name: 'Algoritmos',
            credits: 4
        );

        $useCase = app(AddCourse::class);

        $useCase(
            $preEnrollment,
            $course1,
            SchedulePreference::SIN_PREFERENCIA
        );

        $preEnrollment->unsetRelation('details');

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage(
            'La selección supera el límite máximo de créditos permitido.'
        );

        $useCase(
            $preEnrollment,
            $course2,
            SchedulePreference::SIN_PREFERENCIA
        );
    }

    public function test_it_does_not_allow_modifying_a_submitted_request(): void
    {
        [$student, $preEnrollment, $course] = $this->createBaseScenario();

        $preEnrollment->update([
            'estado' => PreEnrollmentStatus::ENVIADA,
            'fecha_envio' => now(),
        ]);

        $useCase = app(AddCourse::class);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage(
            'Solo se pueden modificar solicitudes en estado BORRADOR.'
        );

        $useCase(
            $preEnrollment,
            $course,
            SchedulePreference::SIN_PREFERENCIA
        );
    }

    public function test_it_does_not_allow_a_course_when_prerequisites_are_not_met(): void
    {
        [$student, $preEnrollment] = $this->createBaseScenario();

        $prerequisite = $this->createCourse(
            code: 'INF202',
            name: 'Programación II',
            credits: 5,
            cycle: 2
        );

        $course = $this->createCourse(
            code: 'INF302',
            name: 'Base de Datos',
            credits: 4,
            cycle: 3
        );

        $course->prerequisites()->attach($prerequisite->id);

        $useCase = app(AddCourse::class);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage(
            'El estudiante no cumple los requisitos para seleccionar este curso.'
        );

        $useCase(
            $preEnrollment,
            $course,
            SchedulePreference::SIN_PREFERENCIA
        );
    }

    private function createBaseScenario(
        int $creditLimit = 24
    ): array {
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
            'creditos_maximos' => $creditLimit,
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

        $preEnrollment = PreEnrollmentRequest::create([
            'student_id' => $student->id,
            'academic_period_id' => $period->id,
            'estado' => PreEnrollmentStatus::BORRADOR,
        ]);

        $course = $this->createCourse(
            code: 'INF301',
            name: 'Base de Datos',
            credits: 4
        );

        return [$student, $preEnrollment, $course];
    }

    private function createCourse(
        string $code,
        string $name,
        int $credits,
        int $cycle = 3
    ): Course {
        return Course::create([
            'codigo' => $code,
            'nombre' => $name,
            'creditos' => $credits,
            'ciclo' => $cycle,
            'estado' => RecordStatus::ACTIVO,
        ]);
    }
}
