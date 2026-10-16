<?php

namespace App\Http\Requests\Api;

use App\Enums\RecordStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAccountRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('account')) ?? false;
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
                'sometimes',
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'correo_institucional')->ignore($this->route('account')),
                'unique:students,codigo_universitario',
            ],
            'password' => [
                'sometimes',
                'required',
                'string',
                'min:8',
                'max:1024',
                Rule::when(config('hashing.driver') === 'bcrypt', ['regex:/\A[^\x00]{1,72}\z/']),
            ],
            'estado' => ['sometimes', 'required', Rule::enum(RecordStatus::class)],
            'rol' => ['prohibited'],
            'cargo' => ['prohibited'],
            'estudiante' => ['prohibited'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['password.regex' => 'La contraseña admite hasta 72 bytes y no puede contener bytes nulos.'];
    }
}
