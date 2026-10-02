<?php

namespace App\UseCases\PreEnrollment;

use App\Enums\PreEnrollmentStatus;
use App\Enums\SchedulePreference;
use App\Models\PreEnrollmentDetail;
use App\Services\PreEnrollmentPeriodValidator;
use DomainException;

class UpdateSchedulePreference
{
    public function __construct(
        private readonly PreEnrollmentPeriodValidator $periodValidator
    ) {}

    public function __invoke(
        PreEnrollmentDetail $detail,
        SchedulePreference $preference
    ): PreEnrollmentDetail {
        $detail->loadMissing(
            'preEnrollmentRequest.academicPeriod'
        );

        $request = $detail->preEnrollmentRequest;

        $this->periodValidator->validate(
            $request->academicPeriod
        );

        if ($request->estado !== PreEnrollmentStatus::BORRADOR) {
            throw new DomainException(
                'No se puede modificar una solicitud que ya fue enviada.'
            );
        }

        $detail->update([
            'preferencia_turno' => $preference,
        ]);

        return $detail->refresh();
    }
}
