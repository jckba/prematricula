<?php

namespace App\Services;

use App\Enums\AcademicPeriodStatus;
use App\Models\AcademicPeriod;
use DomainException;

class CurrentPreEnrollmentPeriod
{
    public function get(): AcademicPeriod
    {
        $periods = AcademicPeriod::query()
            ->where(
                'estado',
                AcademicPeriodStatus::PREMATRICULA
            )
            ->where(
                'fecha_inicio_prematricula',
                '<=',
                now()
            )
            ->where(
                'fecha_fin_prematricula',
                '>=',
                now()
            )
            ->get();

        if ($periods->isEmpty()) {
            throw new DomainException(
                'No existe un periodo de prematrícula habilitado.'
            );
        }

        if ($periods->count() > 1) {
            throw new DomainException(
                'Existe más de un periodo de prematrícula habilitado.'
            );
        }

        return $periods->first();
    }
}
