<?php

namespace App\UseCases\PreEnrollment;

use App\Enums\AcademicPeriodStatus;
use App\Enums\PreEnrollmentStatus;
use App\Models\AcademicPeriod;
use App\Models\PreEnrollmentRequest;
use App\Models\Student;
use App\Services\PreEnrollmentPeriodValidator;
use DomainException;

class GetOrCreateDraft
{
    public function __construct(
        private readonly PreEnrollmentPeriodValidator $periodValidator
    ){

    }
    public function __invoke(
        Student $student,
        AcademicPeriod $period
    ): PreEnrollmentRequest {
        $this ->periodValidator->validate($period);

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
