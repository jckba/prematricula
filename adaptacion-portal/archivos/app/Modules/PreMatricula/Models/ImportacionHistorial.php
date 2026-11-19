<?php

namespace App\Modules\PreMatricula\Models;

use App\Shared\Models\ModuleModel;
use App\Shared\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Archivo de resultados importado. Equivale a academic_history_imports.
 */
final class ImportacionHistorial extends ModuleModel
{
    protected $table = 'prematricula.importacion_historial';

    protected $primaryKey = 'id_importacion';

    public $timestamps = false;

    protected $fillable = [
        'creado_por',
        'sha256',
        'motivo',
        'resumen',
        'creado_en',
    ];

    protected function casts(): array
    {
        return [
            'resumen' => 'array',
            'creado_en' => 'datetime',
        ];
    }

    public function autor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'creado_por', 'id_usuario');
    }

    public function cambios(): HasMany
    {
        return $this->hasMany(CambioHistorial::class, 'id_importacion', 'id_importacion');
    }
}
