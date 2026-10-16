<?php

namespace App\UseCases\PreEnrollment;

use App\Enums\PreEnrollmentStatus;
use App\Models\AcademicPeriod;
use App\Models\PreEnrollmentRequest;
use App\Models\Student;
use App\Services\AcademicProcessLock;
use App\Services\PreEnrollmentPeriodValidator;
use App\Services\RevalidatePreEnrollments;

class GetOrCreateDraft
{
    public function __construct(
        private readonly PreEnrollmentPeriodValidator $periodValidator,
        private readonly AcademicProcessLock $lock,
        private readonly RevalidatePreEnrollments $revalidate,
    ) {}

    public function __invoke(
        Student $student,
        AcademicPeriod $period
    ): PreEnrollmentRequest {
        return $this->lock->transaction(function () use ($student, $period): PreEnrollmentRequest {
            $request = $this->get($student, $period);
            $this->revalidate->evaluate($request);

            return $request;
        });
    }

    private function get(Student $student, AcademicPeriod $period): PreEnrollmentRequest
    {
        $this->periodValidator->validate($period->refresh());

        $request = PreEnrollmentRequest::query()
            ->where('student_id', $student->id)
            ->where('academic_period_id', $period->id)
            ->first();

        if ($request !== null) {
            return $request;
        }

        return PreEnrollmentRequest::create([
            'student_id' => $student->id,
            'academic_period_id' => $period->id,
            'estado' => PreEnrollmentStatus::BORRADOR,
        ]);
    }
}
