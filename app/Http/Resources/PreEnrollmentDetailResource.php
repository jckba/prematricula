<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PreEnrollmentDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'curso' => new CourseResource(
                $this->whenLoaded('course')
            ),

            'preferencia_turno' => $this->preferencia_turno->value,
        ];
    }
}
