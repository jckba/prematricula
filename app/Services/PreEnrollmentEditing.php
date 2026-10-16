<?php

namespace App\Services;

use App\Enums\PreEnrollmentStatus;
use App\Enums\PreEnrollmentWindowType;
use App\Models\PreEnrollmentRequest;

class PreEnrollmentEditing
{
    public function __construct(private readonly ActivePreEnrollmentWindow $windows) {}

    public function allowed(PreEnrollmentRequest $request): bool
    {
        if ($request->estado !== PreEnrollmentStatus::BORRADOR) {
            return false;
        }
        if ($request->current_revision_id === null && $request->correction_window_id === null) {
            return true;
        }
        $window = $this->windows->forPeriod($request->academicPeriod);

        return $window?->tipo === PreEnrollmentWindowType::EXTRAORDINARIA &&
            $request->correction_window_id === $window->id;
    }
}
