<?php

namespace App\Http\Resources;

use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Student */
class StudentAcademicResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'codigo_universitario' => $this->codigo_universitario,
            'nombres' => $this->nombres, 'apellidos' => $this->apellidos,
            'estado' => $this->estado->value, 'creditos_maximos' => $this->creditos_maximos,
        ];
    }
}
