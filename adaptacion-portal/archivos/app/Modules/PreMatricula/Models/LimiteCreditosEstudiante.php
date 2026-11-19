<?php

namespace App\Modules\PreMatricula\Models;

use App\Shared\Models\Core\Estudiante;
use App\Shared\Models\ModuleModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Créditos máximos por estudiante. Reemplaza students.creditos_maximos.
 */
final class LimiteCreditosEstudiante extends ModuleModel
{
    public const CREDITOS_POR_DEFECTO = 22;

    protected $table = 'prematricula.limite_creditos_estudiante';

    protected $primaryKey = 'id_limite';

    public $timestamps = false;

    protected $fillable = [
        'id_estudiante',
        'creditos_maximos',
        'actualizado_en',
    ];

    protected function casts(): array
    {
        return ['actualizado_en' => 'datetime'];
    }

    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(Estudiante::class, 'id_estudiante', 'id_estudiante');
    }

    public static function creditosPara(int $idEstudiante): int
    {
        return (int) (self::query()->where('id_estudiante', $idEstudiante)->value('creditos_maximos')
            ?? self::CREDITOS_POR_DEFECTO);
    }
}
