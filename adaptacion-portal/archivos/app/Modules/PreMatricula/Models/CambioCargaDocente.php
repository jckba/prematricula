<?php

namespace App\Modules\PreMatricula\Models;

use App\Shared\Models\ModuleModel;
use App\Shared\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Auditoría de la carga docente. Equivale a teaching_assignment_changes.
 */
final class CambioCargaDocente extends ModuleModel
{
    public const ACCIONES = ['ALTA', 'CAMBIO', 'BAJA'];

    protected $table = 'prematricula.cambio_carga_docente';

    protected $primaryKey = 'id_cambio';

    public $timestamps = false;

    protected $fillable = [
        'id_carga',
        'id_actor',
        'accion',
        'antes',
        'despues',
        'creado_en',
    ];

    protected function casts(): array
    {
        return [
            'antes' => 'array',
            'despues' => 'array',
            'creado_en' => 'datetime',
        ];
    }

    public function carga(): BelongsTo
    {
        return $this->belongsTo(CargaDocente::class, 'id_carga', 'id_carga');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'id_actor', 'id_usuario');
    }
}
