<?php

namespace App\Services;

use App\Models\AcademicPeriod;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

class AcademicDemand
{
    public const NOTICE = 'Estimaciones de demanda de prematrícula; no constituyen matrícula definitiva.';

    /** @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function forPeriod(AcademicPeriod $period, array $filters = []): array
    {
        $page = $this->courses($period, $filters)->paginate($filters['per_page'] ?? 20)->withQueryString();
        $pagination = $page->toArray();
        unset($pagination['data']);

        return ['academic_period_id' => $period->id, 'advertencia' => self::NOTICE,
            'cursos' => $page->items(), 'paginacion' => $pagination];
    }

    /** @param array<string, mixed> $filters */
    public function courses(AcademicPeriod $period, array $filters = []): Builder
    {
        $query = $this->selections($period, $filters)
            ->select('c.id as course_id', 'c.codigo', 'c.nombre', 'c.ciclo', 'c.creditos')
            ->groupBy('c.id', 'c.codigo', 'c.nombre', 'c.ciclo', 'c.creditos');
        $this->addCounts($query);

        return $query->orderBy($filters['sort'] ?? 'course_id', $filters['direction'] ?? 'asc')->orderBy('c.id');
    }

    /** @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function summary(AcademicPeriod $period, array $filters = []): array
    {
        $totals = $this->registered($period)->selectRaw('COUNT(DISTINCT p.student_id)::integer AS alumnos_enviaron')
            ->selectRaw("COUNT(DISTINCT p.student_id) FILTER (WHERE p.correction_window_id IS NOT NULL OR v.estado = 'REQUIERE_AJUSTE')::integer AS alumnos_requieren_ajuste")
            ->selectRaw('COUNT(DISTINCT p.student_id) FILTER (WHERE v.id IS NULL)::integer AS alumnos_sin_validacion')->first();
        $cycles = $this->selections($period, $filters)->select('c.ciclo')->groupBy('c.ciclo')->orderBy('c.ciclo');
        $this->addCounts($cycles);
        $cycles->selectRaw('COUNT(DISTINCT s.student_id)::integer AS alumnos_enviaron');

        return ['academic_period_id' => $period->id, 'advertencia' => self::NOTICE,
            'alumnos_enviaron' => $totals->alumnos_enviaron,
            'alumnos_requieren_ajuste' => $totals->alumnos_requieren_ajuste,
            'alumnos_sin_validacion' => $totals->alumnos_sin_validacion,
            'poblacion_academica' => null, 'alumnos_no_enviaron' => null,
            'fuente_poblacion' => 'No se dispone de un padrón académico por período.',
            'ciclos' => $cycles->get()->all()];
    }

    /** The current submitted revision survives an unsubmitted correction draft. */
    private function registered(AcademicPeriod $period): Builder
    {
        return DB::table('pre_enrollment_requests as p')
            ->join('pre_enrollment_revisions as r', function (JoinClause $join): void {
                $join->on('r.id', '=', 'p.current_revision_id')->on('r.pre_enrollment_request_id', '=', 'p.id');
            })
            ->leftJoin('pre_enrollment_validations as v', function (JoinClause $join): void {
                $join->on('v.pre_enrollment_request_id', '=', 'p.id')->on('v.revision_id', '=', 'r.id');
            })
            ->where('p.academic_period_id', $period->id)
            ->where(function (Builder $query): void {
                $query->where('p.estado', 'ENVIADA')->orWhere(function (Builder $draft): void {
                    $draft->where('p.estado', 'BORRADOR')->whereNotNull('p.correction_window_id');
                });
            })
            ->where('r.motivo', 'ENVIO')->whereNotNull('r.submitted_at')->whereNotNull('r.revision_number')
            ->whereRaw("jsonb_typeof(r.snapshot->'detalles') = 'array'")
            ->whereExists(function (Builder $query): void {
                $query->selectRaw('1')->from('pre_enrollment_revision_courses as rc')
                    ->whereColumn('rc.revision_id', 'r.id')
                    ->whereRaw("EXISTS (SELECT 1 FROM jsonb_array_elements(
                        CASE WHEN jsonb_typeof(r.snapshot->'detalles') = 'array' THEN r.snapshot->'detalles' ELSE '[]'::jsonb END
                    ) AS detail WHERE detail->>'course_id' = rc.course_id::text)");
            });
    }

    /** @param array<string, mixed> $filters */
    public function selections(AcademicPeriod $period, array $filters = []): Builder
    {
        $selection = $this->registered($period)
            ->crossJoin(DB::raw("LATERAL (
                SELECT DISTINCT ON (detail->>'course_id') detail
                FROM jsonb_array_elements(
                    CASE WHEN jsonb_typeof(r.snapshot->'detalles') = 'array' THEN r.snapshot->'detalles' ELSE '[]'::jsonb END
                ) WITH ORDINALITY AS items(detail, position)
                ORDER BY detail->>'course_id', position
            ) AS selected"))
            ->join('pre_enrollment_revision_courses as rc', function (JoinClause $join): void {
                $join->on('rc.revision_id', '=', 'r.id')->whereRaw("rc.course_id::text = selected.detail->>'course_id'");
            })
            ->leftJoin(DB::raw("LATERAL (
                SELECT course->>'estado' AS estado FROM jsonb_array_elements(
                    CASE WHEN jsonb_typeof(v.cursos) = 'array' THEN v.cursos ELSE '[]'::jsonb END
                ) AS course
                WHERE course->>'course_id' = rc.course_id::text LIMIT 1
            ) AS validation"), DB::raw('true'), '=', DB::raw('true'))
            ->select('p.student_id', 'p.id as pre_enrollment_request_id', 'r.id as revision_id', 'rc.course_id')
            ->selectRaw("CASE WHEN selected.detail->>'preferencia_turno' IN ('MANANA', 'TARDE')
                THEN selected.detail->>'preferencia_turno' ELSE 'SIN_PREFERENCIA' END AS preferencia_turno")
            ->selectRaw("CASE WHEN p.correction_window_id IS NOT NULL OR v.estado = 'REQUIERE_AJUSTE' THEN 'REQUIERE_AJUSTE'
                WHEN v.estado IN ('VALIDA', 'PENDIENTE_RESULTADOS') AND validation.estado IN ('VALIDA', 'PENDIENTE_RESULTADOS', 'REQUIERE_AJUSTE')
                THEN validation.estado ELSE 'SIN_VALIDACION' END AS estado_validacion");
        $query = DB::query()->fromSub($selection, 's')->join('courses as c', 'c.id', '=', 's.course_id');
        if (isset($filters['ciclo'])) {
            $query->where('c.ciclo', $filters['ciclo']);
        }
        if (isset($filters['course_id'])) {
            $query->where('c.id', $filters['course_id']);
        }
        if (isset($filters['q'])) {
            $pattern = '%'.addcslashes($filters['q'], '\\%_').'%';
            $query->where(fn (Builder $search) => $search->whereLike('c.codigo', $pattern)->orWhereLike('c.nombre', $pattern));
        }
        foreach (['estado_validacion', 'preferencia_turno'] as $field) {
            if (isset($filters[$field])) {
                $query->where('s.'.$field, $filters[$field]);
            }
        }

        return $query;
    }

    private function addCounts(Builder $query): void
    {
        $query->selectRaw('COUNT(*)::integer AS registrada');
        foreach (['VALIDA' => 'valida', 'PENDIENTE_RESULTADOS' => 'pendiente_resultados',
            'REQUIERE_AJUSTE' => 'requiere_ajuste', 'SIN_VALIDACION' => 'sin_validacion'] as $state => $field) {
            $query->selectRaw("COUNT(*) FILTER (WHERE s.estado_validacion = ?)::integer AS {$field}", [$state]);
        }
        foreach (['MANANA' => 'manana', 'TARDE' => 'tarde', 'SIN_PREFERENCIA' => 'sin_preferencia'] as $preference => $field) {
            $query->selectRaw("COUNT(*) FILTER (WHERE s.preferencia_turno = ?)::integer AS {$field}", [$preference]);
        }
    }
}
