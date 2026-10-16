<?php

namespace App\Http\Controllers\Api\Student;

use App\Enums\SchedulePreference;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\AddPreEnrollmentCourseRequest;
use App\Http\Requests\Api\UpdateSchedulePreferenceRequest;
use App\Http\Resources\CourseResource;
use App\Http\Resources\PreEnrollmentResource;
use App\Models\Course;
use App\Models\PreEnrollmentDetail;
use App\Models\PreEnrollmentRequest;
use App\Services\CurrentPreEnrollmentPeriod;
use App\UseCases\PreEnrollment\AddCourse;
use App\UseCases\PreEnrollment\GetAvailableCourses;
use App\UseCases\PreEnrollment\GetOrCreateDraft;
use App\UseCases\PreEnrollment\RemoveCourse;
use App\UseCases\PreEnrollment\SubmitPreEnrollment;
use App\UseCases\PreEnrollment\UpdateSchedulePreference;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class PreEnrollmentController extends Controller
{
    public function availableCourses(
        Request $request,
        GetAvailableCourses $getAvailableCourses,
        CurrentPreEnrollmentPeriod $currentPeriod
    ): JsonResponse {
        $student = $request->user()->student;

        $period = $currentPeriod->get();

        $courses = $getAvailableCourses($student, $period);

        return response()->json([
            'data' => [
                'periodo' => [
                    'id' => $period->id,
                    'codigo' => $period->codigo,
                ],
                'creditos_maximos' => $student->creditos_maximos,
                'cursos' => CourseResource::collection($courses),
            ],
        ]);
    }

    public function show(
        Request $request,
        GetOrCreateDraft $getOrCreateDraft,
        CurrentPreEnrollmentPeriod $currentPeriod
    ): PreEnrollmentResource|JsonResponse {
        $student = $request->user()->student;

        if ($student === null) {
            return response()->json([
                'message' => 'El usuario autenticado no tiene un estudiante asociado.',
            ], 403);
        }

        $filters = $request->validate(['academic_period_id' => ['sometimes', 'integer', 'min:1']]);
        if (isset($filters['academic_period_id'])) {
            $preEnrollment = $student->preEnrollmentRequests()->where('academic_period_id', $filters['academic_period_id'])->firstOrFail();
        } else {
            try {
                $period = $currentPeriod->get();
                $preEnrollment = $student->preEnrollmentRequests()->where('academic_period_id', $period->id)->first()
                    ?? $getOrCreateDraft($student, $period);
            } catch (DomainException $exception) {
                $preEnrollment = $student->preEnrollmentRequests()->orderByDesc('id')->first();
                if ($preEnrollment === null) {
                    throw $exception;
                }
            }
        }

        $preEnrollment->load([
            'student',
            'academicPeriod',
            'details.course',
            'academicValidation',
            'currentRevision',
        ]);

        return new PreEnrollmentResource(
            $preEnrollment
        );
    }

    public function store(Request $request, GetOrCreateDraft $getOrCreateDraft, CurrentPreEnrollmentPeriod $currentPeriod): PreEnrollmentResource
    {
        $preEnrollment = $getOrCreateDraft($request->user()->student, $currentPeriod->get());

        return new PreEnrollmentResource($preEnrollment->load(['student', 'academicPeriod', 'details.course', 'academicValidation', 'currentRevision']));
    }

    public function detail(PreEnrollmentRequest $preEnrollment): PreEnrollmentResource
    {
        Gate::authorize('view', $preEnrollment);

        return new PreEnrollmentResource($preEnrollment->load(['student', 'academicPeriod', 'details.course', 'academicValidation', 'currentRevision']));
    }

    public function addCourse(
        AddPreEnrollmentCourseRequest $request,
        AddCourse $addCourse,
        GetOrCreateDraft $getOrCreateDraft,
        CurrentPreEnrollmentPeriod $currentPeriod
    ): JsonResponse {
        $student = $request->user()->student;

        if ($student === null) {
            return response()->json([
                'message' => 'El usuario autenticado no tiene un estudiante asociado.',
            ], 403);
        }

        $period = $currentPeriod->get();

        $preEnrollment = $getOrCreateDraft(
            $student,
            $period
        );

        $course = Course::findOrFail(
            $request->integer('course_id')
        );

        $preference = $request->filled(
            'preferencia_turno'
        )
            ? SchedulePreference::from(
                $request->string(
                    'preferencia_turno'
                )->toString()
            )
            : SchedulePreference::SIN_PREFERENCIA;

        $detail = $addCourse(
            $preEnrollment,
            $course,
            $preference
        );

        return response()->json([
            'message' => 'Curso agregado correctamente.',
            'data' => [
                'detail_id' => $detail->id,
            ],
        ], 201);
    }

    public function removeCourse(
        Request $request,
        PreEnrollmentDetail $detail,
        RemoveCourse $removeCourse
    ): JsonResponse {
        $student = $request->user()->student;

        if ($student === null) {
            return response()->json([
                'message' => 'El usuario autenticado no tiene un estudiante asociado.',
            ], 403);
        }

        $preEnrollment = $detail
            ->preEnrollmentRequest;

        if ($preEnrollment->student_id !== $student->id) {
            abort(404);
        }

        $removeCourse(
            $preEnrollment,
            $detail
        );

        return response()->json([
            'message' => 'Curso eliminado correctamente.',
        ]);
    }

    public function updatePreference(
        UpdateSchedulePreferenceRequest $request,
        PreEnrollmentDetail $detail,
        UpdateSchedulePreference $updatePreference
    ): JsonResponse {
        $student = $request->user()->student;

        if ($student === null) {
            return response()->json([
                'message' => 'El usuario autenticado no tiene un estudiante asociado.',
            ], 403);
        }

        if (
            $detail
                ->preEnrollmentRequest
                ->student_id !== $student->id
        ) {
            abort(404);
        }

        $preference = SchedulePreference::from(
            $request->string(
                'preferencia_turno'
            )->toString()
        );

        $updated = $updatePreference(
            $detail,
            $preference
        );

        return response()->json([
            'message' => 'Preferencia actualizada correctamente.',

            'data' => [
                'id' => $updated->id,
                'preferencia_turno' => $updated->preferencia_turno->value,
            ],
        ]);
    }

    public function submit(
        Request $request,
        SubmitPreEnrollment $submitPreEnrollment,
        CurrentPreEnrollmentPeriod $currentPeriod
    ): PreEnrollmentResource|JsonResponse {
        $student = $request->user()->student;

        if ($student === null) {
            return response()->json([
                'message' => 'El usuario autenticado no tiene un estudiante asociado.',
            ], 403);
        }

        $period = $currentPeriod->get();

        $preEnrollment = PreEnrollmentRequest::query()
            ->where('student_id', $student->id)
            ->where(
                'academic_period_id',
                $period->id
            )
            ->first();

        if ($preEnrollment === null) {
            return response()->json([
                'message' => 'No existe una prematrícula para enviar.',
            ], 404);
        }

        $preEnrollment = $submitPreEnrollment(
            $preEnrollment
        );

        $preEnrollment->load([
            'student',
            'academicPeriod',
            'details.course',
            'academicValidation',
            'currentRevision',
        ]);

        return new PreEnrollmentResource(
            $preEnrollment
        );
    }

    public function history(
        Request $request
    ): AnonymousResourceCollection {
        $student = $request->user()->student;

        $requests = PreEnrollmentRequest::query()
            ->where('student_id', $student->id)
            ->with([
                'student',
                'academicPeriod',
                'details.course',
                'academicValidation',
                'currentRevision',
                'reviewer',
            ])
            ->orderByDesc('created_at')
            ->paginate(10);

        return PreEnrollmentResource::collection(
            $requests
        );
    }
}
