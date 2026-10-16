<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use App\Http\Resources\PreEnrollmentResource;
use App\Models\PreEnrollmentRequest;
use App\UseCases\PreEnrollment\StartPreEnrollmentCorrection;
use Illuminate\Http\Request;

class PreEnrollmentCorrectionController extends Controller
{
    public function store(Request $request, PreEnrollmentRequest $preEnrollment, StartPreEnrollmentCorrection $start): PreEnrollmentResource
    {
        if ($request->user()->student->id !== $preEnrollment->student_id) {
            abort(404);
        }

        return new PreEnrollmentResource($start($preEnrollment)->load(['student', 'academicPeriod', 'details.course', 'academicValidation', 'currentRevision']));
    }
}
