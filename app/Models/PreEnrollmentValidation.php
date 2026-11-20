<?php

namespace App\Models;

use App\Enums\AcademicValidationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['pre_enrollment_request_id', 'estado', 'cursos', 'evaluated_at', 'revision_id'])]
class PreEnrollmentValidation extends Model
{
    protected function casts(): array
    {
        return ['estado' => AcademicValidationStatus::class, 'cursos' => 'array', 'evaluated_at' => 'immutable_datetime'];
    }
}
