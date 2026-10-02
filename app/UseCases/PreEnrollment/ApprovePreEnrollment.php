<?php

namespace App\UseCases\PreEnrollment;

use App\Enums\PreEnrollmentStatus;
use App\Models\PreEnrollmentRequest;
use App\Models\User;
use App\Services\PreEnrollmentReviewValidator;
use Illuminate\Support\Facades\DB;

class ApprovePreEnrollment
{
    public function __construct(
        private readonly PreEnrollmentReviewValidator $reviewValidator
    ) {}

    public function __invoke(
        PreEnrollmentRequest $request,
        User $reviewer
    ): PreEnrollmentRequest {
        return DB::transaction(function () use ($request, $reviewer) {

            $lockedRequest = PreEnrollmentRequest::query()
                ->whereKey($request->id)
                ->lockForUpdate()
                ->FirstOrFail();

            $this->reviewValidator->validate($lockedRequest, $reviewer);

            $lockedRequest->update([
                'estado' => PreEnrollmentStatus::APROBADA,
                'fecha_revision' => now(),
                'reviewed_by' => $reviewer->id,
                'comentarios_coordinador' => null,
            ]);

            return $lockedRequest->refresh();
        });
    }
}
