<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AcademicHistoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'student_id' => $this->student_id,
            'codigo_universitario' => $this->whenLoaded('student', fn () => $this->student->codigo_universitario),
            'curso' => $this->whenLoaded('course', fn () => ['id' => $this->course->id, 'codigo' => $this->course->codigo, 'nombre' => $this->course->nombre]),
            'periodo' => $this->whenLoaded('academicPeriod', fn () => ['id' => $this->academicPeriod->id, 'codigo' => $this->academicPeriod->codigo]),
            'nota_final' => $this->nota_final, 'estado' => $this->estado->value,
            'created_at' => $this->created_at?->toISOString(), 'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
