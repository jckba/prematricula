<?php

namespace App\Http\Requests\Api;

use App\Enums\RecordStatus;
use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TeachingQueryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->rol === UserRole::COORDINADOR;
    }

    public function rules(): array
    {
        return [
            'academic_period_id' => ['sometimes', 'integer', 'exists:academic_periods,id'],
            'teacher_id' => ['sometimes', 'integer', 'exists:teachers,id'],
            'course_id' => ['sometimes', 'integer', 'exists:courses,id'],
            'q' => ['sometimes', 'string', 'min:2', 'max:150'],
            'estado' => ['sometimes', Rule::enum(RecordStatus::class)],
            'ciclo' => ['sometimes', 'integer', 'between:1,32767'],
            'excede_limite' => ['sometimes', 'boolean'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
        ];
    }
}
