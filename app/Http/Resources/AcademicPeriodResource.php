<?php

namespace App\Http\Resources;

use App\Models\AcademicPeriod;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AcademicPeriod */
class AcademicPeriodResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'codigo' => $this->codigo, 'estado' => $this->estado->value,
            'fecha_inicio' => $this->fecha_inicio->toDateString(), 'fecha_fin' => $this->fecha_fin->toDateString(),
            'timezone' => config('academic.timezone'), 'created_by' => $this->created_by, 'updated_by' => $this->updated_by,
            'created_at' => $this->created_at?->toISOString(), 'updated_at' => $this->updated_at?->toISOString(),
            'ventanas' => PreEnrollmentWindowResource::collection($this->whenLoaded('windows')),
        ];
    }
}
