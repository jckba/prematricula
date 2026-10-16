<?php

namespace App\Http\Resources;

use App\Models\Teacher;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Teacher
 *
 * @property-read int $cantidad_cursos
 * @property-read string $horas_teoria_asignadas
 * @property-read string $horas_practica_asignadas
 * @property-read string $horas_laboratorio_asignadas
 * @property-read string $total_semanal
 * @property-read int $excede_limite
 * @property-read string|null $horas_disponibles
 */
class TeacherLoadResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['docente' => new TeacherResource($this->resource), 'cantidad_cursos' => (int) $this->cantidad_cursos,
            'horas_teoria_asignadas' => $this->horas_teoria_asignadas, 'horas_practica_asignadas' => $this->horas_practica_asignadas,
            'horas_laboratorio_asignadas' => $this->horas_laboratorio_asignadas, 'total_semanal' => $this->total_semanal,
            'excede_limite' => (int) $this->excede_limite === 1, 'horas_disponibles' => $this->horas_disponibles];
    }
}
