<?php

namespace App\Services;

use App\Enums\AcademicPeriodStatus;
use App\Models\AcademicPeriod;
use DomainException;

class PreEnrollmentPeriodValidator
{
    public function validate(AcademicPeriod $period): void
    {
        if ($period->estado !== AcademicPeriodStatus::PREMATRICULA) {
            throw new DomainException(
                'El periodo académico no está habilitado para prematrícula.'
            );
        }

        $now = now();

        if (
            $period->fecha_inicio_prematricula === null ||
            $period->fecha_fin_prematricula === null
        ) {
            throw new DomainException(
                'El periodo no tiene fechas de prematrícula configuradas.'
            );
        }

        if (
            $now->lt($period->fecha_inicio_prematricula) ||
            $now->gt($period->fecha_fin_prematricula)
        ) {
            throw new DomainException(
                'La prematrícula se encuentra fuera del rango de fechas permitido.'
            );
        }
    }
}
