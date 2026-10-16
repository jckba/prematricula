<?php

namespace Database\Factories;

use App\Enums\RecordStatus;
use App\Models\Course;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Course>
 */
class CourseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'codigo' => fake()->unique()->bothify('CUR###??'),
            'nombre' => fake()->words(3, true),
            'creditos' => 4,
            'ciclo' => 1,
            'horas_teoria' => '2.00',
            'horas_practica' => '2.00',
            'horas_laboratorio' => '0.00',
            'estado' => RecordStatus::ACTIVO,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => ['estado' => RecordStatus::INACTIVO]);
    }
}
