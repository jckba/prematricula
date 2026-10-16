<?php

namespace App\Services;

use App\Enums\AcademicPeriodStatus;
use App\Enums\AcademicValidationStatus;
use App\Enums\PreEnrollmentStatus;
use App\Models\Course;
use App\Models\PreEnrollmentRequest;
use App\Models\PreEnrollmentValidation;

class RevalidatePreEnrollments
{
    public function __construct(private readonly AcademicEligibility $eligibility) {}

    public function evaluate(PreEnrollmentRequest $request): PreEnrollmentValidation
    {
        $request->load(['student', 'academicPeriod', 'details.course.prerequisites', 'currentRevision']);
        $histories = $this->eligibility->histories($request->student, $request->academicPeriod);
        $selection = $request->currentRevision === null ? $request->details->pluck('course') : Course::query()
            ->whereIn('id', array_column($request->currentRevision->snapshot['detalles'] ?? [], 'course_id'))
            ->with('prerequisites')->orderBy('codigo')->get();
        $courses = $selection->map(fn (Course $course): array => $this->eligibility->evaluate($course, $histories))->all();
        $states = array_column($courses, 'estado');
        $state = in_array(AcademicValidationStatus::REQUIERE_AJUSTE->value, $states, true)
            ? AcademicValidationStatus::REQUIERE_AJUSTE
            : (in_array(AcademicValidationStatus::PENDIENTE_RESULTADOS->value, $states, true)
                ? AcademicValidationStatus::PENDIENTE_RESULTADOS : AcademicValidationStatus::VALIDA);

        $validation = PreEnrollmentValidation::updateOrCreate(['pre_enrollment_request_id' => $request->id],
            ['estado' => $state, 'cursos' => $courses, 'evaluated_at' => now(), 'revision_id' => $request->current_revision_id]);
        $request->setRelation('academicValidation', $validation);

        return $validation;
    }

    /** @param list<int> $studentIds */
    public function forStudents(array $studentIds): void
    {
        PreEnrollmentRequest::query()->whereIn('student_id', $studentIds)
            ->whereIn('estado', [PreEnrollmentStatus::BORRADOR, PreEnrollmentStatus::ENVIADA])
            ->whereHas('academicPeriod', fn ($query) => $query->where('estado', '!=', AcademicPeriodStatus::CERRADO))
            ->orderBy('id')->chunkById(100, function ($requests): void {
                foreach ($requests as $request) {
                    $this->evaluate($request);
                }
            });
    }
}
