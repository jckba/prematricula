<?php

namespace Database\Seeders;

use App\Enums\AcademicHistoryStatus;
use App\Models\AcademicHistory;
use App\Models\AcademicPeriod;
use App\Models\Course;
use App\Models\Student;
use Illuminate\Database\Seeder;

class AcademicHistorySeeder extends Seeder
{
    public function run(): void
    {
        $student = Student::where(
            'codigo_universitario',
            '1452700722'
        )->firstOrFail();

        $period2026I = AcademicPeriod::where(
            'codigo',
            '2026-I'
        )->firstOrFail();

        $period2026II = AcademicPeriod::where(
            'codigo',
            '2026-II'
        )->firstOrFail();

        $programacion1 = Course::where(
            'codigo',
            'INF101'
        )->firstOrFail();

        $programacion2 = Course::where(
            'codigo',
            'INF201'
        )->firstOrFail();

        AcademicHistory::create([
            'student_id' => $student->id,
            'course_id' => $programacion1->id,
            'academic_period_id' => $period2026I->id,
            'nota_final' => 15,
            'estado' => AcademicHistoryStatus::APROBADO,
        ]);

        AcademicHistory::create([
            'student_id' => $student->id,
            'course_id' => $programacion2->id,
            'academic_period_id' => $period2026II->id,
            'nota_final' => null,
            'estado' => AcademicHistoryStatus::EN_CURSO,
        ]);
    }
}
