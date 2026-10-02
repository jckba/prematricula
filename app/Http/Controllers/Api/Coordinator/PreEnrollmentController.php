<?php

namespace App\Http\Controllers\Api\Coordinator;

use App\Enums\PreEnrollmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\RejectPreEnrollmentRequest;
use App\Http\Resources\PreEnrollmentResource;
use App\Models\PreEnrollmentRequest;
use App\UseCases\PreEnrollment\ApprovePreEnrollment;
use App\UseCases\PreEnrollment\RejectPreEnrollment;
use Illuminate\Http\Request;
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
            ]);

        $status = $request->validated('estado');

        if ($status !== null) {
            $query->where('estado', $status);
        } else {
            $query->where(
                'estado',
                PreEnrollmentStatus::ENVIADA
            );
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
            'reviewer',
        ]);

        return new PreEnrollmentResource(
            $preEnrollment
        );
    }

    public function approve(
        Request $request,
        PreEnrollmentRequest $preEnrollment,
        ApprovePreEnrollment $approvePreEnrollment
    ): PreEnrollmentResource {
        $result = $approvePreEnrollment(
            $preEnrollment,
            $request->user()
        );

        $result->load([
            'student',
            'academicPeriod',
            'details.course',
            'reviewer',
        ]);

        return new PreEnrollmentResource(
            $result
        );
    }

    public function reject(
        RejectPreEnrollmentRequest $request,
        PreEnrollmentRequest $preEnrollment,
        RejectPreEnrollment $rejectPreEnrollment
    ): PreEnrollmentResource {
        $result = $rejectPreEnrollment(
            $preEnrollment,
            $request->user(),
            $request->string('comentario')->toString()
        );

        $result->load([
            'student',
            'academicPeriod',
            'details.course',
            'reviewer',
        ]);

        return new PreEnrollmentResource(
            $result
        );
    }
}
