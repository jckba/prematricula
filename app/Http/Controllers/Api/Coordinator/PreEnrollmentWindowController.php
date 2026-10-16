<?php

namespace App\Http\Controllers\Api\Coordinator;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StorePreEnrollmentWindowRequest;
use App\Http\Resources\PreEnrollmentWindowResource;
use App\Models\AcademicPeriod;
use App\UseCases\AcademicPeriods\CreatePreEnrollmentWindow;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class PreEnrollmentWindowController extends Controller
{
    public function index(AcademicPeriod $period): AnonymousResourceCollection
    {
        Gate::authorize('view', $period);

        return PreEnrollmentWindowResource::collection($period->windows()->orderBy('fecha_inicio')->get());
    }

    public function store(StorePreEnrollmentWindowRequest $request, AcademicPeriod $period, CreatePreEnrollmentWindow $create): PreEnrollmentWindowResource
    {
        return new PreEnrollmentWindowResource($create($period, $request->validated(), $request->user()));
    }
}
