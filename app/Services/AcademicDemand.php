<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\AcademicPeriod;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

class AcademicDemand
{
    public const NOTICE = 'Estimaciones de demanda de prematrícula; no constituyen matrícula definitiva.';

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function forPeriod(AcademicPeriod $period, array $filters = []): array
    {
        $page = $this->courses($period, $filters)->paginate($filters['per_page'] ?? 20)->withQueryString();
        $pagination = $page->toArray();
        unset($pagination['data']);

        return [
            'academic_period_id' => $period->id,
            'advertencia' => self::NOTICE,
            'cursos' => $page->items(),
            'paginacion' => $pagination,
        ];
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
    /**
     * Resumen por ciclo y total de estudiantes únicos del período.
     *
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function summary(AcademicPeriod $period, array $filters = []): array
    {
        $selection = $this->selections($period, $filters);

        // Total de estudiantes únicos en todo el período.
        $totalStudents = (clone $selection)
            ->selectRaw('COUNT(DISTINCT s.student_id) AS total_estudiantes')
            ->value('total_estudiantes');

        // Estudiantes únicos agrupados por ciclo.
        $cycles = (clone $selection)
            ->select('c.ciclo')
            ->selectRaw(
                'COUNT(DISTINCT s.student_id) AS estudiantes_prematriculados'
            )
            ->groupBy('c.ciclo')
            ->orderBy('c.ciclo')
            ->get();

        return [
            'academic_period_id' => $period->id,
            'advertencia' => self::NOTICE,
            'total_estudiantes' => (int) $totalStudents,
            'ciclos' => $cycles->all(),
        ];
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
            ->whereRaw("JSON_TYPE(JSON_EXTRACT(r.snapshot, '$.detalles')) = 'ARRAY'");
    }

    /**
     * One row per (current revision, registered course) whose course appears in the
     * snapshot details, with the shift preference of its first matching detail and
     * the per-course state of its first matching validation entry.
     *
     * MariaDB < 10.6 has neither LATERAL nor JSON_TABLE, so JSON arrays are walked
     * with a small derived table of positions (0..n-1): for each registered course,
     * MIN(position) whose element has that course_id is the first match.
     *
     * @param  array<string, mixed>  $filters
     */
    public function selections(AcademicPeriod $period, array $filters = []): Builder
    {
        $base = $this->registered($period)
            ->select('p.student_id', 'p.id as pre_enrollment_request_id', 'r.id as revision_id',
                'p.correction_window_id', 'v.estado as request_validation')
            ->selectRaw("JSON_EXTRACT(r.snapshot, '$.detalles') AS detalles")
            ->addSelect('v.cursos');

        // JSON values are compared as bytes (CAST ... AS BINARY), like PostgreSQL text:
        // 'manana' <> 'MANANA' and '05' <> '5', whatever the column collation is.
        $positions = $this->positions($period);
        $firstMatch = fn (string $array): string => "(SELECT MIN(n.i) FROM ({$positions}) AS n
            WHERE JSON_TYPE({$array}) = 'ARRAY' AND n.i < JSON_LENGTH({$array})
            AND CAST(JSON_UNQUOTE(JSON_EXTRACT({$array}, CONCAT('$[', n.i, '].course_id'))) AS BINARY)
                = CAST(rc.course_id AS BINARY))";

        $candidates = DB::query()->fromSub($base, 'b')
            ->join('pre_enrollment_revision_courses as rc', 'rc.revision_id', '=', 'b.revision_id')
            ->select('b.student_id', 'b.pre_enrollment_request_id', 'b.revision_id', 'rc.course_id',
                'b.correction_window_id', 'b.request_validation', 'b.detalles', 'b.cursos')
            ->selectRaw($firstMatch('b.detalles').' AS detail_position')
            ->selectRaw($firstMatch('b.cursos').' AS validation_position');

        $preference = "CAST(JSON_UNQUOTE(JSON_EXTRACT(x.detalles, CONCAT('$[', x.detail_position, '].preferencia_turno'))) AS BINARY)";
        $courseState = "CAST(JSON_UNQUOTE(JSON_EXTRACT(x.cursos, CONCAT('$[', x.validation_position, '].estado'))) AS BINARY)";
        $whenEqual = fn (string $expression, array $values): string => implode(' ',
            array_map(fn (string $value): string => "WHEN {$expression} = '{$value}' THEN '{$value}'", $values));

        $selection = DB::query()->fromSub($candidates, 'x')
            ->whereNotNull('x.detail_position')
            ->select('x.student_id', 'x.pre_enrollment_request_id', 'x.revision_id', 'x.course_id')
            ->selectRaw('CASE '.$whenEqual($preference, ['MANANA', 'TARDE'])." ELSE 'SIN_PREFERENCIA' END AS preferencia_turno")
            ->selectRaw("CASE WHEN x.correction_window_id IS NOT NULL OR x.request_validation = 'REQUIERE_AJUSTE' THEN 'REQUIERE_AJUSTE'
                WHEN x.request_validation IS NULL OR x.request_validation NOT IN ('VALIDA', 'PENDIENTE_RESULTADOS') THEN 'SIN_VALIDACION' "
                .$whenEqual($courseState, ['VALIDA', 'PENDIENTE_RESULTADOS', 'REQUIERE_AJUSTE'])
                ." ELSE 'SIN_VALIDACION' END AS estado_validacion");

        $query = DB::query()->fromSub($selection, 's')->join('courses as c', 'c.id', '=', 's.course_id');

        if (isset($filters['ciclo'])) {
            $query->where('c.ciclo', $filters['ciclo']);
        }
        if (isset($filters['course_id'])) {
            $query->where('c.id', $filters['course_id']);
        }
        if (isset($filters['q'])) {
            $pattern = '%' . addcslashes((string) $filters['q'], '\\%_') . '%';
            $query->where(fn (Builder $search) => $search->whereLike('c.codigo', $pattern)->orWhereLike('c.nombre', $pattern));
        }
        foreach (['estado_validacion', 'preferencia_turno'] as $field) {
            if (isset($filters[$field])) {
                $query->where('s.' . $field, $filters[$field]);
            }
        }

        return $query;
    }

    /**
     * Literal derived table "SELECT 0 AS i UNION ALL SELECT 1 ..." covering the longest
     * details/validation array of the period. Values are integers generated here, so
     * nothing user-controlled reaches the SQL.
     */
    private function positions(AcademicPeriod $period): string
    {
        $longest = (int) $this->registered($period)
            ->selectRaw("GREATEST(COALESCE(MAX(JSON_LENGTH(r.snapshot, '$.detalles')), 0),
                COALESCE(MAX(JSON_LENGTH(v.cursos)), 0)) AS longest")
            ->value('longest');

        if ($longest === 0) {
            return 'SELECT 0 AS i FROM DUAL WHERE 1 = 0';
        }

        return implode(' UNION ALL ', array_map(fn (int $i): string => "SELECT {$i} AS i", range(0, $longest - 1)));
    }

    private function addCounts(Builder $query): void
    {
        // COUNT(CASE WHEN ... THEN 1 END) is the portable form of COUNT(*) FILTER (WHERE ...).
        $query->selectRaw('COUNT(*) AS registrada');
        foreach ([
                     'VALIDA' => 'valida',
                     'PENDIENTE_RESULTADOS' => 'pendiente_resultados',
                     'REQUIERE_AJUSTE' => 'requiere_ajuste',
                     'SIN_VALIDACION' => 'sin_validacion',
                 ] as $state => $field) {
            $query->selectRaw("COUNT(CASE WHEN s.estado_validacion = ? THEN 1 END) AS {$field}", [$state]);
        }
        foreach ([
                     'MANANA' => 'manana',
                     'TARDE' => 'tarde',
                     'SIN_PREFERENCIA' => 'sin_preferencia',
                 ] as $preference => $field) {
            $query->selectRaw("COUNT(CASE WHEN s.preferencia_turno = ? THEN 1 END) AS {$field}", [$preference]);
        }
    }
}
