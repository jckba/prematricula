<?php

namespace App\Services;

use App\Enums\PreEnrollmentStatus;
use App\Enums\RecordStatus;
use App\Enums\UserRole;
use App\Models\PreEnrollmentRequest;
use App\Models\User;
use DomainException;

class PreEnrollmentReviewValidator
{
    public function validate(
        PreEnrollmentRequest $request,
        User $reviewer
    ): void {
        if ($reviewer->estado !== RecordStatus::ACTIVO) {
            throw new DomainException(
                'El usuario revisor no se encuentra activo.'
            );
        }

        if ($reviewer->rol !== UserRole::COORDINADOR) {
            throw new DomainException(
                'Solo un coordinador puede revisar una prematrícula.'
            );
        }

        if ($request->estado !== PreEnrollmentStatus::ENVIADA) {
            throw new DomainException(
                'Solo se pueden revisar solicitudes en estado ENVIADA.'
            );
        }
    }
}
