<?php

namespace App\Models;

use App\Enums\SchedulePreference;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['pre_enrollment_request_id', 'course_id', 'preferencia_turno'])]
class PreEnrollmentDetail extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'preferencia_turno' => SchedulePreference::class,
        ];
    }

    public function preEnrollmentRequest()
    {
        return $this->belongsTo(PreEnrollmentRequest::class);
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }
}
