<?php

namespace Database\Factories;

use App\Enums\PreEnrollmentStatus;
use App\Models\AcademicPeriod;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

class PreEnrollmentRequestFactory extends Factory
{
    public function definition(): array
    {
        return ['student_id' => Student::factory(), 'academic_period_id' => AcademicPeriod::factory(),
            'estado' => PreEnrollmentStatus::BORRADOR];
    }
}
