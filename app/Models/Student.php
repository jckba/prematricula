<?php

namespace App\Models;

use App\Enums\RecordStatus;
use Database\Factories\StudentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'codigo_universitario', 'nombres', 'apellidos', 'creditos_maximos', 'estado'])]
class Student extends Model
{
    /** @use HasFactory<StudentFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'codigo_universitario' => 'string',
            'creditos_maximos' => 'integer',
            'estado' => RecordStatus::class,
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<AcademicHistory, $this> */
    public function academicHistories(): HasMany
    {
        return $this->hasMany(AcademicHistory::class);
    }

    /** @return HasMany<PreEnrollmentRequest, $this> */
    public function preEnrollmentRequests(): HasMany
    {
        return $this->hasMany(PreEnrollmentRequest::class);
    }
}
