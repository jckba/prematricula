<?php

namespace App\Http\Controllers\Api\Coordinator;

use App\Enums\PreEnrollmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ListPreEnrollmentsRequest;
use App\Http\Resources\PreEnrollmentResource;
use App\Models\PreEnrollmentRequest;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PreEnrollmentController extends Controller
{
    public function index(
        ListPreEnrollmentsRequest $request
    ): AnonymousResourceCollection {
        $query = PreEnrollmentRequest::query()
            ->with([
                'student',
                'academicPeriod',
                'details.course',
                'academicValidation',
                'currentRevision',
            ]);

        $status = $request->validated('estado');

        if ($status !== null) {
            $query->where('estado', $status);
        } else {
            $query->where(fn ($query) => $query->where('estado', PreEnrollmentStatus::ENVIADA)
                ->orWhere(fn ($draft) => $draft->where('estado', PreEnrollmentStatus::BORRADOR)->whereNotNull('current_revision_id')));
        }

        return PreEnrollmentResource::collection(
            $query
                ->orderByDesc('fecha_envio')
                ->paginate(20)
        );
    }

    public function show(
        PreEnrollmentRequest $preEnrollment
    ): PreEnrollmentResource {
        $preEnrollment->load([
            'student',
            'academicPeriod',
            'details.course',
            'academicValidation',
            'currentRevision',
            'reviewer',
        ]);

        return new PreEnrollmentResource(
            $preEnrollment
        );
    }
}
