<?php

namespace App\UseCases\PreEnrollment;

use App\Models\PreEnrollmentDetail;
use App\Models\PreEnrollmentRequest;
use App\Services\AcademicProcessLock;
use App\Services\PreEnrollmentEditing;
use App\Services\PreEnrollmentPeriodValidator;
use App\Services\RevalidatePreEnrollments;
use DomainException;

class RemoveCourse
{
    public function __construct(
        private readonly PreEnrollmentPeriodValidator $periodValidator,
        private readonly AcademicProcessLock $lock,
        private readonly PreEnrollmentEditing $editing,
        private readonly RevalidatePreEnrollments $revalidate,
    ) {}

    public function __invoke(
        PreEnrollmentRequest $request,
        PreEnrollmentDetail $detail
    ): void {
        $this->lock->transaction(function () use ($request, $detail): void {
            $this->remove($request->refresh(), $detail);
            $this->revalidate->evaluate($request);
        });
    }

    private function remove(PreEnrollmentRequest $request, PreEnrollmentDetail $detail): void
    {
        $request->load('academicPeriod');

        $this->periodValidator->validate(
            $request->academicPeriod
        );

        if (! $this->editing->allowed($request)) {
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
