<?php

namespace Database\Seeders;

use App\Enums\RecordStatus;
use App\Models\Course;
use Illuminate\Database\Seeder;

class CourseSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $programacion1 = Course::firstOrCreate(['codigo' => 'INF101'], [
            'nombre' => 'Programación I',
            'creditos' => 4,
            'ciclo' => 1,
            'estado' => RecordStatus::ACTIVO,
            'horas_teoria' => '2.00', 'horas_practica' => '2.00', 'horas_laboratorio' => '0.00',
        ]);

        $matematica1 = Course::firstOrCreate(['codigo' => 'MAT101'], [
            'nombre' => 'Matemática I',
            'creditos' => 4,
            'ciclo' => 1,
            'estado' => RecordStatus::ACTIVO,
            'horas_teoria' => '2.00', 'horas_practica' => '2.00', 'horas_laboratorio' => '0.00',
        ]);

        $programacion2 = Course::firstOrCreate(['codigo' => 'INF201'], [
            'nombre' => 'Programación II',
            'creditos' => 5,
            'ciclo' => 2,
            'estado' => RecordStatus::ACTIVO,
            'horas_teoria' => '2.00', 'horas_practica' => '2.00', 'horas_laboratorio' => '0.00',
        ]);

        $baseDatos = Course::firstOrCreate(['codigo' => 'INF301'], [
            'nombre' => 'Base de Datos',
            'creditos' => 4,
            'ciclo' => 3,
            'estado' => RecordStatus::ACTIVO,
            'horas_teoria' => '2.00', 'horas_practica' => '2.00', 'horas_laboratorio' => '0.00',
        ]);

        $algoritmos = Course::firstOrCreate(['codigo' => 'INF302'], [
            'nombre' => 'Algoritmos y Estructuras de Datos',
            'creditos' => 5,
            'ciclo' => 3,
            'estado' => RecordStatus::ACTIVO,
            'horas_teoria' => '2.00', 'horas_practica' => '2.00', 'horas_laboratorio' => '0.00',
        ]);

        $programacion2->prerequisites()->syncWithoutDetaching([$programacion1->id]);

        $baseDatos->prerequisites()->syncWithoutDetaching([$programacion2->id]);

        $algoritmos->prerequisites()->syncWithoutDetaching([$programacion2->id]);
    }
}
