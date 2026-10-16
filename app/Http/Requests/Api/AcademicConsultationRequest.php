<?php

namespace App\Http\Requests\Api;

use App\Enums\RecordStatus;
use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AcademicConsultationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->rol === UserRole::COORDINADOR;
    }

    public function rules(): array
    {
        return [
            'codigo_universitario' => ['sometimes', 'string', 'max:30'],
            'q' => ['sometimes', 'string', 'min:2', 'max:150'],
            'estado' => ['sometimes', Rule::enum(RecordStatus::class)],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'sort' => ['sometimes', Rule::in(['codigo_universitario', 'nombres', 'apellidos'])],
            'direction' => ['sometimes', Rule::in(['asc', 'desc'])],
            'academic_period_id' => [$this->route('student') === null ? 'prohibited' : 'required', 'integer', 'exists:academic_periods,id'],
        ];
    }
}
