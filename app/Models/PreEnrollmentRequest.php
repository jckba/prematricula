<?php

namespace App\Models;

use App\Enums\PreEnrollmentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['student_id', 'academic_period_id', 'estado', 'fecha_envio', 'fecha_revision', 'reviewed_by', 'comentarios_coordinador', 'correction_window_id', 'current_revision_id'])]
class PreEnrollmentRequest extends Model
{
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

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function academicValidation(): HasOne
    {
        return $this->hasOne(PreEnrollmentValidation::class);
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(PreEnrollmentRevision::class);
    }

    public function currentRevision(): BelongsTo
    {
        return $this->belongsTo(PreEnrollmentRevision::class, 'current_revision_id');
    }

    public function academicPeriod()
    {
        return $this->belongsTo(AcademicPeriod::class);
    }

    public function details()
    {
        return $this->hasMany(PreEnrollmentDetail::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
