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

    /** @return BelongsToMany<Course, $this> */
    public function prerequisites(): BelongsToMany
    {
        return $this->belongsToMany(
            Course::class,
            'course_prerequisites',
            'course_id',
            'prerequisite_id'
        );
    }

    /** @return BelongsToMany<Course, $this> */
    public function prerequisiteFor(): BelongsToMany
    {
        return $this->belongsToMany(
            Course::class,
            'course_prerequisites',
            'prerequisite_id',
            'course_id'
        );
    }

    /** @return HasMany<AcademicHistory, $this> */
    public function academicHistories(): HasMany
    {
        return $this->hasMany(AcademicHistory::class);
    }

    /** @return HasMany<TeachingAssignment, $this> */
    public function teachingAssignments(): HasMany
    {
        return $this->hasMany(TeachingAssignment::class);
    }

    /** @return BelongsToMany<PreEnrollmentRevision, $this> */
    public function preEnrollmentRevisions(): BelongsToMany
    {
        return $this->belongsToMany(PreEnrollmentRevision::class, 'pre_enrollment_revision_courses', 'course_id', 'revision_id');
    }

    /** @return HasMany<PreEnrollmentDetail, $this> */
    public function preEnrollmentDetails(): HasMany
    {
        return $this->hasMany(PreEnrollmentDetail::class);
    }
}
