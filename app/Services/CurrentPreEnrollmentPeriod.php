<?php

namespace App\Services;

use App\Models\AcademicPeriod;

class CurrentPreEnrollmentPeriod
{
    public function __construct(private readonly ActivePreEnrollmentWindow $windows) {}

    public function get(): AcademicPeriod
    {
        return $this->windows->currentPeriod();
    }
}
