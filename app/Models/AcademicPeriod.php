<?php

namespace App\Models;

use App\Enums\AcademicPeriodStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['codigo', 'fecha_inicio', 'fecha_fin', 'fecha_inicio_prematricula', 'fecha_fin_prematricula', 'estado', 'created_by', 'updated_by'])]
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

    /** Compatibility bridge for existing internal seeders using the legacy fields on creation only. */
    protected static function booted(): void
    {
        static::created(function (self $period): void {
            if ($period->fecha_inicio_prematricula !== null && $period->fecha_fin_prematricula !== null &&
                $period->fecha_inicio_prematricula->lt($period->fecha_fin_prematricula)) {
                $period->windows()->create([
                    'tipo' => 'ORDINARIA',
                    'fecha_inicio' => CarbonImmutable::parse($period->getAttributes()['fecha_inicio_prematricula'], config('academic.legacy_timezone'))->utc(),
                    'fecha_fin' => CarbonImmutable::parse($period->getAttributes()['fecha_fin_prematricula'], config('academic.legacy_timezone'))->utc(),
                    'motivo' => 'Compatibilidad de creación con fechas heredadas; autor original no registrado.',
                    'timezone' => config('academic.legacy_timezone'), 'origen' => 'MIGRACION', 'created_by' => null,
                ]);
            }
        });
    }

    public function windows(): HasMany
    {
        return $this->hasMany(PreEnrollmentWindow::class);
    }

    public function academicHistories(): HasMany
    {
        return $this->hasMany(AcademicHistory::class);
    }

    public function preEnrollmentRequests(): HasMany
    {
        return $this->hasMany(PreEnrollmentRequest::class);
    }
}
