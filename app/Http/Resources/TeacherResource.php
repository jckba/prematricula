<?php

namespace App\Http\Resources;

use App\Models\Teacher;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Teacher */
class TeacherResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'codigo_institucional' => $this->codigo_institucional,
            'nombres' => $this->nombres, 'apellidos' => $this->apellidos, 'correo_institucional' => $this->correo_institucional,
            'estado' => $this->estado->value, 'max_horas_semanales' => $this->max_horas_semanales,
            'created_at' => $this->created_at?->toISOString(), 'updated_at' => $this->updated_at?->toISOString()];
    }
}
