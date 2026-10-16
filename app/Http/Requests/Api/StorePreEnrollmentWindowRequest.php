<?php

namespace App\Http\Requests\Api;

use App\Enums\PreEnrollmentWindowType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePreEnrollmentWindowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('period'));
    }

    public function rules(): array
    {
        return [
            'tipo' => ['required', Rule::enum(PreEnrollmentWindowType::class)],
            'fecha_inicio' => ['required', 'date_format:Y-m-d H:i:s'],
            'fecha_fin' => ['required', 'date_format:Y-m-d H:i:s', 'after:fecha_inicio'],
            'motivo' => ['required', 'string', 'max:2000'],
            'created_by' => ['prohibited'], 'academic_period_id' => ['prohibited'],
            'student_id' => ['prohibited'], 'user_id' => ['prohibited'],
        ];
    }
}
