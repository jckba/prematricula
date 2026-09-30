<?php

namespace App\Models;

use App\Enums\PreEnrollmentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['stundent_id', 'academic_period_id', 'estado', 'fecha_envio', 'fecha_revision', 'reviewed_by', 'comentarios_coordinador'])]
class PreEnrollmentRequest extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'fecha_envio' => 'datetime',
            'fecha_revision' => 'datetime',
            'estado' => PreEnrollmentStatus::class,
        ];
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
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
