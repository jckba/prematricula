<?php

namespace App\UseCases\Curriculum;

use App\Exceptions\CurriculumValidationException;
use App\Models\Course;
use App\Models\PreEnrollmentRequest;
use App\Services\AcademicProcessLock;
use App\Services\CurriculumCatalog;
use App\Services\CurriculumValidator;
use App\Services\RevalidatePreEnrollments;
use Illuminate\Database\Eloquent\Collection;

class UpdateCourse
{
    public function __construct(
        private readonly CurriculumCatalog $catalog,
        private readonly CurriculumValidator $validator,
        private readonly AcademicProcessLock $lock,
        private readonly RevalidatePreEnrollments $revalidate,
    ) {}

    /** @param array<string, mixed> $data */
    public function __invoke(Course $course, array $data): Course
    {
        return $this->lock->transaction(fn (): Course => $this->catalog->transaction(function (Collection $courses) use ($course, $data): Course {
            $current = $courses->find($course->id);

            if ($current === null) {
                abort(404);
            }

            $row = array_replace($current->only(['codigo', ...CurriculumCatalog::ACADEMIC_FIELDS]), [
                'row' => 0,
                'prerrequisitos' => $current->prerequisites->pluck('codigo')->all(),
            ], $data);
            $report = $this->validator->validate([$row], $courses);

            if (! $report->isValid()) {
                throw new CurriculumValidationException($report);
            }

            $this->catalog->persist($report->rows, $courses);

            if (isset($data['estado']) && $current->estado->value !== $data['estado']) {
                $current->update(['estado' => $data['estado']]);
                $studentIds = PreEnrollmentRequest::query()->where(function ($requests) use ($current): void {
                    $requests->whereHas('details', fn ($details) => $details->where('course_id', $current->id))
                        ->orWhereHas('currentRevision.courses', fn ($selected) => $selected->where('courses.id', $current->id));
                })->distinct()->pluck('student_id')->all();
                $this->revalidate->forStudents($studentIds);
            }

            return $current->refresh()->load('prerequisites');
        }));
    }
}
