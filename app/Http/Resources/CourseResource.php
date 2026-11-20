<?php

namespace App\Http\Resources;

use App\Models\Course;
use App\Services\CurriculumCatalog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Course */
class CourseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            'nombre' => $this->nombre,
            'creditos' => $this->creditos,
            'ciclo' => $this->ciclo,
            'horas_teoria' => $this->horas_teoria,
            'horas_practica' => $this->horas_practica,
            'horas_laboratorio' => $this->horas_laboratorio,
            'estado' => $this->estado->value,
            // Solo cuando se cargaron los conteos (detalle para coordinación): true si sus datos académicos son inmutables.
            'en_uso' => $this->when(
                $this->resource->academic_histories_count !== null,
                fn (): bool => app(CurriculumCatalog::class)->isProtected($this->resource),
            ),

            'prerrequisitos' => $this->whenLoaded(
                'prerequisites',
                fn () => $this->prerequisites
                    ->map(fn ($course) => [
                        'id' => $course->id,
                        'codigo' => $course->codigo,
                        'nombre' => $course->nombre,
                    ])
                    ->values()
            ),
        ];
    }
}
