<?php

namespace App\Http\Resources;

use App\Models\TeachingAssignment;
use App\Services\TeachingHours;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TeachingAssignment */
class TeachingAssignmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'academic_period_id' => $this->academic_period_id,
            'teacher_id' => $this->teacher_id, 'course_id' => $this->course_id,
            'docente' => new TeacherResource($this->whenLoaded('teacher')),
            'curso' => $this->whenLoaded('course', fn (): array => ['id' => $this->course->id, 'codigo' => $this->course->codigo, 'nombre' => $this->course->nombre]),
            'horas_teoria_asignadas' => $this->horas_teoria_asignadas, 'horas_practica_asignadas' => $this->horas_practica_asignadas,
            'horas_laboratorio_asignadas' => $this->horas_laboratorio_asignadas,
            'total_semanal' => TeachingHours::decimal(TeachingHours::total($this->resource->getAttributes())),
            'created_by' => $this->created_by, 'updated_by' => $this->updated_by,
            'created_at' => $this->created_at?->toISOString(), 'updated_at' => $this->updated_at?->toISOString()];
    }
}
