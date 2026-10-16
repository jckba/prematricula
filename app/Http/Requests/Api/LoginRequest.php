<?php

namespace App\Http\Requests\Api;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'identifier' => [
                'required_without:correo_institucional',
                'string',
                'max:255',
                'prohibits:correo_institucional',
            ],
            'correo_institucional' => [
                'required_without:identifier',
                'string',
                'email',
                'max:255',
                'prohibits:identifier',
            ],
            'password' => [
                'required',
                'string',
                'max:1024',
            ],
        ];
    }
}
