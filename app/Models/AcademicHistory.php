<?php

namespace App\Models;

use App\Enums\AcademicHistoryStatus;
use Database\Factories\AcademicHistoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['student_id', 'course_id', 'academic_period_id', 'nota_final', 'estado'])]
class AcademicHistory extends Model
{
    /** @use HasFactory<AcademicHistoryFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'nota_final' => 'decimal:2',
            'estado' => AcademicHistoryStatus::class,
        ];
    }

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
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
}
