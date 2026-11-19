<?php

namespace App\Modules\PreMatricula\Models;

use App\Shared\Models\ModuleModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Valor anterior/nuevo de un resultado tras una importación. Equivale a academic_history_changes.
 */
final class CambioHistorial extends ModuleModel
{
    protected $table = 'prematricula.cambio_historial';

    protected $primaryKey = 'id_cambio';

    public $timestamps = false;

    protected $fillable = [
        'id_importacion',
        'id_historial',
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

    public function importacion(): BelongsTo
    {
        return $this->belongsTo(ImportacionHistorial::class, 'id_importacion', 'id_importacion');
    }

    public function historial(): BelongsTo
    {
        return $this->belongsTo(HistorialAcademico::class, 'id_historial', 'id_historial');
    }
}
