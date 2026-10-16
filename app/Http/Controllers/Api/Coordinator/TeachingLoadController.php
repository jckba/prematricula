<?php

namespace App\Http\Controllers\Api\Coordinator;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\TeachingQueryRequest;
use App\Http\Resources\CourseTeachingLoadResource;
use App\Http\Resources\TeacherLoadResource;
use App\Http\Resources\TeachingAssignmentResource;
use App\Models\AcademicPeriod;
use App\Models\Course;
use App\Models\Teacher;
use App\Services\TeachingLoadReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Arr;

class TeachingLoadController extends Controller
{
    public function summary(TeachingQueryRequest $request, AcademicPeriod $period, TeachingLoadReport $report): JsonResponse
    {
        return response()->json(['data' => $report->summary($period)]);
    }

    public function teachers(TeachingQueryRequest $request, AcademicPeriod $period, TeachingLoadReport $report): AnonymousResourceCollection
    {
        return TeacherLoadResource::collection($report->teachers($period, $request->validated())->orderBy('teachers.codigo_institucional')->orderBy('teachers.id')
            ->paginate($request->integer('per_page', 20))->withQueryString())->additional(['contexto' => $report->context($period)]);
    }

    public function courses(TeachingQueryRequest $request, AcademicPeriod $period, TeachingLoadReport $report): AnonymousResourceCollection
    {
        return CourseTeachingLoadResource::collection($report->courses($period, $request->validated())->orderBy('c.codigo')->orderBy('c.id')
            ->paginate($request->integer('per_page', 20))->withQueryString())->additional(['contexto' => $report->context($period)]);
    }

    public function teacher(TeachingQueryRequest $request, AcademicPeriod $period, Teacher $teacher, TeachingLoadReport $report): JsonResponse
    {
        return response()->json(['data' => $report->context($period) + [
            'carga' => new TeacherLoadResource($report->teachers($period, ['teacher_id' => $teacher->id])->firstOrFail()),
            ...$this->assignmentPage($request, $report, ['academic_period_id' => $period->id, 'teacher_id' => $teacher->id]),
        ]]);
    }

    public function course(TeachingQueryRequest $request, AcademicPeriod $period, Course $course, TeachingLoadReport $report): JsonResponse
    {
        return response()->json(['data' => $report->context($period) + [
            'carga' => new CourseTeachingLoadResource($report->courses($period, ['course_id' => $course->id])->first()),
            ...$this->assignmentPage($request, $report, ['academic_period_id' => $period->id, 'course_id' => $course->id]),
        ]]);
    }

    /** @param array<string, int> $filters
     * @return array<string, mixed>
     */
    private function assignmentPage(TeachingQueryRequest $request, TeachingLoadReport $report, array $filters): array
    {
        $page = $report->assignments($filters)->orderBy('id')->paginate($request->integer('per_page', 20))->withQueryString();

        return ['asignaciones' => TeachingAssignmentResource::collection($page->items()),
            'paginacion' => Arr::except($page->toArray(), ['data'])];
    }
}
