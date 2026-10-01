<?php

namespace App\Services;

use App\Enums\AcademicPeriodStatus;
use App\Models\AcademicPeriod;
use DomainException;

class CurrentPreEnrollmentPeriod
{
    public function get(): AcademicPeriod
    {
        $period = AcademicPeriod::query()
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
            ->orderBy('fecha_inicio')
            ->first();

        if ($period === null) {
            throw new DomainException('No se encontró un periodo de prematricula habilitado');
        }
        return $period;
    }
}
