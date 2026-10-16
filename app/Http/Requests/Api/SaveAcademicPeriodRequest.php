<?php

namespace App\Http\Requests\Api;

use App\Enums\AcademicPeriodStatus;
use App\Models\AcademicPeriod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveAcademicPeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->isMethod('POST') ? $this->user()->can('create', AcademicPeriod::class)
            : $this->user()->can('update', $this->route('period'));
    }

    public function rules(): array
    {
        $presence = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'codigo' => [$presence, 'required', 'string', 'max:20', 'regex:/\\A[A-Za-z0-9][A-Za-z0-9._-]*\\z/'],
            'fecha_inicio' => [$presence, 'required', 'date_format:Y-m-d'],
            'fecha_fin' => [$presence, 'required', 'date_format:Y-m-d'],
            'estado' => [$presence, 'required', Rule::enum(AcademicPeriodStatus::class)],
            'created_by' => ['prohibited'], 'updated_by' => ['prohibited'],
            'fecha_inicio_prematricula' => ['prohibited'], 'fecha_fin_prematricula' => ['prohibited'],
        ];
    }
}
