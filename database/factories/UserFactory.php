<?php

namespace Database\Factories;

use App\Enums\CoordinatorPosition;
use App\Enums\RecordStatus;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'correo_institucional' => fake()->unique()->userName().'@unitru.edu.pe',
            'rol' => UserRole::ESTUDIANTE,
            'estado' => RecordStatus::ACTIVO,
            'cargo' => null,
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'estado' => RecordStatus::INACTIVO,
        ]);
    }

    public function coordinator(CoordinatorPosition $position = CoordinatorPosition::DIRECTOR_ESCUELA): static
    {
        return $this->state(fn (array $attributes): array => [
            'rol' => UserRole::COORDINADOR,
            'cargo' => $position,
        ]);
    }
}
