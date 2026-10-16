<?php

namespace App\Services;

use App\Enums\AcademicPeriodStatus;
use App\Models\AcademicPeriod;
use DomainException;

class PreEnrollmentPeriodValidator
{
    public function __construct(private readonly ActivePreEnrollmentWindow $windows) {}

    public function validate(AcademicPeriod $period): void
    {
        if ($period->estado !== AcademicPeriodStatus::PREMATRICULA) {
            throw new DomainException('El periodo académico no está habilitado para prematrícula.');
        }

        if ($this->windows->forPeriod($period) === null) {
            throw new DomainException('La prematrícula no tiene una ventana activa.');
        }
    }
}
