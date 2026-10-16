<?php

namespace App\Services;

use App\Enums\AcademicPeriodStatus;
use App\Models\AcademicPeriod;
use App\Models\PreEnrollmentWindow;
use DomainException;

class ActivePreEnrollmentWindow
{
    public function forPeriod(AcademicPeriod $period): ?PreEnrollmentWindow
    {
        if ($period->estado !== AcademicPeriodStatus::PREMATRICULA) {
            return null;
        }
        $target = $this->nextTarget();
        if ($target['has_current'] && $period->fecha_inicio->toDateString() !== $target['next_start']) {
            return null;
        }

        return $period->windows()->where('fecha_inicio', '<=', now()->toIso8601String())
            ->where('fecha_fin', '>', now()->toIso8601String())->first();
    }

    public function currentPeriod(): AcademicPeriod
    {
        $periods = AcademicPeriod::query()->where('estado', AcademicPeriodStatus::PREMATRICULA)
            ->whereHas('windows', fn ($query) => $query->where('fecha_inicio', '<=', now()->toIso8601String())
                ->where('fecha_fin', '>', now()->toIso8601String()))->get();
        $target = $this->nextTarget();

        if ($target['has_current']) {
            $periods = $periods->filter(fn (AcademicPeriod $period): bool => $period->fecha_inicio->toDateString() === $target['next_start']);
        }

        if ($periods->count() !== 1) {
            throw new DomainException($periods->isEmpty()
                ? 'No existe un periodo de prematrícula habilitado.'
                : 'Existe más de un periodo de prematrícula habilitado.');
        }

        return $periods->first();
    }

    /** @return array{has_current: bool, next_start: string|null} */
    private function nextTarget(): array
    {
        $currentStart = AcademicPeriod::where('estado', AcademicPeriodStatus::EN_CURSO)->max('fecha_inicio');
        if ($currentStart === null) {
            return ['has_current' => false, 'next_start' => null];
        }

        return ['has_current' => true, 'next_start' => AcademicPeriod::whereIn('estado', [AcademicPeriodStatus::PLANIFICACION, AcademicPeriodStatus::PREMATRICULA])
            ->where('fecha_inicio', '>', $currentStart)->min('fecha_inicio')];
    }
}
