<?php

namespace App\Http\Requests\Api;

use App\Enums\AcademicValidationStatus;
use App\Enums\SchedulePreference;
use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AcademicDemandRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->rol === UserRole::COORDINADOR;
    }

    public function rules(): array
    {
        return [
            'q' => ['sometimes', 'string', 'min:2', 'max:150'],
            'ciclo' => ['sometimes', 'integer', 'min:1', 'max:32767'],
            'course_id' => ['sometimes', 'integer', 'min:1', 'exists:courses,id'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'sort' => ['sometimes', Rule::in(['course_id', 'codigo', 'nombre', 'ciclo', 'creditos', 'registrada', 'valida', 'pendiente_resultados', 'requiere_ajuste'])],
            'direction' => ['sometimes', Rule::in(['asc', 'desc'])],
            'estado_validacion' => ['sometimes', Rule::in([...array_column(AcademicValidationStatus::cases(), 'value'), 'SIN_VALIDACION'])],
            'preferencia_turno' => ['sometimes', Rule::enum(SchedulePreference::class)],
        ];
    }
}
