<?php

namespace Database\Seeders;

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
        User::create([
            'correo_institucional' => 'estudiante@unitru.edu.pe',
            'password' => 'password',
            'rol' => UserRole::ESTUDIANTE,
            'estado' => RecordStatus::ACTIVO,
        ]);

        User::create([
            'correo_institucional' => 'coordinador@unitru.edu.pe',
            'password' => 'password',
            'rol' => UserRole::COORDINADOR,
            'estado' => RecordStatus::ACTIVO,
        ]);
    }
}
