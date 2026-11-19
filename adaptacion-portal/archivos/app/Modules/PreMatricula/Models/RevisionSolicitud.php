<?php

namespace App\Modules\PreMatricula\Models;

use App\Shared\Models\Core\CursoPlan;
use App\Shared\Models\ModuleModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Envío inmutable de una solicitud (snapshot + constancia PDF).
 * Equivale a pre_enrollment_revisions. Un trigger impide UPDATE y DELETE.
 */
final class RevisionSolicitud extends ModuleModel
{
    public const MOTIVO_ENVIO = 'ENVIO';

    public const MOTIVO_LEGACY = 'LEGACY_CAPTURE';

    protected $table = 'prematricula.revision_solicitud';

    protected $primaryKey = 'id_revision';

    public $timestamps = false;

    protected $fillable = [
        'id_solicitud',
        'id_cronograma',
        'motivo',
        'numero_revision',
        'codigo_constancia',
        'snapshot',
        'version_esquema',
        'pdf_base64',
        'pdf_sha256',
        'enviada_en',
        'creado_en',
    ];

    /** El PDF embebido no se expone al serializar. */
    protected $hidden = ['pdf_base64'];

    protected function casts(): array
    {
        return [
            'snapshot' => 'array',
            'enviada_en' => 'datetime',
            'creado_en' => 'datetime',
        ];
    }

    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(SolicitudPrematricula::class, 'id_solicitud', 'id_solicitud');
    }

    public function cronograma(): BelongsTo
    {
        return $this->belongsTo(CronogramaPrematricula::class, 'id_cronograma', 'id_cronograma');
    }

    public function cursos(): HasMany
    {
        return $this->hasMany(RevisionCurso::class, 'id_revision', 'id_revision');
    }

    public function cursosPlan(): BelongsToMany
    {
        return $this->belongsToMany(
            CursoPlan::class,
            'prematricula.revision_curso',
            'id_revision',
            'id_curso_plan',
            'id_revision',
            'id_curso_plan',
        )->withPivot('preferencia_turno');
    }
}
