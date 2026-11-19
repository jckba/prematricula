<?php

namespace App\Modules\PreMatricula\Models;

use App\Shared\Models\Core\PeriodoAcademico;
use App\Shared\Models\ModuleModel;
use App\Shared\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Ventana de prematrícula de un período. Equivale a pre_enrollment_windows.
 * Un período tiene una ventana ORDINARIA y, opcionalmente, varias EXTRAORDINARIA.
 */
final class CronogramaPrematricula extends ModuleModel
{
    public const TIPO_ORDINARIA = 'ORDINARIA';

    public const TIPO_EXTRAORDINARIA = 'EXTRAORDINARIA';

    protected $table = 'prematricula.cronograma_prematricula';

    protected $primaryKey = 'id_cronograma';

    public $timestamps = false;

    protected $fillable = [
        'id_periodo',
        'tipo',
        'fecha_inicio',
        'fecha_fin',
        'estado',
        'motivo',
        'zona_horaria',
        'creado_por',
        'creado_en',
    ];

    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'datetime',
            'fecha_fin' => 'datetime',
            'creado_en' => 'datetime',
        ];
    }

    public function periodo(): BelongsTo
    {
        return $this->belongsTo(PeriodoAcademico::class, 'id_periodo', 'id_periodo');
    }

    public function autor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'creado_por', 'id_usuario');
    }

    public function revisiones(): HasMany
    {
        return $this->hasMany(RevisionSolicitud::class, 'id_cronograma', 'id_cronograma');
    }

    public function estaActiva(?\DateTimeInterface $momento = null): bool
    {
        $momento ??= now();

        // Inicio inclusivo, fin exclusivo (regla del proyecto original).
        return $this->fecha_inicio <= $momento && $momento < $this->fecha_fin;
    }
}
