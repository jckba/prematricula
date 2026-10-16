<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['academic_history_import_id', 'academic_history_id', 'before', 'after'])]
class AcademicHistoryChange extends Model
{
    protected function casts(): array
    {
        return ['before' => 'array', 'after' => 'array'];
    }
}
