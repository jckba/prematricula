<?php

namespace Tests\Feature\UseCases\PreEnrollment;

use App\Enums\AcademicHistoryStatus;
use App\Enums\RecordStatus;
use App\Models\AcademicHistory;
use App\Models\AcademicPeriod;
use App\Models\Course;
use App\Models\Student;
use App\Models\User;
use App\UseCases\PreEnrollment\GetAvailableCourses;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GetAvailableCoursesTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_does_not_return_an_approved_course(): void
    {
        $user = User::create([
            'correo_institucional' => 'student@test.com',
            'password' => 'password',
            'rol' => 'ESTUDIANTE',
            'estado' => 'ACTIVO',
        ]);

        $student = Student::create([
            'user_id' => $user->id,
            'codigo_universitario' => 'TEST001',
            'nombres' => 'Test',
            'apellidos' => 'Student',
            'creditos_maximos' => 24,
            'estado' => 'ACTIVO',
        ]);

        $period = AcademicPeriod::create([
            'codigo' => '2026-I',
            'fecha_inicio' => '2026-04-01',
            'fecha_fin' => '2026-07-31',
            'estado' => 'CERRADO',
        ]);

        $course = Course::create([
            'codigo' => 'INF101',
            'nombre' => 'Programación I',
            'creditos' => 4,
            'ciclo' => 1,
            'estado' => RecordStatus::ACTIVO,
        ]);

        AcademicHistory::create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'academic_period_id' => $period->id,
            'nota_final' => 15,
            'estado' => AcademicHistoryStatus::APROBADO,
        ]);

        $useCase = app(GetAvailableCourses::class);

        $courses = $useCase->__invoke($student);

        $this->assertFalse(
            $courses->contains('id', $course->id)
        );
    }

    public function test_a_course_in_progress_satisfies_a_prerequisite_for_pre_enrollment(): void
    {
        $user = User::create([
            'correo_institucional' => 'student@test.com',
            'password' => 'password',
            'rol' => 'ESTUDIANTE',
            'estado' => 'ACTIVO',
        ]);

        $student = Student::create([
            'user_id' => $user->id,
            'codigo_universitario' => 'TEST001',
            'nombres' => 'Test',
            'apellidos' => 'Student',
            'creditos_maximos' => 24,
            'estado' => 'ACTIVO',
        ]);

        $currentPeriod = AcademicPeriod::create([
            'codigo' => '2026-II',
            'fecha_inicio' => '2026-08-01',
            'fecha_fin' => '2026-12-15',
            'estado' => 'EN_CURSO',
        ]);

        $programming2 = Course::create([
            'codigo' => 'INF201',
            'nombre' => 'Programación II',
            'creditos' => 5,
            'ciclo' => 2,
            'estado' => RecordStatus::ACTIVO,
        ]);

        $database = Course::create([
            'codigo' => 'INF301',
            'nombre' => 'Base de Datos',
            'creditos' => 4,
            'ciclo' => 3,
            'estado' => RecordStatus::ACTIVO,
        ]);

        $database->prerequisites()->attach(
            $programming2->id
        );

        AcademicHistory::create([
            'student_id' => $student->id,
            'course_id' => $programming2->id,
            'academic_period_id' => $currentPeriod->id,
            'nota_final' => null,
            'estado' => AcademicHistoryStatus::EN_CURSO,
        ]);

        $useCase = app(GetAvailableCourses::class);

        $courses = $useCase->__invoke($student);

        $this->assertTrue(
            $courses->contains('id', $database->id)
        );
    }

    public function test_a_failed_course_does_not_satisfy_a_prerequisite(): void
    {
        $user = User::create([
            'correo_institucional' => 'student@test.com',
            'password' => 'password',
            'rol' => 'ESTUDIANTE',
            'estado' => 'ACTIVO',
        ]);

        $student = Student::create([
            'user_id' => $user->id,
            'codigo_universitario' => 'TEST001',
            'nombres' => 'Test',
            'apellidos' => 'Student',
            'creditos_maximos' => 24,
            'estado' => 'ACTIVO',
        ]);

        $period = AcademicPeriod::create([
            'codigo' => '2026-I',
            'fecha_inicio' => '2026-04-01',
            'fecha_fin' => '2026-07-31',
            'estado' => 'CERRADO',
        ]);

        $programming2 = Course::create([
            'codigo' => 'INF201',
            'nombre' => 'Programación II',
            'creditos' => 5,
            'ciclo' => 2,
            'estado' => RecordStatus::ACTIVO,
        ]);

        $database = Course::create([
            'codigo' => 'INF301',
            'nombre' => 'Base de Datos',
            'creditos' => 4,
            'ciclo' => 3,
            'estado' => RecordStatus::ACTIVO,
        ]);

        $database->prerequisites()->attach(
            $programming2->id
        );

        AcademicHistory::create([
            'student_id' => $student->id,
            'course_id' => $programming2->id,
            'academic_period_id' => $period->id,
            'nota_final' => 8,
            'estado' => AcademicHistoryStatus::DESAPROBADO,
        ]);

        $useCase = app(GetAvailableCourses::class);

        $courses = $useCase->__invoke($student);

        $this->assertFalse(
            $courses->contains('id', $database->id)
        );
    }
}
