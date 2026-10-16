<?php

namespace App\Http\Controllers\Api\Coordinator;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SaveAcademicPeriodRequest;
use App\Http\Resources\AcademicPeriodResource;
use App\Models\AcademicPeriod;
use App\UseCases\AcademicPeriods\ManageAcademicPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class AcademicPeriodController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', AcademicPeriod::class);

        return AcademicPeriodResource::collection(AcademicPeriod::with('windows')->orderByDesc('fecha_inicio')->orderByDesc('id')->paginate(20));
    }

    public function show(AcademicPeriod $period): AcademicPeriodResource
    {
        Gate::authorize('view', $period);

        return new AcademicPeriodResource($period->load('windows'));
    }

    public function store(SaveAcademicPeriodRequest $request, ManageAcademicPeriod $manage): AcademicPeriodResource
    {
        return new AcademicPeriodResource($manage->save($request->validated(), $request->user())->load('windows'));
    }

    public function update(SaveAcademicPeriodRequest $request, AcademicPeriod $period, ManageAcademicPeriod $manage): AcademicPeriodResource
    {
        return new AcademicPeriodResource($manage->save($request->validated(), $request->user(), $period)->load('windows'));
    }

    public function destroy(AcademicPeriod $period, ManageAcademicPeriod $manage): JsonResponse
    {
        Gate::authorize('delete', $period);
        $manage->delete($period);

        return response()->json(['message' => 'Período eliminado.']);
    }
}
