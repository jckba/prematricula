<?php

namespace App\UseCases\PreEnrollment;

use App\Enums\SchedulePreference;
use App\Models\PreEnrollmentDetail;
use App\Services\AcademicProcessLock;
use App\Services\PreEnrollmentEditing;
use App\Services\PreEnrollmentPeriodValidator;
use DomainException;

class UpdateSchedulePreference
{
    public function __construct(
        private readonly PreEnrollmentPeriodValidator $periodValidator,
        private readonly AcademicProcessLock $lock,
        private readonly PreEnrollmentEditing $editing,
    ) {}

    public function __invoke(
        PreEnrollmentDetail $detail,
        SchedulePreference $preference
    ): PreEnrollmentDetail {
        return $this->lock->transaction(fn (): PreEnrollmentDetail => $this->update($detail->refresh(), $preference));
    }

    private function update(PreEnrollmentDetail $detail, SchedulePreference $preference): PreEnrollmentDetail
    {
        $detail->load(
            'preEnrollmentRequest.academicPeriod'
        );

        $request = $detail->preEnrollmentRequest;

        $this->periodValidator->validate(
            $request->academicPeriod
        );

        if (! $this->editing->allowed($request)) {
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
