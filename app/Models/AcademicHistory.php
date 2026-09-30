<?php

namespace App\Models;

use App\Enums\AcademicHistoryStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['student_id', 'course_id', 'academic_period_id', 'nota_final', 'estado'])]
class AcademicHistory extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'nota_final' => 'decimal:2',
            'estado' => AcademicHistoryStatus::class,
        ];
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function academicPeriod()
    {
        return $this->belongsTo(AcademicPeriod::class);
    }

}
