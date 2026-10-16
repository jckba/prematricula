<?php

namespace App\Http\Resources;

use App\Services\TeachingHours;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read int $course_id
 * @property-read string $codigo
 * @property-read string $nombre
 * @property-read int $ciclo
 * @property-read string $estado
 * @property-read int $cantidad_docentes
 * @property-read string $total_semanal
 * @property-read string $horas_teoria
 * @property-read string $horas_teoria_asignadas
 * @property-read string $horas_teoria_pendientes
 * @property-read string $horas_practica
 * @property-read string $horas_practica_asignadas
 * @property-read string $horas_practica_pendientes
 * @property-read string $horas_laboratorio
 * @property-read string $horas_laboratorio_asignadas
 * @property-read string $horas_laboratorio_pendientes
 * @property-read int $demanda_registrada
 * @property-read int $demanda_valida
 * @property-read int $demanda_pendiente_resultados
 * @property-read int $demanda_requiere_ajuste
 * @property-read int $demanda_sin_validacion
 */
class CourseTeachingLoadResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $result = ['course_id' => $this->course_id, 'codigo' => $this->codigo, 'nombre' => $this->nombre,
            'ciclo' => $this->ciclo, 'estado' => $this->estado, 'cantidad_docentes' => $this->cantidad_docentes,
            'total_semanal' => $this->total_semanal];
        foreach (TeachingHours::FIELDS as $assigned => $available) {
            $result[$assigned] = $this->$assigned;
            $result[$available] = $this->$available;
            $result[$available.'_pendientes'] = $this->{$available.'_pendientes'};
        }
        foreach (['registrada', 'valida', 'pendiente_resultados', 'requiere_ajuste', 'sin_validacion'] as $field) {
            $result['demanda_'.$field] = $this->{'demanda_'.$field};
        }

        return $result;
    }
}
