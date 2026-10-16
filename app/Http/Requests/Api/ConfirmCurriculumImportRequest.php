<?php

namespace App\Http\Requests\Api;

use Illuminate\Contracts\Validation\ValidationRule;

class ConfirmCurriculumImportRequest extends CurriculumFileRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return parent::authorize();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return parent::rules() + [
            'sha256' => ['required', 'string', 'regex:/\A[a-f0-9]{64}\z/'],
            'catalog_fingerprint' => ['required', 'string', 'regex:/\A[a-f0-9]{64}\z/'],
        ];
    }
}
