<?php

namespace App\Models;

use App\Enums\AcademicPeriodStatus;
use Illuminate\Database\Eloquent\Attributes\DateFormat;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;


#[Fillable(['codigo', 'fecha_inicio', 'fecha_fin', 'fecha_inicio_prematricula', 'fecha_fin_prematricula', 'estado'])]
class AcademicPeriod extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
            'fecha_inicio_prematricula' => 'datetime',
            'fecha_fin_prematricula' => 'datetime',
            'estado' => AcademicPeriodStatus::class,
        ];
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
