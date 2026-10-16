<?php

namespace Database\Factories;

use App\Models\PreEnrollmentRequest;
use App\Models\PreEnrollmentRevision;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<PreEnrollmentRevision> */
class PreEnrollmentRevisionFactory extends Factory
{
    public function definition(): array
    {
        $receiptCode = 'PRE-'.Str::uuid();

        return ['pre_enrollment_request_id' => PreEnrollmentRequest::factory(),
            'motivo' => 'ENVIO', 'schema_version' => 2, 'revision_number' => 1,
            'receipt_code' => $receiptCode, 'submitted_at' => now(),
            'pdf_base64' => base64_encode('%PDF-1.4 test fixture'), 'pdf_sha256' => hash('sha256', '%PDF-1.4 test fixture'),
            'snapshot' => ['institution' => [], 'estudiante' => [], 'periodo' => [], 'detalles' => [],
                'revision_number' => 1, 'fecha_envio' => now()->toISOString(), 'receipt_code' => $receiptCode]];
    }
}
