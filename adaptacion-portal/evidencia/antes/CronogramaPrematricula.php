<?php

namespace App\Modules\PreMatricula\Models;

use App\Shared\Models\Core\PeriodoAcademico;
use App\Shared\Models\ModuleModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CronogramaPrematricula extends ModuleModel
{
    protected $table = 'prematricula.cronograma_prematricula';

    protected $primaryKey = 'id_cronograma';

    public $timestamps = false;

    protected $fillable = [
        'id_periodo',
        'fecha_inicio',
        'fecha_fin',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'datetime',
            'fecha_fin' => 'datetime',
        ];
    }

    public function periodo(): BelongsTo
    {
        return $this->belongsTo(PeriodoAcademico::class, 'id_periodo', 'id_periodo');
    }
}
