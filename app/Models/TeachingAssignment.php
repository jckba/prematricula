<?php

namespace App\Models;

use Database\Factories\TeachingAssignmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['academic_period_id', 'course_id', 'teacher_id', 'horas_teoria_asignadas', 'horas_practica_asignadas', 'horas_laboratorio_asignadas', 'created_by', 'updated_by'])]
class TeachingAssignment extends Model
{
    /** @use HasFactory<TeachingAssignmentFactory> */
    use HasFactory;

    use SoftDeletes;

    protected function casts(): array
    {
        return ['horas_teoria_asignadas' => 'decimal:2', 'horas_practica_asignadas' => 'decimal:2',
            'horas_laboratorio_asignadas' => 'decimal:2'];
    }

    /** @return BelongsTo<Teacher, $this> */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    /** @return BelongsTo<Course, $this> */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /** @return BelongsTo<AcademicPeriod, $this> */
    public function academicPeriod(): BelongsTo
    {
        return $this->belongsTo(AcademicPeriod::class);
    }

    /** @return HasMany<TeachingAssignmentChange, $this> */
    public function changes(): HasMany
    {
        return $this->hasMany(TeachingAssignmentChange::class);
    }
}
