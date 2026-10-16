<?php

namespace App\Http\Resources;

use App\Models\AcademicHistoryImport;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AcademicHistoryImport */
class AcademicHistoryImportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'created_by' => $this->created_by, 'sha256' => $this->sha256,
            'motivo' => $this->motivo, 'summary' => $this->summary,
            'created_at' => $this->created_at?->toISOString(),
            'changes' => $this->whenLoaded('changes', fn () => $this->changes->map(fn ($change): array => [
                'id' => $change->id, 'academic_history_id' => $change->academic_history_id,
                'before' => $change->before, 'after' => $change->after, 'created_at' => $change->created_at?->toISOString(),
            ])->all()),
        ];
    }
}
