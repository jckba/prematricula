<?php

namespace App\Modules\PreMatricula\Models;

use App\Shared\Models\Core\CursoPlan;
use App\Shared\Models\ModuleModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Curso contenido en una revisión enviada (clave compuesta id_revision + id_curso_plan).
 * Fuente para el cálculo de demanda por curso. Solo se inserta; nunca se actualiza.
 */
final class RevisionCurso extends ModuleModel
{
    protected $table = 'prematricula.revision_curso';

    protected $primaryKey = 'id_revision';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'id_revision',
        'id_curso_plan',
        'preferencia_turno',
    ];

    public function revision(): BelongsTo
    {
        return $this->belongsTo(RevisionSolicitud::class, 'id_revision', 'id_revision');
    }

    public function cursoPlan(): BelongsTo
    {
        return $this->belongsTo(CursoPlan::class, 'id_curso_plan', 'id_curso_plan');
    }
}
