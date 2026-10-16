<?php

namespace App\Models;

use App\Enums\RecordStatus;
use Database\Factories\CourseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['codigo', 'nombre', 'creditos', 'ciclo', 'horas_teoria', 'horas_practica', 'horas_laboratorio', 'estado'])]
class Course extends Model
{
    /** @use HasFactory<CourseFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'creditos' => 'integer',
            'ciclo' => 'integer',
            'horas_teoria' => 'decimal:2',
            'horas_practica' => 'decimal:2',
            'horas_laboratorio' => 'decimal:2',
            'estado' => RecordStatus::class,
        ];
    }

    public function prerequisites(): BelongsToMany
    {
        return $this->belongsToMany(
            Course::class,
            'course_prerequisites',
            'course_id',
            'prerequisite_id'
        );
    }

    public function prerequisiteFor(): BelongsToMany
    {
        return $this->belongsToMany(
            Course::class,
            'course_prerequisites',
            'prerequisite_id',
            'course_id'
        );
    }

    public function academicHistories(): HasMany
    {
        return $this->hasMany(AcademicHistory::class);
    }

    public function preEnrollmentRevisions(): BelongsToMany
    {
        return $this->belongsToMany(PreEnrollmentRevision::class, 'pre_enrollment_revision_courses', 'course_id', 'revision_id');
    }

    public function preEnrollmentDetails(): HasMany
    {
        return $this->hasMany(PreEnrollmentDetail::class);
    }
}
