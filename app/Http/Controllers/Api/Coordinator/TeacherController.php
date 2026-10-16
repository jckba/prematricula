<?php

namespace App\Http\Controllers\Api\Coordinator;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SaveTeacherRequest;
use App\Http\Requests\Api\TeachingQueryRequest;
use App\Http\Resources\TeacherResource;
use App\Models\Teacher;
use App\UseCases\Teaching\ManageTeacher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TeacherController extends Controller
{
    public function index(TeachingQueryRequest $request): AnonymousResourceCollection
    {
        $query = Teacher::query();
        if ($request->has('estado')) {
            $query->where('estado', $request->validated('estado'));
        }
        if ($request->has('q')) {
            $pattern = '%'.addcslashes($request->validated('q'), '\\%_').'%';
            $query->where(fn (Builder $search) => $search->whereLike('codigo_institucional', $pattern)
                ->orWhereRaw("concat_ws(' ', nombres, apellidos) ILIKE ?", [$pattern]));
        }

        return TeacherResource::collection($query->orderBy('codigo_institucional')->orderBy('id')
            ->paginate($request->integer('per_page', 20))->withQueryString());
    }

    public function show(TeachingQueryRequest $request, Teacher $teacher): TeacherResource
    {
        return new TeacherResource($teacher);
    }

    public function store(SaveTeacherRequest $request, ManageTeacher $manage): TeacherResource
    {
        return new TeacherResource($manage->save($request->validated(), $request->user()));
    }

    public function update(SaveTeacherRequest $request, Teacher $teacher, ManageTeacher $manage): TeacherResource
    {
        return new TeacherResource($manage->save($request->validated(), $request->user(), $teacher));
    }

    public function destroy(TeachingQueryRequest $request, Teacher $teacher, ManageTeacher $manage): TeacherResource
    {
        return new TeacherResource($manage->save(['estado' => 'INACTIVO'], $request->user(), $teacher));
    }
}
