<?php

namespace App\Modules\PreMatricula\Models;

use App\Shared\Models\Core\CursoPlan;
use App\Shared\Models\Core\PeriodoAcademico;
use App\Shared\Models\ModuleModel;
use App\Shared\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Horas semanales asignadas a un docente en un curso y período.
 * Equivale a teaching_assignments. La baja es lógica (eliminado_en).
 *
 * id_docente apunta a core.docente. Aún no existe App\Shared\Models\Core\Docente;
 * se debe pedir al equipo antes de declarar la relación.
 */
final class CargaDocente extends ModuleModel
{
    use SoftDeletes;

    public const CREATED_AT = 'creado_en';

    public const UPDATED_AT = 'actualizado_en';

    public const DELETED_AT = 'eliminado_en';

    protected $table = 'prematricula.carga_docente';

    protected $primaryKey = 'id_carga';

    protected $fillable = [
        'id_periodo',
        'id_curso_plan',
        'id_docente',
        'horas_teoria',
        'horas_practica',
        'horas_laboratorio',
        'creado_por',
        'actualizado_por',
    ];

    protected function casts(): array
    {
        return [
            'horas_teoria' => 'decimal:2',
            'horas_practica' => 'decimal:2',
            'horas_laboratorio' => 'decimal:2',
        ];
    }

    public function periodo(): BelongsTo
    {
        return $this->belongsTo(PeriodoAcademico::class, 'id_periodo', 'id_periodo');
    }

    public function cursoPlan(): BelongsTo
    {
        return $this->belongsTo(CursoPlan::class, 'id_curso_plan', 'id_curso_plan');
    }

    public function autor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'creado_por', 'id_usuario');
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'actualizado_por', 'id_usuario');
    }

    public function cambios(): HasMany
    {
        return $this->hasMany(CambioCargaDocente::class, 'id_carga', 'id_carga');
    }

    public function totalHoras(): float
    {
        return (float) $this->horas_teoria + (float) $this->horas_practica + (float) $this->horas_laboratorio;
    }
}
