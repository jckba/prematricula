<?php

namespace App\UseCases\PreEnrollment;

use App\Enums\PreEnrollmentStatus;
use App\Enums\PreEnrollmentWindowType;
use App\Models\PreEnrollmentRequest;
use App\Services\AcademicProcessLock;
use App\Services\ActivePreEnrollmentWindow;
use App\Services\RecordPreEnrollmentRevision;
use App\Services\RevalidatePreEnrollments;
use DomainException;

class StartPreEnrollmentCorrection
{
    public function __construct(
        private readonly AcademicProcessLock $lock,
        private readonly ActivePreEnrollmentWindow $windows,
        private readonly RecordPreEnrollmentRevision $revisions,
        private readonly RevalidatePreEnrollments $revalidate,
    ) {}

    public function __invoke(PreEnrollmentRequest $request): PreEnrollmentRequest
    {
        return $this->lock->transaction(function () use ($request): PreEnrollmentRequest {
            $request->refresh()->load('academicPeriod');
            $window = $this->windows->forPeriod($request->academicPeriod);
            $resuming = $request->estado === PreEnrollmentStatus::BORRADOR && $request->correction_window_id !== null;
            if ($window?->tipo !== PreEnrollmentWindowType::EXTRAORDINARIA ||
                ($request->estado !== PreEnrollmentStatus::ENVIADA && ! $resuming)) {
                throw new DomainException('La corrección requiere solicitud enviada y ventana extraordinaria activa.');
            }
            if ($request->current_revision_id === null) {
                $request->current_revision_id = $this->revisions->captureLegacy($request)->id;
            }
            $request->fill(['estado' => PreEnrollmentStatus::BORRADOR, 'correction_window_id' => $window->id])->save();
            $this->revalidate->evaluate($request);

            return $request->refresh();
        });
    }
}
