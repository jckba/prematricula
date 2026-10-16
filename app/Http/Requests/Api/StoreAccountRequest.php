<?php

namespace App\Http\Requests\Api;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAccountRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', User::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'correo_institucional' => [
                'required',
                'string',
                'email',
                'max:255',
                'unique:users,correo_institucional',
                'unique:students,codigo_universitario',
            ],
            'password' => [
                'required',
                'string',
                'min:8',
                'max:1024',
                Rule::when(config('hashing.driver') === 'bcrypt', ['regex:/\A[^\x00]{1,72}\z/']),
            ],
            'rol' => ['prohibited'],
            'cargo' => ['prohibited'],
            'estado' => ['prohibited'],
            'estudiante' => ['required', 'array:codigo_universitario,nombres,apellidos,creditos_maximos'],
            'estudiante.codigo_universitario' => [
                'required',
                'string',
                'max:30',
                'unique:students,codigo_universitario',
                'unique:users,correo_institucional',
                'different:correo_institucional',
            ],
            'estudiante.nombres' => ['required', 'string', 'max:100'],
            'estudiante.apellidos' => ['required', 'string', 'max:150'],
            'estudiante.creditos_maximos' => ['sometimes', 'integer', 'min:1', 'max:32767'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['password.regex' => 'La contraseña admite hasta 72 bytes y no puede contener bytes nulos.'];
    }
}
