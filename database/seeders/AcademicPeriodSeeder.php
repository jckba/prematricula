<?php

namespace Database\Seeders;

use App\Enums\AcademicPeriodStatus;
use App\Models\AcademicPeriod;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AcademicPeriodSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        AcademicPeriod::create([
            'codigo' => '2026-II',
            'fecha_inicio' => '2026-08-24',
            'fecha_fin' => '2026-12-11',
            'estado' => AcademicPeriodStatus::CERRADO,
        ]);

        AcademicPeriod::create([
            'codigo' => '2027-I',
            'fecha_inicio' => '2027-04-12',
            'fecha_fin' => '2027-07-28',
            'fecha_inicio_prematricula' => now()->subDays(2),
            'fecha_fin_prematricula' => now()->addDays(10),
            'estado' => AcademicPeriodStatus::PREMATRICULA,
        ]);
    }
}
