<?php

namespace App\Http\Requests\Api;

use App\Models\Course;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CurriculumFileRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('manageCurriculum', Course::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'extensions:xlsx',
                'mimetypes:application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/zip,application/octet-stream',
                'max:'.config('curriculum.max_file_kilobytes'),
            ],
        ];
    }
}
