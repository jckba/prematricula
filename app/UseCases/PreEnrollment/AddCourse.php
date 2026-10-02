<?php

namespace App\UseCases\PreEnrollment;

use App\Enums\PreEnrollmentStatus;
use App\Enums\RecordStatus;
use App\Enums\SchedulePreference;
use App\Models\Course;
use App\Models\PreEnrollmentDetail;
use App\Models\PreEnrollmentRequest;
use App\Services\PreEnrollmentPeriodValidator;
use DomainException;

class AddCourse
{
    public function __construct(
        private readonly GetAvailableCourses $getAvailableCourses,
        private readonly PreEnrollmentPeriodValidator $periodValidator
    ) {}

    public function __invoke(
        PreEnrollmentRequest $request,
        Course $course,
        SchedulePreference $preference = SchedulePreference::SIN_PREFERENCIA
    ): PreEnrollmentDetail {
        $request->loadMissing([
            'student',
            'academicPeriod',
            'details.course',
        ]);

        $this->periodValidator->validate(
            $request->academicPeriod
        );

        if ($request->estado !== PreEnrollmentStatus::BORRADOR) {
            throw new DomainException(
                'Solo se pueden modificar solicitudes en estado BORRADOR.'
            );
        }

        if ($course->estado !== RecordStatus::ACTIVO) {
            throw new DomainException(
                'El curso no se encuentra activo.'
            );
        }

        if (
            $request->details
                ->contains('course_id', $course->id)
        ) {
            throw new DomainException(
                'El curso ya fue agregado a la prematrícula.'
            );
        }

        if (
            ! $this->getAvailableCourses
                ->isAvailable($request->student, $course)
        ) {
            throw new DomainException(
                'El estudiante no cumple los requisitos para seleccionar este curso.'
            );
        }

        $currentCredits = $request->details
            ->sum(fn ($detail) => $detail->course->creditos);

        $newTotal = $currentCredits + $course->creditos;

        if (
            $newTotal >
            $request->student->creditos_maximos
        ) {
            throw new DomainException(
                'La selección supera el límite máximo de créditos permitido.'
            );
        }

        return PreEnrollmentDetail::create([
            'pre_enrollment_request_id' => $request->id,
            'course_id' => $course->id,
            'preferencia_turno' => $preference,
        ]);
    }
}
