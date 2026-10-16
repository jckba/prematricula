<?php

namespace App\Http\Resources;

use App\Models\PreEnrollmentRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PreEnrollmentRequest */
class PreEnrollmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'estado' => $this->estado->value,
            'correccion_en_progreso' => $this->correction_window_id !== null,
            'current_revision_id' => $this->current_revision_id,
            'revision_vigente' => new PreEnrollmentRevisionResource($this->whenLoaded('currentRevision')),
            'validacion_academica' => $this->whenLoaded('academicValidation', fn () => $this->academicValidation === null ? null : [
                'estado' => $this->academicValidation->estado->value,
                'revision_id' => $this->academicValidation->revision_id,
                'cursos' => $this->academicValidation->cursos,
                'evaluated_at' => $this->academicValidation->evaluated_at->toISOString(),
            ]),

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
