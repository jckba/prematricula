<?php

namespace App\Http\Requests\Api;

use App\Enums\SchedulePreference;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AddPreEnrollmentCourseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'course_id' => [
                'required',
                'integer',
                'exists:courses,id',
            ],

            'preferencia_turno' => [
                'nullable',
                Rule::enum(SchedulePreference::class),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'course_id.required' =>
                'Debe indicar el curso.',
            'course_id.exists' =>
                'El curso seleccionado no existe.',
        ];
    }
}
