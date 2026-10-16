<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use LogicException;

#[Fillable(['pre_enrollment_request_id', 'window_id', 'motivo', 'snapshot', 'revision_number', 'receipt_code', 'submitted_at', 'schema_version', 'pdf_base64', 'pdf_sha256'])]
class PreEnrollmentRevision extends Model
{
    protected $hidden = ['pdf_base64'];

    protected $dateFormat = 'Y-m-d H:i:sP';

    protected function casts(): array
    {
        return ['snapshot' => 'array', 'submitted_at' => 'immutable_datetime', 'revision_number' => 'integer', 'schema_version' => 'integer'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Las revisiones enviadas son inmutables.'));
        static::deleting(fn () => throw new LogicException('Las revisiones enviadas son inmutables.'));
    }

    public function preEnrollmentRequest(): BelongsTo
    {
        return $this->belongsTo(PreEnrollmentRequest::class);
    }

    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'pre_enrollment_revision_courses', 'revision_id', 'course_id');
    }
}
