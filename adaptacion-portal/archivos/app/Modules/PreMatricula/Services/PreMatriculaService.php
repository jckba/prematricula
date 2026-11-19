<?php

namespace App\Modules\PreMatricula\Services;

use App\Modules\PreMatricula\Models\RevisionSolicitud;
use App\Modules\PreMatricula\Models\SolicitudPrematricula;
use App\Shared\Services\ModuleService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class PreMatriculaService extends ModuleService
{
    /**
     * @param  array<string, mixed>  $solicitud
     * @param  array<int, array<string, mixed>>  $detalles
     */
    public function crearSolicitud(array $solicitud, array $detalles): SolicitudPrematricula
    {
        return DB::transaction(function () use ($solicitud, $detalles): SolicitudPrematricula {
            $registro = SolicitudPrematricula::query()->create($solicitud);
            $registro->detalles()->createMany($detalles);

            return $registro->load('detalles');
        });
    }

    /**
     * Demanda estimada por curso en un período.
     *
     * Solo cuenta la revisión vigente de cada solicitud cuando es un ENVIO; los borradores,
     * capturas heredadas y versiones reemplazadas no suman. Cada estudiante cuenta una vez por curso.
     *
     * @return Collection<int, object{id_curso_plan: int, codigo: string, nombre: string, ciclo: int, estudiantes: int, manana: int, tarde: int, sin_preferencia: int}>
     */
    public function demandaPorCurso(int $idPeriodo): Collection
    {
        return DB::table('prematricula.solicitud_prematricula as s')
            ->join('prematricula.revision_solicitud as r', function ($join): void {
                $join->on('r.id_revision', '=', 's.id_revision_vigente')
                    ->where('r.motivo', '=', RevisionSolicitud::MOTIVO_ENVIO);
            })
            ->join('prematricula.revision_curso as rc', 'rc.id_revision', '=', 'r.id_revision')
            ->join('core.curso_plan as cp', 'cp.id_curso_plan', '=', 'rc.id_curso_plan')
            ->join('core.curso as c', 'c.id_curso', '=', 'cp.id_curso')
            ->where('s.id_periodo', $idPeriodo)
            ->groupBy('rc.id_curso_plan', 'c.codigo', 'c.nombre', 'cp.ciclo')
            ->orderByDesc('estudiantes')
            ->orderBy('c.codigo')
            ->select([
                'rc.id_curso_plan',
                'c.codigo',
                'c.nombre',
                'cp.ciclo',
                DB::raw('COUNT(DISTINCT s.id_estudiante)::int AS estudiantes'),
                DB::raw("COUNT(*) FILTER (WHERE rc.preferencia_turno = 'MANANA')::int AS manana"),
                DB::raw("COUNT(*) FILTER (WHERE rc.preferencia_turno = 'TARDE')::int AS tarde"),
                DB::raw("COUNT(*) FILTER (WHERE rc.preferencia_turno = 'SIN_PREFERENCIA')::int AS sin_preferencia"),
            ])
            ->get();
    }
}
