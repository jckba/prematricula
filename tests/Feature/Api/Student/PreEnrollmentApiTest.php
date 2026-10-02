<?php

namespace Tests\Feature\Api\Student;

use App\Enums\AcademicPeriodStatus;
use App\Enums\RecordStatus;
use App\Enums\UserRole;
use App\Models\AcademicPeriod;
use App\Models\Course;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PreEnrollmentApiTest extends TestCase
{
    use RefreshDatabase;
    public function test_student_can_get_available_courses(): void
    {
        [$user, $student] = $this->createStudent();

        $this->createOpenPeriod();

        Course::create([
            'codigo' => 'INF301',
            'nombre' => 'Base de Datos',
            'creditos' => 4,
            'ciclo' => 3,
            'estado' => RecordStatus::ACTIVO,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson(
            '/api/student/pre-enrollment/available-courses'
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.creditos_maximos',
                $student->creditos_maximos
            )
            ->assertJsonCount(
                1,
                'data.cursos'
            );
    }

    public function test_guest_cannot_access_student_pre_enrollment(): void
    {
        $response = $this->getJson(
            '/api/student/pre-enrollment'
        );

        $response->assertUnauthorized();
    }

    public function test_coordinator_cannot_access_student_routes(): void
    {
        $coordinator = User::create([
            'correo_institucional' => 'coordinator@test.edu.pe',
            'password' => 'password',
            'rol' => UserRole::COORDINADOR,
            'estado' => RecordStatus::ACTIVO,
        ]);

        Sanctum::actingAs($coordinator);

        $response = $this->getJson(
            '/api/student/pre-enrollment'
        );

        $response->assertForbidden();
    }

    private function createStudent(): array
    {
        $user = User::create([
            'correo_institucional' => 'student@test.edu.pe',
            'password' => 'password',
            'rol' => UserRole::ESTUDIANTE,
            'estado' => RecordStatus::ACTIVO,
        ]);

        $student = Student::create([
            'user_id' => $user->id,
            'codigo_universitario' => '1452700722',
            'nombres' => 'Alumno',
            'apellidos' => 'Prueba',
            'creditos_maximos' => 24,
            'estado' => RecordStatus::ACTIVO,
        ]);

        return [$user, $student];
    }

    private function createOpenPeriod(): AcademicPeriod
    {
        return AcademicPeriod::create([
            'codigo' => '2027-I',
            'fecha_inicio' => now()->addMonths(3),
            'fecha_fin' => now()->addMonths(7),
            'fecha_inicio_prematricula' => now()->subDay(),
            'fecha_fin_prematricula' => now()->addWeek(),
            'estado' => AcademicPeriodStatus::PREMATRICULA,
        ]);
    }
    public function test_student_cannot_access_coordinator_routes(): void
    {
        $user = User::create([
            'correo_institucional' => 'student@test.edu.pe',
            'password' => 'password',
            'rol' => UserRole::ESTUDIANTE,
            'estado' => RecordStatus::ACTIVO,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson(
            '/api/coordinator/pre-enrollments'
        );

        $response->assertForbidden();
    }
}
