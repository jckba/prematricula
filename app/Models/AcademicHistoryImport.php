<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['created_by', 'sha256', 'motivo', 'summary'])]
class AcademicHistoryImport extends Model
{
    protected function casts(): array
    {
        return ['summary' => 'array'];
    }

    /** @return HasMany<AcademicHistoryChange, $this> */
    public function changes(): HasMany
    {
        return $this->hasMany(AcademicHistoryChange::class);
    }
}
