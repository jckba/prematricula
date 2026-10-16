<?php

namespace App\Services;

use App\Enums\AcademicHistoryStatus;
use App\Enums\AcademicValidationStatus;
use App\Enums\RecordStatus;
use App\Models\AcademicHistory;
use App\Models\AcademicPeriod;
use App\Models\Course;
use App\Models\Student;
use Illuminate\Support\Collection;

class AcademicEligibility
{
    /** @return Collection<int, AcademicHistory> */
    public function histories(Student $student, ?AcademicPeriod $target = null): Collection
    {
        return $student->academicHistories()->with('academicPeriod')
            ->when($target !== null, fn ($query) => $query->whereHas('academicPeriod',
                fn ($periods) => $periods->where('fecha_inicio', '<', $target->fecha_inicio->toDateString())))
            ->get()->sortByDesc(fn (AcademicHistory $history): string => $history->academicPeriod->fecha_inicio->toDateString().'-'.str_pad((string) $history->id, 20, '0', STR_PAD_LEFT))->values();
    }

    /** Official approval in any earlier attempt prevails; otherwise use the latest earlier attempt.
     * @param  Collection<int, AcademicHistory>  $histories
     */
    public function status(int $courseId, Collection $histories): ?AcademicHistoryStatus
    {
        $attempts = $histories->where('course_id', $courseId);
        if ($attempts->contains(fn (AcademicHistory $history): bool => $history->estado === AcademicHistoryStatus::APROBADO)) {
            return AcademicHistoryStatus::APROBADO;
        }

        return $attempts->first()?->estado;
    }

    /** @param Collection<int, AcademicHistory> $histories
     * @return array{course_id: int, codigo: string, estado: string, motivo: string|null, prerrequisitos: list<array<string, mixed>>}
     */
    public function evaluate(Course $course, Collection $histories): array
    {
        $ownStatus = $this->status($course->id, $histories);
        $blocked = $course->estado !== RecordStatus::ACTIVO ||
            in_array($ownStatus, [AcademicHistoryStatus::APROBADO, AcademicHistoryStatus::EN_CURSO], true);
        $state = $blocked ? AcademicValidationStatus::REQUIERE_AJUSTE : AcademicValidationStatus::VALIDA;
        $prerequisites = [];

        foreach ($course->prerequisites as $prerequisite) {
            $status = $this->status($prerequisite->id, $histories);
            $prerequisiteState = match ($status) {
                AcademicHistoryStatus::APROBADO => AcademicValidationStatus::VALIDA,
                AcademicHistoryStatus::EN_CURSO => AcademicValidationStatus::PENDIENTE_RESULTADOS,
                default => AcademicValidationStatus::REQUIERE_AJUSTE,
            };
            if ($prerequisiteState === AcademicValidationStatus::REQUIERE_AJUSTE ||
                ($state === AcademicValidationStatus::VALIDA && $prerequisiteState === AcademicValidationStatus::PENDIENTE_RESULTADOS)) {
                $state = $prerequisiteState;
            }
            $prerequisites[] = ['id' => $prerequisite->id, 'codigo' => $prerequisite->codigo,
                'estado_historial' => $status?->value, 'estado_validacion' => $prerequisiteState->value];
        }

        return ['course_id' => $course->id, 'codigo' => $course->codigo, 'estado' => $state->value,
            'motivo' => $blocked ? 'El curso está inactivo, aprobado o en curso.' : null, 'prerrequisitos' => $prerequisites];
    }
}
