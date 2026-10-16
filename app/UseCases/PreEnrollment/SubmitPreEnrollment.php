<?php

namespace App\UseCases\PreEnrollment;

use App\Enums\PreEnrollmentStatus;
use App\Models\Course;
use App\Models\PreEnrollmentRequest;
use App\Services\AcademicProcessLock;
use App\Services\ActivePreEnrollmentWindow;
use App\Services\PreEnrollmentEditing;
use App\Services\PreEnrollmentPeriodValidator;
use App\Services\RecordPreEnrollmentRevision;
use App\Services\RevalidatePreEnrollments;
use DomainException;

class SubmitPreEnrollment
{
    public function __construct(
        private readonly GetAvailableCourses $getAvailableCourses,
        private readonly PreEnrollmentPeriodValidator $periodValidator,
        private readonly AcademicProcessLock $lock,
        private readonly PreEnrollmentEditing $editing,
        private readonly RevalidatePreEnrollments $revalidate,
        private readonly RecordPreEnrollmentRevision $revisions,
        private readonly ActivePreEnrollmentWindow $windows,
    ) {}

    public function __invoke(
        PreEnrollmentRequest $request
    ): PreEnrollmentRequest {
        return $this->lock->transaction(function () use ($request) {
            $request->refresh();
            Course::query()->whereIn('id', $request->details()->pluck('course_id'))->orderBy('id')->sharedLock()->get();

            $request->load([
                'student',
                'academicPeriod',
                'details.course',
            ]);

            $this->periodValidator->validate(
                $request->academicPeriod
            );

            if (! $this->editing->allowed($request)) {
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

            $availableCourseIds = ($this->getAvailableCourses)($request->student, $request->academicPeriod)->pluck('id')->all();
            foreach ($request->details as $detail) {
                if (! in_array($detail->course_id, $availableCourseIds, true)) {
                    throw new DomainException(
                        "El curso {$detail->course->nombre} ya no cumple los requisitos de prematrícula."
                    );
                }
            }

            $window = $this->windows->forPeriod($request->academicPeriod);
            if ($window === null) {
                throw new DomainException('La ventana de prematrícula ya no está activa.');
            }
            $revision = $this->revisions->submit($request, $window);
            $request->update([
                'estado' => PreEnrollmentStatus::ENVIADA,
                'fecha_envio' => $revision->submitted_at,
                'correction_window_id' => null,
                'current_revision_id' => $revision->id,
            ]);

            $this->revalidate->evaluate($request);

            return $request->refresh();
        });
    }
}
