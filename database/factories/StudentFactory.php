<?php

namespace Database\Factories;

use App\Enums\RecordStatus;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Student>
 */
class StudentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'codigo_universitario' => fake()->unique()->numerify('00########'),
            'nombres' => fake()->firstName(),
            'apellidos' => fake()->lastName(),
            'creditos_maximos' => 22,
            'estado' => RecordStatus::ACTIVO,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => ['estado' => RecordStatus::INACTIVO]);
    }
}
