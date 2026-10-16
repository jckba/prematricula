<?php

namespace App\Http\Resources;

use App\Models\TeachingAssignmentChange;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TeachingAssignmentChange */
class TeachingAssignmentChangeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'teaching_assignment_id' => $this->teaching_assignment_id,
            'actor_id' => $this->actor_id, 'accion' => $this->accion, 'before' => $this->before, 'after' => $this->after,
            'created_at' => $this->created_at->toISOString()];
    }
}
