<?php

namespace App\Http\Requests\Api;

use App\Enums\RecordStatus;
use App\Enums\UserRole;
use App\Models\Course;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListCoursesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return ($this->user()?->can('viewAny', Course::class) ?? false)
            && ! ($this->user()->rol === UserRole::ESTUDIANTE && $this->input('estado') === RecordStatus::INACTIVO->value);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'q' => ['sometimes', 'nullable', 'string', 'max:100'],
            'ciclo' => ['sometimes', 'required', 'integer', 'between:1,32767'],
            'estado' => ['sometimes', 'required', Rule::enum(RecordStatus::class)],
            'per_page' => ['sometimes', 'required', 'integer', 'between:1,100'],
        ];
    }
}
