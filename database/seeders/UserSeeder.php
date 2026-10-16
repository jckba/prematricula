<?php

namespace Database\Seeders;

use App\Enums\CoordinatorPosition;
use App\Enums\RecordStatus;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        User::firstOrCreate(['correo_institucional' => 'estudiante@unitru.edu.pe'], [
            'password' => 'password',
            'rol' => UserRole::ESTUDIANTE,
            'estado' => RecordStatus::ACTIVO,
        ]);

        User::firstOrCreate(['correo_institucional' => 'coordinador@unitru.edu.pe'], [
            'password' => 'password',
            'rol' => UserRole::COORDINADOR,
            'cargo' => CoordinatorPosition::DIRECTOR_ESCUELA,
            'estado' => RecordStatus::ACTIVO,
        ]);

        User::firstOrCreate(['correo_institucional' => 'secretaria.demo@unitru.edu.pe'], [
            'password' => 'password',
            'rol' => UserRole::COORDINADOR,
            'cargo' => CoordinatorPosition::SECRETARIA,
            'estado' => RecordStatus::ACTIVO,
        ]);
    }
}
