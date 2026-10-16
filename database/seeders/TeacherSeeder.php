<?php

namespace Database\Seeders;

use App\Models\Teacher;
use Illuminate\Database\Seeder;

class TeacherSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        Teacher::firstOrCreate(['codigo_institucional' => 'DOC-DEMO'], [
            'nombres' => 'Docente', 'apellidos' => 'Demostración', 'estado' => 'ACTIVO', 'max_horas_semanales' => '20.00',
        ]);
    }
}
