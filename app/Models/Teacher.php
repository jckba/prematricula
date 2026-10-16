<?php

namespace App\Models;

use App\Enums\RecordStatus;
use Database\Factories\TeacherFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['codigo_institucional', 'nombres', 'apellidos', 'correo_institucional', 'estado', 'max_horas_semanales'])]
class Teacher extends Model
{
    /** @use HasFactory<TeacherFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['estado' => RecordStatus::class, 'max_horas_semanales' => 'decimal:2'];
    }

    /** @return HasMany<TeachingAssignment, $this> */
    public function teachingAssignments(): HasMany
    {
        return $this->hasMany(TeachingAssignment::class);
    }
}
