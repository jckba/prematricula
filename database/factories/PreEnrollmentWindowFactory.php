<?php

namespace Database\Factories;

use App\Enums\PreEnrollmentWindowType;
use App\Models\AcademicPeriod;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PreEnrollmentWindowFactory extends Factory
{
    public function definition(): array
    {
        return ['academic_period_id' => AcademicPeriod::factory()->state([
            'fecha_inicio_prematricula' => null, 'fecha_fin_prematricula' => null,
        ]), 'tipo' => PreEnrollmentWindowType::ORDINARIA, 'fecha_inicio' => now()->subDay(), 'fecha_fin' => now()->addDay(),
            'motivo' => 'Ventana de prueba', 'timezone' => 'America/Lima', 'created_by' => User::factory()->coordinator()];
    }
}
