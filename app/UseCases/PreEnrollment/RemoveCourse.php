<?php

namespace App\UseCases\PreEnrollment;

use App\Enums\PreEnrollmentStatus;
use App\Models\PreEnrollmentDetail;
use App\Models\PreEnrollmentRequest;
use App\Services\PreEnrollmentPeriodValidator;
use DomainException;

class RemoveCourse
{
    public function __construct(
        private readonly PreEnrollmentPeriodValidator $periodValidator
    ) {}

    public function __invoke(
        PreEnrollmentRequest $request,
        PreEnrollmentDetail $detail
    ): void {
        $request->loadMissing('academicPeriod');

        $this->periodValidator->validate(
            $request->academicPeriod
        );

        if ($request->estado !== PreEnrollmentStatus::BORRADOR) {
            throw new DomainException(
                'Solo se pueden modificar solicitudes en estado BORRADOR.'
            );
        }

        if (
            $detail->pre_enrollment_request_id !== $request->id
        ) {
            throw new DomainException(
                'El curso no pertenece a esta solicitud de prematrícula.'
            );
        }

        $detail->delete();
    }
}
