<?php

namespace App\UseCases\PreEnrollment;

use App\Enums\PreEnrollmentStatus;
use App\Models\PreEnrollmentRequest;
use App\Models\User;
use App\Services\PreEnrollmentReviewValidator;
use DomainException;
use Illuminate\Support\Facades\DB;

class RejectPreEnrollment
{
    public function __construct(
        private readonly PreEnrollmentReviewValidator $reviewValidator
    )
    {
    }

    public function __invoke(
        PreEnrollmentRequest $request,
        User                 $reviewer,
        string               $comment
    ): PreEnrollmentRequest
    {
        $comment = trim($comment);
        if ($comment === '') {
            throw new DomainException(
                'Debe indicar el motivo del rechazo.'
            );
        }
        return DB::transaction(function () use (
            $request,
            $reviewer,
            $comment
        ) {
            $lockedRequest = PreEnrollmentRequest::query()
                ->whereKey($request->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->reviewValidator->validate(
                $lockedRequest,
                $reviewer
            );

            $lockedRequest->update([
                'estado' => PreEnrollmentStatus::RECHAZADA,
                'fecha_revision' => now(),
                'reviewed_by' => $reviewer->id,
                'comentarios_coordinador' => $comment,
            ]);

            return $lockedRequest->refresh();
        });
    }
}
