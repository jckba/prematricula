<?php

namespace Database\Seeders;


use App\Enums\RecordStatus;
use App\Models\Course;
use Illuminate\Database\Seeder;

class CourseSeeder extends Seeder
{
    public function run(): void
    {
        $programacion1 = Course::create([
            'codigo' => 'INF101',
            'nombre' => 'Programación I',
            'creditos' => 4,
            'ciclo' => 1,
            'estado' => RecordStatus::ACTIVO,
        ]);

        $matematica1 = Course::create([
            'codigo' => 'MAT101',
            'nombre' => 'Matemática I',
            'creditos' => 4,
            'ciclo' => 1,
            'estado' => RecordStatus::ACTIVO,
        ]);

        $programacion2 = Course::create([
            'codigo' => 'INF201',
            'nombre' => 'Programación II',
            'creditos' => 5,
            'ciclo' => 2,
            'estado' => RecordStatus::ACTIVO,
        ]);

        $baseDatos = Course::create([
            'codigo' => 'INF301',
            'nombre' => 'Base de Datos',
            'creditos' => 4,
            'ciclo' => 3,
            'estado' => RecordStatus::ACTIVO,
        ]);

        $algoritmos = Course::create([
            'codigo' => 'INF302',
            'nombre' => 'Algoritmos y Estructuras de Datos',
            'creditos' => 5,
            'ciclo' => 3,
            'estado' => RecordStatus::ACTIVO,
        ]);

        $programacion2->prerequisites()->attach($programacion1->id);

        $baseDatos->prerequisites()->attach($programacion2->id);

        $algoritmos->prerequisites()->attach($programacion2->id);
    }
}
