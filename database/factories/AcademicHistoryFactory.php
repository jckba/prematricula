<?php

namespace Database\Factories;

use App\Enums\AcademicHistoryStatus;
use App\Models\AcademicHistory;
use App\Models\AcademicPeriod;
use App\Models\Course;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AcademicHistory>
 */
class AcademicHistoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'course_id' => Course::factory(),
            'academic_period_id' => AcademicPeriod::factory()->closed(),
            'nota_final' => '15.50',
            'estado' => AcademicHistoryStatus::APROBADO,
        ];
    }
}
