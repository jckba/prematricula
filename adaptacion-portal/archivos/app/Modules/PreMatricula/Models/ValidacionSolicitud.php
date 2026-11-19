<?php

namespace App\Modules\PreMatricula\Models;

use App\Shared\Models\ModuleModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Validación académica vigente de una solicitud. Equivale a pre_enrollment_validations.
 */
final class ValidacionSolicitud extends ModuleModel
{
    public const VALIDA = 'VALIDA';

    public const PENDIENTE_RESULTADOS = 'PENDIENTE_RESULTADOS';

    public const REQUIERE_AJUSTE = 'REQUIERE_AJUSTE';

    protected $table = 'prematricula.validacion_solicitud';

    protected $primaryKey = 'id_validacion';

    public $timestamps = false;

    protected $fillable = [
        'id_solicitud',
        'id_revision',
        'estado',
        'cursos',
        'evaluada_en',
    ];

    protected function casts(): array
    {
        return [
            'cursos' => 'array',
            'evaluada_en' => 'datetime',
        ];
    }

    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(SolicitudPrematricula::class, 'id_solicitud', 'id_solicitud');
    }

    public function revision(): BelongsTo
    {
        return $this->belongsTo(RevisionSolicitud::class, 'id_revision', 'id_revision');
    }
}
