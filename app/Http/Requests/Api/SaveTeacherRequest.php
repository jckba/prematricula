<?php

namespace App\Http\Requests\Api;

use App\Enums\RecordStatus;
use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveTeacherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->rol === UserRole::COORDINADOR;
    }

    public function rules(): array
    {
        $presence = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'codigo_institucional' => [$presence, 'required', 'string', 'max:30', Rule::unique('teachers')->ignore($this->route('teacher'))],
            'nombres' => [$presence, 'required', 'string', 'max:100'],
            'apellidos' => [$presence, 'required', 'string', 'max:150'],
            'correo_institucional' => ['sometimes', 'nullable', 'email', 'max:255'],
            'estado' => ['sometimes', 'required', Rule::enum(RecordStatus::class)],
            'max_horas_semanales' => ['sometimes', 'nullable', 'numeric', 'decimal:0,2', 'between:0,99999.99'],
            'user_id' => ['prohibited'], 'password' => ['prohibited'], 'rol' => ['prohibited'],
        ];
    }
}
