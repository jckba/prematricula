<?php

namespace App\Models;

use App\Enums\PreEnrollmentStatus;
use Database\Factories\PreEnrollmentRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['student_id', 'academic_period_id', 'estado', 'fecha_envio', 'fecha_revision', 'reviewed_by', 'comentarios_coordinador', 'correction_window_id', 'current_revision_id'])]
class PreEnrollmentRequest extends Model
{
    /** @use HasFactory<PreEnrollmentRequestFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'fecha_envio' => 'datetime',
            'fecha_revision' => 'datetime',
            'estado' => PreEnrollmentStatus::class,
            'current_revision_id' => 'integer',
            'correction_window_id' => 'integer',
        ];
    }

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** @return HasOne<PreEnrollmentValidation, $this> */
    public function academicValidation(): HasOne
    {
        return $this->hasOne(PreEnrollmentValidation::class);
    }

    /** @return HasMany<PreEnrollmentRevision, $this> */
    public function revisions(): HasMany
    {
        return $this->hasMany(PreEnrollmentRevision::class);
    }

    /** @return BelongsTo<PreEnrollmentRevision, $this> */
    public function currentRevision(): BelongsTo
    {
        return $this->belongsTo(PreEnrollmentRevision::class, 'current_revision_id');
    }

    /** @return BelongsTo<AcademicPeriod, $this> */
    public function academicPeriod(): BelongsTo
    {
        return $this->belongsTo(AcademicPeriod::class);
    }

    /** @return HasMany<PreEnrollmentDetail, $this> */
    public function details(): HasMany
    {
        return $this->hasMany(PreEnrollmentDetail::class);
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
