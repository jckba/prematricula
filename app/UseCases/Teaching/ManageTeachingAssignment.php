<?php

namespace App\UseCases\Teaching;

use App\Enums\AcademicPeriodStatus;
use App\Enums\RecordStatus;
use App\Enums\UserRole;
use App\Models\AcademicPeriod;
use App\Models\Course;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\User;
use App\Services\AcademicProcessLock;
use App\Services\TeachingHours;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ManageTeachingAssignment
{
    public function __construct(private readonly AcademicProcessLock $lock) {}

    /** @param array<string, mixed> $data */
    public function save(array $data, User $actor, ?TeachingAssignment $assignment = null): TeachingAssignment
    {
        $this->authorize($actor);

        return $this->lock->transaction(function () use ($data, $actor, $assignment): TeachingAssignment {
            $current = $assignment === null ? null : TeachingAssignment::query()->lockForUpdate()->findOrFail($assignment->id);
            if ($current !== null && array_intersect(array_keys($data), ['academic_period_id', 'course_id', 'teacher_id']) !== []) {
                throw ValidationException::withMessages(['assignment' => 'Los vínculos son inmutables. Dé de baja y cree otra asignación durante la planificación.']);
            }
            $fields = ['academic_period_id', 'course_id', 'teacher_id', ...array_keys(TeachingHours::FIELDS)];
            $merged = array_replace(array_fill_keys(array_keys(TeachingHours::FIELDS), 0), $current?->only($fields) ?? [], Arr::only($data, $fields));
            Validator::make($merged, array_fill_keys(array_keys(TeachingHours::FIELDS), TeachingHours::validationRules()))->validate();
            $period = AcademicPeriod::query()->lockForUpdate()->findOrFail($merged['academic_period_id']);
            $this->assertOpen($period);
            $course = Course::query()->lockForUpdate()->findOrFail($merged['course_id']);
            $teacher = Teacher::query()->lockForUpdate()->findOrFail($merged['teacher_id']);
            if ($teacher->estado !== RecordStatus::ACTIVO || $course->estado !== RecordStatus::ACTIVO) {
                throw ValidationException::withMessages(['assignment' => 'El docente y el curso deben estar activos.']);
            }
            $others = TeachingAssignment::query()->where('academic_period_id', $period->id)
                ->when($current !== null, fn ($query) => $query->where('id', '!=', $current->id));
            if ((clone $others)->where('course_id', $course->id)->where('teacher_id', $teacher->id)->exists()) {
                throw ValidationException::withMessages(['teacher_id' => 'Ya existe una asignación vigente para este docente, curso y período.']);
            }
            foreach (TeachingHours::FIELDS as $assigned => $available) {
                $merged[$assigned] = TeachingHours::decimal(TeachingHours::hundredths($merged[$assigned] ?? 0));
                $used = (clone $others)->where('course_id', $course->id)->sum($assigned);
                if (TeachingHours::hundredths($used) + TeachingHours::hundredths($merged[$assigned]) > TeachingHours::hundredths($course->$available)) {
                    throw ValidationException::withMessages([$assigned => 'Las horas asignadas superan las horas semanales del curso para este tipo.']);
                }
            }
            $total = TeachingHours::total($merged);
            if ($total === 0) {
                throw ValidationException::withMessages(['horas' => 'Debe asignar al menos un tipo de hora mayor que cero.']);
            }
            $load = (clone $others)->where('teacher_id', $teacher->id)->toBase()
                ->selectRaw('COALESCE(SUM(horas_teoria_asignadas + horas_practica_asignadas + horas_laboratorio_asignadas), 0) as total')->first()->total;
            if ($teacher->max_horas_semanales !== null && TeachingHours::hundredths($load) + $total > TeachingHours::hundredths($teacher->max_horas_semanales)) {
                throw ValidationException::withMessages(['teacher_id' => 'La asignación supera el máximo semanal del docente en este período.']);
            }
            $before = $current === null ? null : $this->snapshot($current);
            if ($current === null) {
                $current = TeachingAssignment::create($merged + ['created_by' => $actor->id, 'updated_by' => $actor->id]);
            } else {
                $current->fill($merged + ['updated_by' => $actor->id])->save();
            }
            $this->audit($current, $actor, $before === null ? 'CREADA' : 'ACTUALIZADA', $before);

            return $current;
        });
    }

    public function delete(TeachingAssignment $assignment, User $actor): void
    {
        $this->authorize($actor);
        $this->lock->transaction(function () use ($assignment, $actor): void {
            $current = TeachingAssignment::query()->lockForUpdate()->findOrFail($assignment->id);
            $this->assertOpen(AcademicPeriod::query()->lockForUpdate()->findOrFail($current->academic_period_id));
            $before = $this->snapshot($current);
            $current->update(['updated_by' => $actor->id]);
            $current->delete();
            $this->audit($current, $actor, 'ELIMINADA', $before);
        });
    }

    private function authorize(User $actor): void
    {
        abort_unless($actor->rol === UserRole::COORDINADOR && $actor->estado === RecordStatus::ACTIVO, 403);
    }

    private function assertOpen(AcademicPeriod $period): void
    {
        if ($period->estado === AcademicPeriodStatus::CERRADO) {
            abort(409, 'El período está cerrado; sus asignaciones se conservan sin modificaciones ni bajas.');
        }
    }

    /** @return array<string, mixed> */
    private function snapshot(TeachingAssignment $assignment): array
    {
        return $assignment->only(['academic_period_id', 'course_id', 'teacher_id', ...array_keys(TeachingHours::FIELDS), 'created_by', 'updated_by', 'deleted_at']);
    }

    /** @param array<string, mixed>|null $before */
    private function audit(TeachingAssignment $assignment, User $actor, string $action, ?array $before): void
    {
        $assignment->changes()->create(['actor_id' => $actor->id, 'accion' => $action,
            'before' => $before, 'after' => $this->snapshot($assignment), 'created_at' => now()]);
    }
}
