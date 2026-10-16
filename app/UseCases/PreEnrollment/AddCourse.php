<?php

namespace App\UseCases\PreEnrollment;

use App\Enums\RecordStatus;
use App\Enums\SchedulePreference;
use App\Models\Course;
use App\Models\PreEnrollmentDetail;
use App\Models\PreEnrollmentRequest;
use App\Services\AcademicProcessLock;
use App\Services\PreEnrollmentEditing;
use App\Services\PreEnrollmentPeriodValidator;
use App\Services\RevalidatePreEnrollments;
use DomainException;

class AddCourse
{
    public function __construct(
        private readonly GetAvailableCourses $getAvailableCourses,
        private readonly PreEnrollmentPeriodValidator $periodValidator,
        private readonly AcademicProcessLock $lock,
        private readonly PreEnrollmentEditing $editing,
        private readonly RevalidatePreEnrollments $revalidate,
    ) {}

    public function __invoke(
        PreEnrollmentRequest $request,
        Course $course,
        SchedulePreference $preference = SchedulePreference::SIN_PREFERENCIA
    ): PreEnrollmentDetail {
        return $this->lock->transaction(function () use ($request, $course, $preference): PreEnrollmentDetail {
            $result = $this->add($request->refresh(), $course->refresh(), $preference);
            $this->revalidate->evaluate($request);

            return $result;
        });
    }

    private function add(PreEnrollmentRequest $request, Course $course, SchedulePreference $preference): PreEnrollmentDetail
    {
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
                ->isAvailable($request->student, $course, $request->academicPeriod)
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
