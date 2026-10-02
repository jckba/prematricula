<?php

namespace App\Http\Requests\Api;

use App\Enums\PreEnrollmentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListPreEnrollmentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'estado' => [
                'nullable',
                Rule::enum(PreEnrollmentStatus::class),
            ],
        ];
    }
}
