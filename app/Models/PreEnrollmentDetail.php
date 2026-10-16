<?php

namespace App\Models;

use App\Enums\SchedulePreference;
use Database\Factories\PreEnrollmentDetailFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['pre_enrollment_request_id', 'course_id', 'preferencia_turno'])]
class PreEnrollmentDetail extends Model
{
    /** @use HasFactory<PreEnrollmentDetailFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'preferencia_turno' => SchedulePreference::class,
        ];
    }

    /** @return BelongsTo<PreEnrollmentRequest, $this> */
    public function preEnrollmentRequest(): BelongsTo
    {
        return $this->belongsTo(PreEnrollmentRequest::class);
    }

    /** @return BelongsTo<Course, $this> */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
}
