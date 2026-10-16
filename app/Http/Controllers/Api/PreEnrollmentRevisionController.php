<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PreEnrollmentRevisionResource;
use App\Models\PreEnrollmentRequest;
use App\Models\PreEnrollmentRevision;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class PreEnrollmentRevisionController extends Controller
{
    public function index(PreEnrollmentRequest $preEnrollment): AnonymousResourceCollection
    {
        Gate::authorize('view', $preEnrollment);

        return PreEnrollmentRevisionResource::collection($preEnrollment->revisions()->whereNotNull('revision_number')
            ->orderByDesc('revision_number')->paginate(20));
    }

    public function currentReceipt(PreEnrollmentRequest $preEnrollment): Response
    {
        Gate::authorize('view', $preEnrollment);
        $revision = $preEnrollment->currentRevision;
        if ($revision === null) {
            abort(409, 'No hay constancia histórica disponible para esta solicitud.');
        }

        return $this->download($revision);
    }

    public function receipt(PreEnrollmentRequest $preEnrollment, PreEnrollmentRevision $revision): Response
    {
        Gate::authorize('view', $preEnrollment);
        if ($revision->pre_enrollment_request_id !== $preEnrollment->id) {
            abort(404);
        }

        return $this->download($revision);
    }

    private function download(PreEnrollmentRevision $revision): Response
    {
        $binary = $revision->pdf_base64 === null ? false : base64_decode($revision->pdf_base64, true);
        if ($binary === false || $revision->pdf_sha256 === null ||
            ! hash_equals($revision->pdf_sha256, hash('sha256', $binary))) {
            abort(409, 'La revisión no tiene una constancia histórica verificable. No se reconstruye con datos actuales.');
        }

        return response($binary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="constancia-'.$revision->id.'-r'.$revision->revision_number.'.pdf"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
