<?php

namespace Tests\Feature\UseCases\PreEnrollment;

use App\Enums\AcademicPeriodStatus;
use App\Enums\PreEnrollmentStatus;
use App\Enums\RecordStatus;
use App\Enums\UserRole;
use App\Models\AcademicPeriod;
use App\Models\PreEnrollmentRequest;
use App\Models\Student;
use App\Models\User;
use App\UseCases\PreEnrollment\ApprovePreEnrollment;
use App\UseCases\PreEnrollment\RejectPreEnrollment;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewPreEnrollmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_coordinator_can_approve_submitted_request(): void
    {
        [$request, $coordinator] = $this->createScenario();

        $result = app(ApprovePreEnrollment::class)(
            $request,
            $coordinator
        );

        $this->assertSame(
            PreEnrollmentStatus::APROBADA,
            $result->estado
        );

        $this->assertSame(
            $coordinator->id,
            $result->reviewed_by
        );

        $this->assertNotNull($result->fecha_revision);
    }

    public function test_coordinator_can_reject_submitted_request(): void
    {
        [$request, $coordinator] = $this->createScenario();

        $result = app(RejectPreEnrollment::class)(
            $request,
            $coordinator,
            'Debe corregir la selección de cursos.'
        );

        $this->assertSame(
            PreEnrollmentStatus::RECHAZADA,
            $result->estado
        );

        $this->assertSame(
            'Debe corregir la selección de cursos.',
            $result->comentarios_coordinador
        );

        $this->assertSame(
            $coordinator->id,
            $result->reviewed_by
        );

        $this->assertNotNull($result->fecha_revision);
    }

    public function test_rejection_requires_comment(): void
    {
        [$request, $coordinator] = $this->createScenario();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage(
            'Debe indicar el motivo del rechazo.'
        );

        app(RejectPreEnrollment::class)(
            $request,
            $coordinator,
            '   '
        );
    }

    public function test_student_cannot_review_request(): void
    {
        [$request] = $this->createScenario();

        $studentUser = User::create([
            'correo_institucional' => 'otro@universidad.edu.pe',
            'password' => 'password',
            'rol' => UserRole::ESTUDIANTE,
            'estado' => RecordStatus::ACTIVO,
        ]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage(
            'Solo un coordinador puede revisar una prematrícula.'
        );

        app(ApprovePreEnrollment::class)(
            $request,
            $studentUser
        );
    }

    public function test_draft_request_cannot_be_approved(): void
    {
        [$request, $coordinator] = $this->createScenario();

        $request->update([
            'estado' => PreEnrollmentStatus::BORRADOR,
        ]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage(
            'Solo se pueden revisar solicitudes en estado ENVIADA.'
        );

        app(ApprovePreEnrollment::class)(
            $request->refresh(),
            $coordinator
        );
    }

    private function createScenario(): array
    {
        $studentUser = User::create([
            'correo_institucional' => 'student@test.com',
            'password' => 'password',
            'rol' => UserRole::ESTUDIANTE,
            'estado' => RecordStatus::ACTIVO,
        ]);

        $student = Student::create([
            'user_id' => $studentUser->id,
            'codigo_universitario' => 'TEST001',
            'nombres' => 'Alumno',
            'apellidos' => 'Prueba',
            'creditos_maximos' => 24,
            'estado' => RecordStatus::ACTIVO,
        ]);

        $coordinator = User::create([
            'correo_institucional' => 'coordinator@test.com',
            'password' => 'password',
            'rol' => UserRole::COORDINADOR,
            'estado' => RecordStatus::ACTIVO,
        ]);

        $period = AcademicPeriod::create([
            'codigo' => '2027-I',
            'fecha_inicio' => now()->addMonths(3),
            'fecha_fin' => now()->addMonths(7),
            'fecha_inicio_prematricula' => now()->subDay(),
            'fecha_fin_prematricula' => now()->addWeek(),
            'estado' => AcademicPeriodStatus::PREMATRICULA,
        ]);

        $request = PreEnrollmentRequest::create([
            'student_id' => $student->id,
            'academic_period_id' => $period->id,
            'estado' => PreEnrollmentStatus::ENVIADA,
            'fecha_envio' => now(),
        ]);

        return [$request, $coordinator];
    }
}
