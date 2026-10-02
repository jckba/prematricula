<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PreEnrollmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'estado' => $this->estado->value,

            'fecha_envio' => $this->fecha_envio?->toISOString(),

            'fecha_revision' => $this->fecha_revision?->toISOString(),

            'comentarios_coordinador' => $this->comentarios_coordinador,

            'periodo' => [
                'id' => $this->academicPeriod->id,
                'codigo' => $this->academicPeriod->codigo,
            ],

            'estudiante' => [
                'id' => $this->student->id,
                'codigo_universitario' => $this->student->codigo_universitario,
                'nombres' => $this->student->nombres,
                'apellidos' => $this->student->apellidos,
            ],

            'detalles' => PreEnrollmentDetailResource::collection(
                $this->whenLoaded('details')
            ),

            'creditos_totales' => $this->whenLoaded(
                'details',
                fn () => $this->details
                    ->sum(fn ($detail) => $detail->course->creditos
                    )
            ),

            'revisado_por' => $this->whenLoaded(
                'reviewer',
                fn () => $this->reviewer
                    ? [
                        'id' => $this->reviewer->id,
                        'correo_institucional' => $this->reviewer->correo_institucional,
                    ]
                    : null
            ),
        ];
    }
}
