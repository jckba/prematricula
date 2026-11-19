<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'codigo_universitario' => [
                'required',
                'string',
                'digits:10',
                'unique:students,codigo_universitario',
            ],
            'nombres' => [
                'required',
                'string',
                'max:100',
            ],
            'apellidos' => [
                'required',
                'string',
                'max:150',
            ],
            'correo_institucional' => [
                'required',
                'string',
                'email',
                'max:255',
                'regex:/^[A-Za-z0-9._%+-]+@unitru\.edu\.pe$/i',
                'unique:users,correo_institucional',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'codigo_universitario.required' => 'El código universitario es obligatorio.',
            'codigo_universitario.digits' => 'El código universitario debe tener exactamente 10 dígitos.',
            'codigo_universitario.unique' => 'Este código universitario ya está registrado.',

            'nombres.required' => 'Los nombres son obligatorios.',
            'apellidos.required' => 'Los apellidos son obligatorios.',

            'correo_institucional.required' => 'El correo institucional es obligatorio.',
            'correo_institucional.email' => 'Ingresa un correo electrónico válido.',
            'correo_institucional.regex' => 'Debes utilizar un correo con dominio @unitru.edu.pe.',
            'correo_institucional.unique' => 'Este correo institucional ya está registrado.',
        ];
    }
}
