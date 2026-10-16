<?php

namespace App\Http\Requests\Api;

class ConfirmAcademicHistoryImportRequest extends AcademicHistoryFileRequest
{
    public function rules(): array
    {
        return parent::rules() + [
            'sha256' => ['required', 'string', 'regex:/\\A[a-f0-9]{64}\\z/'],
            'history_fingerprint' => ['required', 'string', 'regex:/\\A[a-f0-9]{64}\\z/'],
            'motivo' => ['required', 'string', 'max:2000'],
        ];
    }
}
