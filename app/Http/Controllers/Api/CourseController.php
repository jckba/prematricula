<?php

namespace App\Http\Controllers\Api;

use App\Enums\RecordStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ListCoursesRequest;
use App\Http\Requests\Api\UpdateCourseRequest;
use App\Http\Resources\CourseResource;
use App\Models\Course;
use App\UseCases\Curriculum\UpdateCourse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class CourseController extends Controller
{
    public function index(ListCoursesRequest $request): AnonymousResourceCollection
    {
        $query = Course::query()->with('prerequisites');

        if ($request->user()->rol === UserRole::ESTUDIANTE) {
            $query->where('estado', RecordStatus::ACTIVO);
        } elseif ($request->filled('estado')) {
            $query->where('estado', $request->validated('estado'));
        }

        if ($request->filled('ciclo')) {
            $query->where('ciclo', $request->integer('ciclo'));
        }

        if ($request->filled('q')) {
            $search = '%'.addcslashes($request->validated('q'), '\\%_').'%';
            $query->where(fn (Builder $query): Builder => $query
                ->where('codigo', 'ilike', $search)
                ->orWhere('nombre', 'ilike', $search));
        }

        return CourseResource::collection($query->orderBy('ciclo')->orderBy('codigo')->orderBy('id')
            ->paginate($request->integer('per_page', 20)));
    }

    public function show(Course $course): CourseResource
    {
        Gate::authorize('view', $course);

        return new CourseResource($course->load('prerequisites'));
    }

    public function update(UpdateCourseRequest $request, Course $course, UpdateCourse $updateCourse): CourseResource
    {
        return new CourseResource($updateCourse($course, $request->validated()));
    }
}
