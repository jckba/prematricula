<?php

namespace App\Http\Requests\Api;

use App\Enums\RecordStatus;
use App\Services\CurriculumValidator;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCourseRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('course')) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = CurriculumValidator::academicRules();
        unset($rules['codigo']);

        foreach ($rules as $field => $fieldRules) {
            if (! str_contains($field, '*')) {
                $rules[$field] = ['sometimes', ...array_values(array_diff($fieldRules, ['present']))];
            }
        }

        return $rules + [
            'codigo' => ['prohibited'],
            'estado' => ['sometimes', 'required', Rule::enum(RecordStatus::class)],
        ];
    }
}
