<?php

namespace App\Http\Requests\Api;

use App\Models\AcademicHistory;

class AcademicHistoryFileRequest extends CurriculumFileRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('import', AcademicHistory::class) ?? false;
    }
}
