<?php

namespace App\Http\Resources;

use App\Models\PreEnrollmentRevision;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PreEnrollmentRevision */
class PreEnrollmentRevisionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'revision_number' => $this->revision_number,
            'window_id' => $this->window_id, 'submitted_at' => $this->submitted_at?->toISOString(),
            'receipt_code' => $this->receipt_code, 'receipt_available' => $this->pdf_sha256 !== null,
            'pdf_sha256' => $this->pdf_sha256, 'schema_version' => $this->schema_version,
            'motivo' => $this->motivo, 'snapshot' => $this->snapshot,
        ];
    }
}
