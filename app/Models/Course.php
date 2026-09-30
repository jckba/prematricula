<?php

namespace App\Models;

use App\Enums\RecordStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['codigo', 'nombre', 'creditos', 'ciclo', 'estado'])]
class Course extends Model
{
    use HasFactory;
    protected function casts(): array
    {
        return [
            'creditos' => 'integer',
            'ciclo' => 'integer',
            'estado' => RecordStatus::class,
        ];
    }

    public function prerequisites()
    {
        return $this->belongsToMany(
            Course::class,
            'course_prerequisites',
            'course_id',
            'prerequisite_id'
        );
    }

    public function prerequisiteFor()
    {
        return $this->belongsToMany(
            Course::class,
            'course_prerequisites',
            'prerequisite_id',
            'course_id'
        );
    }

    public function academicHistories()
    {
        return $this->hasMany(AcademicHistory::class);
    }

    public function preEnrollmentDetails()
    {
        return $this->hasMany(PreEnrollmentDetail::class);
    }
}
