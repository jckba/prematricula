<?php

namespace App\UseCases\PreEnrollment;

use App\Enums\AcademicHistoryStatus;
use App\Enums\RecordStatus;
use App\Models\Course;
use App\Models\Student;
use Illuminate\Support\Collection;


class GetAvailableCourses
{
    public function __invoke(Student $student): Collection
    {
        $histories = $student->academicHistories()->get();

        $excludedCourseIds = $histories
            ->filter(fn ($history) =>
            in_array(
                $history->estado,
                [
                    AcademicHistoryStatus::APROBADO,
                    AcademicHistoryStatus::EN_CURSO,
                ],
                true
            )
            )
            ->pluck('course_id');

        $courses = Course::query()
            ->where('estado', RecordStatus::ACTIVO)
            ->whereNotIn('id', $excludedCourseIds)
            ->with('prerequisites')
            ->orderBy('ciclo')
            ->orderBy('nombre')
            ->get();

        return $courses->filter(
            function (Course $course) use ($histories) {
                return $this->meetsPrerequisites(
                    $course,
                    $histories
                );
            }
        )->values();
    }

    private function meetsPrerequisites(
        Course     $course,
        Collection $histories
    ): bool
    {
        if ($course->prerequisites->isEmpty()) {
            return true;
        }

        return $course->prerequisites->every(
            function (Course $prerequisite) use ($histories) {
                return $histories->contains(
                    function ($history) use ($prerequisite) {
                        return $history->course_id === $prerequisite->id && in_array($history->estado, [
                                AcademicHistoryStatus::APROBADO,
                                ACademicHistoryStatus::EN_CURSO,
                            ], true);
                    }
                );
            }

        );
    }

    public function isAvailable(
        Student $student,
        Course $course
    ): bool {
        return $this->__invoke($student)
            ->contains('id', $course->id);
    }

}
