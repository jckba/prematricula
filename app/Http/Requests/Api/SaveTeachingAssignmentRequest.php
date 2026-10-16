<?php

namespace App\Http\Requests\Api;

use App\Enums\UserRole;
use App\Services\TeachingHours;
use Illuminate\Foundation\Http\FormRequest;

class SaveTeachingAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->rol === UserRole::COORDINADOR;
    }

    public function rules(): array
    {
        $rules = [];
        foreach (['academic_period_id' => 'academic_periods', 'teacher_id' => 'teachers', 'course_id' => 'courses'] as $field => $table) {
            $rules[$field] = $this->isMethod('POST') ? ['required', 'integer', 'exists:'.$table.',id'] : ['prohibited'];
        }
        foreach (array_keys(TeachingHours::FIELDS) as $field) {
            $rules[$field] = ['sometimes', ...TeachingHours::validationRules()];
        }

        return $rules + ['created_by' => ['prohibited'], 'updated_by' => ['prohibited'], 'deleted_at' => ['prohibited']];
    }
}
