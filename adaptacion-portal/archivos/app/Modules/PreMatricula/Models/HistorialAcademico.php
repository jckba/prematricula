<?php

namespace App\Modules\PreMatricula\Models;

use App\Shared\Models\Core\CursoPlan;
use App\Shared\Models\Core\Estudiante;
use App\Shared\Models\Core\PeriodoAcademico;
use App\Shared\Models\ModuleModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Resultado oficial importado (estudiante/curso/período). Equivale a academic_histories.
 */
final class HistorialAcademico extends ModuleModel
{
    public const EN_CURSO = 'EN_CURSO';

    public const APROBADO = 'APROBADO';

    public const DESAPROBADO = 'DESAPROBADO';

    public const RETIRADO = 'RETIRADO';

    protected $table = 'prematricula.historial_academico';

    protected $primaryKey = 'id_historial';

    public $timestamps = false;

    protected $fillable = [
        'id_estudiante',
        'id_curso_plan',
        'id_periodo',
        'nota_final',
        'estado',
        'creado_en',
        'actualizado_en',
    ];

    protected function casts(): array
    {
        return [
            'nota_final' => 'decimal:2',
            'creado_en' => 'datetime',
            'actualizado_en' => 'datetime',
        ];
    }

    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(Estudiante::class, 'id_estudiante', 'id_estudiante');
    }

    public function cursoPlan(): BelongsTo
    {
        return $this->belongsTo(CursoPlan::class, 'id_curso_plan', 'id_curso_plan');
    }

    public function periodo(): BelongsTo
    {
        return $this->belongsTo(PeriodoAcademico::class, 'id_periodo', 'id_periodo');
    }

    public function cambios(): HasMany
    {
        return $this->hasMany(CambioHistorial::class, 'id_historial', 'id_historial');
    }
}
