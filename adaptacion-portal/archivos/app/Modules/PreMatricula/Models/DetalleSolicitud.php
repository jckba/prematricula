<?php

namespace App\Modules\PreMatricula\Models;

use App\Shared\Models\Core\CursoPlan;
use App\Shared\Models\Core\Seccion;
use App\Shared\Models\ModuleModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Curso elegido dentro del borrador de una solicitud. Equivale a pre_enrollment_details.
 */
final class DetalleSolicitud extends ModuleModel
{
    public const TURNOS = ['MANANA', 'TARDE', 'SIN_PREFERENCIA'];

    protected $table = 'prematricula.detalle_solicitud';

    protected $primaryKey = 'id_detalle';

    public $timestamps = false;

    protected $fillable = [
        'id_solicitud',
        'id_curso_plan',
        'id_seccion_preferida',
        'preferencia_turno',
        'estado',
        'motivo_rechazo',
    ];

    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(SolicitudPrematricula::class, 'id_solicitud', 'id_solicitud');
    }

    public function cursoPlan(): BelongsTo
    {
        return $this->belongsTo(CursoPlan::class, 'id_curso_plan', 'id_curso_plan');
    }

    public function seccionPreferida(): BelongsTo
    {
        return $this->belongsTo(Seccion::class, 'id_seccion_preferida', 'id_seccion');
    }
}
