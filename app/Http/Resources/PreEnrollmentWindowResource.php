<?php

namespace App\Http\Resources;

use App\Models\PreEnrollmentWindow;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PreEnrollmentWindow */
class PreEnrollmentWindowResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'academic_period_id' => $this->academic_period_id,
            'tipo' => $this->tipo->value, 'fecha_inicio' => $this->fecha_inicio->toISOString(),
            'fecha_fin' => $this->fecha_fin->toISOString(), 'timezone' => $this->timezone,
            'motivo' => $this->motivo, 'created_by' => $this->created_by, 'origen' => $this->origen,
            'created_at' => $this->created_at?->toISOString(), 'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
