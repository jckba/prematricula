<?php

namespace App\Models;

use App\Enums\RecordStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['user_id', 'codigo_universitario', 'nombres', 'apellidos', 'creditos_maximos', 'estado'])]
class Student extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'creditos_maximos' => 'integer',
            'estado' => RecordStatus::class,
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function academicHistories()
    {
        return $this->hasMany(AcademicHistory::class);
    }

    public function preEnrollmentRequests()
    {
        return $this->hasMany(PreEnrollmentRequest::class);
    }
}
