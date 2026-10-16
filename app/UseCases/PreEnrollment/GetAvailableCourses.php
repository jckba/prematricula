<?php

namespace App\UseCases\PreEnrollment;

use App\Enums\AcademicValidationStatus;
use App\Enums\RecordStatus;
use App\Models\AcademicPeriod;
use App\Models\Course;
use App\Models\Student;
use App\Services\AcademicEligibility;
use Illuminate\Support\Collection;

class GetAvailableCourses
{
    public function __construct(private readonly AcademicEligibility $eligibility) {}

    public function __invoke(Student $student, ?AcademicPeriod $period = null): Collection
    {
        $histories = $this->eligibility->histories($student, $period);

        return Course::query()->where('estado', RecordStatus::ACTIVO)->with('prerequisites')
            ->orderBy('ciclo')->orderBy('nombre')->get()->filter(
                fn (Course $course): bool => $this->eligibility->evaluate($course, $histories)['estado'] !== AcademicValidationStatus::REQUIERE_AJUSTE->value
            )->values();
    }

    public function isAvailable(Student $student, Course $course, ?AcademicPeriod $period = null): bool
    {
        return $this->__invoke($student, $period)->contains('id', $course->id);
    }
}
