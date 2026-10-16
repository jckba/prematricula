<?php

namespace App\Services;

use App\Enums\AcademicPeriodStatus;
use App\Enums\AcademicValidationStatus;
use App\Enums\PreEnrollmentStatus;
use App\Models\AcademicPeriod;
use App\Models\PreEnrollmentRequest;

class AcademicDemand
{
    public function __construct(private readonly AcademicProcessLock $lock, private readonly RevalidatePreEnrollments $revalidate) {}

    /** @return array{academic_period_id: int, cursos: list<array{course_id: int, codigo: string, valida: int, pendiente_resultados: int, requiere_ajuste: int}>} */
    public function forPeriod(AcademicPeriod $period): array
    {
        return $this->lock->transaction(function () use ($period): array {
            $period->refresh();
            $counts = [];
            PreEnrollmentRequest::with('academicValidation')->where('academic_period_id', $period->id)
                ->whereIn('estado', [PreEnrollmentStatus::BORRADOR, PreEnrollmentStatus::ENVIADA])
                ->where(fn ($query) => $query->where('estado', PreEnrollmentStatus::ENVIADA)->orWhereNotNull('current_revision_id'))
                ->orderBy('id')->chunkById(100, function ($requests) use (&$counts, $period): void {
                    foreach ($requests as $request) {
                        $validation = $period->estado === AcademicPeriodStatus::CERRADO ? $request->academicValidation : $this->revalidate->evaluate($request);
                        if ($validation === null) {
                            continue;
                        }
                        foreach ($validation->cursos as $course) {
                            $counts[$course['course_id']] ??= ['course_id' => $course['course_id'], 'codigo' => $course['codigo'],
                                'valida' => 0, 'pendiente_resultados' => 0, 'requiere_ajuste' => 0];
                            $field = match ($course['estado']) {
                                AcademicValidationStatus::VALIDA->value => 'valida',
                                AcademicValidationStatus::PENDIENTE_RESULTADOS->value => 'pendiente_resultados',
                                default => 'requiere_ajuste',
                            };
                            $counts[$course['course_id']][$field]++;
                        }
                    }
                });
            ksort($counts);

            return ['academic_period_id' => $period->id, 'cursos' => array_values($counts)];
        });
    }
}
