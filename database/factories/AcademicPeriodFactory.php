<?php

namespace Database\Factories;

use App\Enums\AcademicPeriodStatus;
use App\Models\AcademicPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AcademicPeriod>
 */
class AcademicPeriodFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'codigo' => fake()->unique()->bothify('TEST-####-??'),
            'fecha_inicio' => now()->addMonth()->toDateString(),
            'fecha_fin' => now()->addMonths(5)->toDateString(),
            'fecha_inicio_prematricula' => now()->subDay(),
            'fecha_fin_prematricula' => now()->addDay(),
            'estado' => AcademicPeriodStatus::PREMATRICULA,
        ];
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes): array => ['estado' => AcademicPeriodStatus::CERRADO]);
    }
}
