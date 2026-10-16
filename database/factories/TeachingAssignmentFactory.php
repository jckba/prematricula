<?php

namespace Database\Factories;

use App\Models\AcademicPeriod;
use App\Models\Course;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TeachingAssignment> */
class TeachingAssignmentFactory extends Factory
{
    public function definition(): array
    {
        return ['academic_period_id' => AcademicPeriod::factory(), 'teacher_id' => Teacher::factory(),
            'course_id' => Course::factory(), 'horas_teoria_asignadas' => '1.00', 'horas_practica_asignadas' => '0.00',
            'horas_laboratorio_asignadas' => '0.00', 'created_by' => User::factory()->coordinator(),
            'updated_by' => fn (array $attributes): int => $attributes['created_by']];
    }
}
