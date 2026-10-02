<?php

namespace Database\Seeders;

use App\Enums\RecordStatus;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Seeder;

class StudentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = User::where(
            'correo_institucional',
            'estudiante@unitru.edu.pe'
        )->firstOrFail();

        Student::create([
            'user_id' => $user->id,
            'codigo_universitario' => '1452700722',
            'nombres' => 'Alumno',
            'apellidos' => 'Prueba',
            'creditos_maximos' => 22,
            'estado' => RecordStatus::ACTIVO,
        ]);

    }
}
