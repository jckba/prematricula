<?php

namespace Database\Factories;

use App\Enums\RecordStatus;
use App\Models\Teacher;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Teacher> */
class TeacherFactory extends Factory
{
    public function definition(): array
    {
        return ['codigo_institucional' => fake()->unique()->bothify('DOC-####??'), 'nombres' => fake()->firstName(),
            'apellidos' => fake()->lastName(), 'correo_institucional' => null,
            'estado' => RecordStatus::ACTIVO, 'max_horas_semanales' => null];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['estado' => RecordStatus::INACTIVO]);
    }
}
