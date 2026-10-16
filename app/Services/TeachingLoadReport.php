<?php

namespace App\Services;

use App\Models\AcademicPeriod;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class TeachingLoadReport
{
    public const NOTICE = 'Modelo simplificado de una unidad docente por curso y período. No representa grupos paralelos, turnos, horarios ni conflictos de tiempo.';

    public function __construct(private readonly AcademicDemand $demand) {}

    /** @return array{academic_period_id: int, advertencia: string, advertencia_demanda: string} */
    public function context(AcademicPeriod $period): array
    {
        return ['academic_period_id' => $period->id, 'advertencia' => self::NOTICE, 'advertencia_demanda' => AcademicDemand::NOTICE];
    }

    /** @param array<string, mixed> $filters */
    public function assignments(array $filters = []): EloquentBuilder
    {
        $query = TeachingAssignment::query()->with(['teacher', 'course:id,codigo,nombre']);
        foreach (['academic_period_id', 'teacher_id', 'course_id'] as $field) {
            if (isset($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }

        return $query;
    }

    /** @param array<string, mixed> $filters */
    public function teachers(AcademicPeriod $period, array $filters = []): EloquentBuilder
    {
        $query = Teacher::query()->leftJoinSub($this->sums($period, 'teacher_id'), 'load', 'load.teacher_id', '=', 'teachers.id')
            ->select('teachers.*')->selectRaw('COALESCE(load.asignaciones, 0)::integer as cantidad_cursos');
        foreach (array_keys(TeachingHours::FIELDS) as $field) {
            $query->selectRaw("COALESCE(load.{$field}, 0)::numeric(12,2) as {$field}");
        }
        $exceeds = 'teachers.max_horas_semanales IS NOT NULL AND COALESCE(load.total_semanal, 0) > teachers.max_horas_semanales';
        $query->selectRaw('COALESCE(load.total_semanal, 0)::numeric(12,2) as total_semanal')
            ->selectRaw("CASE WHEN {$exceeds} THEN 1 ELSE 0 END as excede_limite")
            ->selectRaw('CASE WHEN teachers.max_horas_semanales IS NULL THEN NULL
                ELSE GREATEST(teachers.max_horas_semanales - COALESCE(load.total_semanal, 0), 0)::numeric(12,2) END as horas_disponibles');
        if (isset($filters['teacher_id'])) {
            $query->where('teachers.id', $filters['teacher_id']);
        }
        if (isset($filters['estado'])) {
            $query->where('teachers.estado', $filters['estado']);
        }
        if (isset($filters['excede_limite'])) {
            $query->whereRaw((bool) $filters['excede_limite'] ? "({$exceeds})" : "NOT ({$exceeds})");
        }
        if (isset($filters['q'])) {
            $pattern = '%'.addcslashes($filters['q'], '\\%_').'%';
            $query->where(fn (EloquentBuilder $search) => $search->whereLike('teachers.codigo_institucional', $pattern)
                ->orWhereRaw("concat_ws(' ', teachers.nombres, teachers.apellidos) ILIKE ?", [$pattern]));
        }

        return $query;
    }

    /** @param array<string, mixed> $filters */
    public function courses(AcademicPeriod $period, array $filters = []): Builder
    {
        $query = DB::table('courses as c')->leftJoinSub($this->sums($period, 'course_id'), 'load', 'load.course_id', '=', 'c.id')
            ->leftJoinSub($this->demand->courses($period)->reorder(), 'd', 'd.course_id', '=', 'c.id')
            ->select('c.id as course_id', 'c.codigo', 'c.nombre', 'c.ciclo', 'c.estado')
            ->selectRaw('COALESCE(load.asignaciones, 0)::integer as cantidad_docentes')
            ->selectRaw('COALESCE(load.total_semanal, 0)::numeric(12,2) as total_semanal');
        foreach (TeachingHours::FIELDS as $assigned => $available) {
            $query->addSelect('c.'.$available)->selectRaw("COALESCE(load.{$assigned}, 0)::numeric(12,2) as {$assigned}")
                ->selectRaw("GREATEST(c.{$available} - COALESCE(load.{$assigned}, 0), 0)::numeric(12,2) as {$available}_pendientes");
        }
        foreach (['registrada', 'valida', 'pendiente_resultados', 'requiere_ajuste', 'sin_validacion'] as $field) {
            $query->selectRaw("COALESCE(d.{$field}, 0)::integer as demanda_{$field}");
        }
        foreach (['course_id' => 'c.id', 'ciclo' => 'c.ciclo', 'estado' => 'c.estado'] as $filter => $column) {
            if (isset($filters[$filter])) {
                $query->where($column, $filters[$filter]);
            }
        }
        if (isset($filters['q'])) {
            $pattern = '%'.addcslashes($filters['q'], '\\%_').'%';
            $query->where(fn (Builder $search) => $search->whereLike('c.codigo', $pattern)->orWhereLike('c.nombre', $pattern));
        }

        return $query;
    }

    /** @return array<string, mixed> */
    public function summary(AcademicPeriod $period): array
    {
        $totals = DB::table('teaching_assignments')->whereNull('deleted_at')->where('academic_period_id', $period->id)
            ->selectRaw('COUNT(*)::integer as asignaciones, COUNT(DISTINCT teacher_id)::integer as docentes_con_carga,
                COUNT(DISTINCT course_id)::integer as cursos_con_asignacion');
        foreach (array_keys(TeachingHours::FIELDS) as $field) {
            $totals->selectRaw("COALESCE(SUM({$field}), 0)::numeric(12,2) as {$field}");
        }
        $totals->selectRaw('COALESCE(SUM(horas_teoria_asignadas + horas_practica_asignadas + horas_laboratorio_asignadas), 0)::numeric(12,2) as total_semanal');
        $pending = DB::query()->fromSub($this->courses($period), 'report')
            ->selectRaw('COUNT(*) FILTER (WHERE cantidad_docentes = 0)::integer as cursos_sin_asignacion');
        foreach (TeachingHours::FIELDS as $available) {
            $pending->selectRaw("COALESCE(SUM({$available}_pendientes), 0)::numeric(12,2) as {$available}_pendientes");
        }

        return $this->context($period) + ['totales' => (array) $totals->first() + (array) $pending->first() +
            ['docentes_exceden_limite' => $this->teachers($period, ['excede_limite' => true])->count()]];
    }

    private function sums(AcademicPeriod $period, string $group): Builder
    {
        $query = DB::table('teaching_assignments')->whereNull('deleted_at')->where('academic_period_id', $period->id)
            ->select($group)->selectRaw('COUNT(*) as asignaciones')->groupBy($group);
        foreach (array_keys(TeachingHours::FIELDS) as $field) {
            $query->selectRaw("SUM({$field}) as {$field}");
        }

        return $query->selectRaw('SUM(horas_teoria_asignadas + horas_practica_asignadas + horas_laboratorio_asignadas) as total_semanal');
    }
}
