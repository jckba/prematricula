<?php

namespace App\Modules\PreMatricula\Models;

use App\Shared\Models\Core\Estudiante;
use App\Shared\Models\Core\PeriodoAcademico;
use App\Shared\Models\ModuleModel;
use App\Shared\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Solicitud de prematrícula de un estudiante en un período. Equivale a pre_enrollment_requests.
 */
final class SolicitudPrematricula extends ModuleModel
{
    public const ESTADO_BORRADOR = 'BORRADOR';

    public const ESTADO_ENVIADA = 'ENVIADA';

    protected $table = 'prematricula.solicitud_prematricula';

    protected $primaryKey = 'id_solicitud';

    public $timestamps = false;

    protected $fillable = [
        'id_estudiante',
        'id_periodo',
        'estado',
        'observacion',
        'enviada_en',
        'revisado_por',
        'revisado_en',
        'id_revision_vigente',
        'id_cronograma_correccion',
        'creado_en',
        'actualizado_en',
    ];

    protected function casts(): array
    {
        return [
            'enviada_en' => 'datetime',
            'revisado_en' => 'datetime',
            'creado_en' => 'datetime',
            'actualizado_en' => 'datetime',
        ];
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(DetalleSolicitud::class, 'id_solicitud', 'id_solicitud');
    }

    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(Estudiante::class, 'id_estudiante', 'id_estudiante');
    }

    public function periodo(): BelongsTo
    {
        return $this->belongsTo(PeriodoAcademico::class, 'id_periodo', 'id_periodo');
    }

    public function revisor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'revisado_por', 'id_usuario');
    }

    public function revisiones(): HasMany
    {
        return $this->hasMany(RevisionSolicitud::class, 'id_solicitud', 'id_solicitud');
    }

    public function revisionVigente(): BelongsTo
    {
        return $this->belongsTo(RevisionSolicitud::class, 'id_revision_vigente', 'id_revision');
    }

    public function cronogramaCorreccion(): BelongsTo
    {
        return $this->belongsTo(CronogramaPrematricula::class, 'id_cronograma_correccion', 'id_cronograma');
    }

    public function validacion(): HasOne
    {
        return $this->hasOne(ValidacionSolicitud::class, 'id_solicitud', 'id_solicitud');
    }

    public function estaEnCorreccion(): bool
    {
        return $this->estado === self::ESTADO_BORRADOR && $this->id_cronograma_correccion !== null;
    }
}
