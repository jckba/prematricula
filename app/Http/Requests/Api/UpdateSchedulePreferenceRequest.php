<?php

namespace App\Http\Requests\Api;

use App\Enums\SchedulePreference;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSchedulePreferenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'preferencia_turno' => [
                'required',
                Rule::enum(SchedulePreference::class),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'preferencia_turno.required' =>
                'Debe indicar una preferencia de turno.',
        ];
    }

}
