<?php

namespace App\Http\Controllers\Api\Coordinator;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SaveTeachingAssignmentRequest;
use App\Http\Requests\Api\TeachingQueryRequest;
use App\Http\Resources\TeachingAssignmentChangeResource;
use App\Http\Resources\TeachingAssignmentResource;
use App\Models\TeachingAssignment;
use App\Services\TeachingLoadReport;
use App\UseCases\Teaching\ManageTeachingAssignment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TeachingAssignmentController extends Controller
{
    public function index(TeachingQueryRequest $request, TeachingLoadReport $report): AnonymousResourceCollection
    {
        return TeachingAssignmentResource::collection($report->assignments($request->validated())
            ->orderBy('id')->paginate($request->integer('per_page', 20))->withQueryString());
    }

    public function show(TeachingQueryRequest $request, TeachingAssignment $assignment): TeachingAssignmentResource
    {
        return new TeachingAssignmentResource($assignment->load(['teacher', 'course:id,codigo,nombre']));
    }

    public function store(SaveTeachingAssignmentRequest $request, ManageTeachingAssignment $manage): TeachingAssignmentResource
    {
        return new TeachingAssignmentResource($manage->save($request->validated(), $request->user())->load(['teacher', 'course:id,codigo,nombre']));
    }

    public function update(SaveTeachingAssignmentRequest $request, TeachingAssignment $assignment, ManageTeachingAssignment $manage): TeachingAssignmentResource
    {
        return new TeachingAssignmentResource($manage->save($request->validated(), $request->user(), $assignment)->load(['teacher', 'course:id,codigo,nombre']));
    }

    public function destroy(TeachingQueryRequest $request, TeachingAssignment $assignment, ManageTeachingAssignment $manage): JsonResponse
    {
        $manage->delete($assignment, $request->user());

        return response()->json(['message' => 'Asignación dada de baja; su historial se conserva.']);
    }

    public function history(TeachingQueryRequest $request, TeachingAssignment $assignment): AnonymousResourceCollection
    {
        return TeachingAssignmentChangeResource::collection($assignment->changes()->orderBy('id')
            ->paginate($request->integer('per_page', 20))->withQueryString());
    }
}
