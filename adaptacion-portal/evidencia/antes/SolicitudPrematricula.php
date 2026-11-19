<?php

namespace App\Modules\PreMatricula\Models;

use App\Shared\Models\Core\Estudiante;
use App\Shared\Models\Core\PeriodoAcademico;
use App\Shared\Models\Seguridad\Usuario;
use App\Shared\Models\ModuleModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class SolicitudPrematricula extends ModuleModel
{
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
    ];

    protected function casts(): array
    {
        return [
            'enviada_en' => 'datetime',
            'revisado_en' => 'datetime',
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
}
