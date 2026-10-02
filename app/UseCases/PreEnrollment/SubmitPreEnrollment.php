<?php

namespace App\UseCases\PreEnrollment;

use App\Enums\PreEnrollmentStatus;
use App\Models\PreEnrollmentRequest;
use App\Services\PreEnrollmentPeriodValidator;
use DomainException;
use Illuminate\Support\Facades\DB;

class SubmitPreEnrollment
{
    public function __construct(
        private readonly GetAvailableCourses $getAvailableCourses,
        private readonly PreEnrollmentPeriodValidator $periodValidator
    ) {}

    public function __invoke(
        PreEnrollmentRequest $request
    ): PreEnrollmentRequest {
        return DB::transaction(function () use ($request) {

            $request->load([
                'student',
                'academicPeriod',
                'details.course',
            ]);

            $this->periodValidator->validate(
                $request->academicPeriod
            );

            if ($request->estado !== PreEnrollmentStatus::BORRADOR) {
                throw new DomainException(
                    'Solo una solicitud en borrador puede ser enviada.'
                );
            }

            if ($request->details->isEmpty()) {
                throw new DomainException(
                    'Debe seleccionar al menos un curso.'
                );
            }

            $totalCredits = $request->details
                ->sum(fn ($detail) => $detail->course->creditos);

            if ($totalCredits > $request->student->creditos_maximos) {
                throw new DomainException(
                    'La solicitud supera el límite máximo de créditos.'
                );
            }

            foreach ($request->details as $detail) {
                if (
                    ! $this->getAvailableCourses->isAvailable(
                        $request->student,
                        $detail->course
                    )
                ) {
                    throw new DomainException(
                        "El curso {$detail->course->nombre} ya no cumple los requisitos de prematrícula."
                    );
                }
            }

            $request->update([
                'estado' => PreEnrollmentStatus::ENVIADA,
                'fecha_envio' => now(),
            ]);

            return $request->refresh();
        });
    }
}
