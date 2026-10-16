<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Resources\AcademicHistoryResource;
use App\Models\AcademicHistory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AcademicHistoryController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'student_id' => ['sometimes', 'integer', 'min:1'], 'academic_period_id' => ['sometimes', 'integer', 'min:1'],
        ]);
        $query = AcademicHistory::with(['student', 'course', 'academicPeriod']);
        if ($request->user()->rol === UserRole::ESTUDIANTE) {
            $query->where('student_id', $request->user()->student->id);
        } elseif (isset($filters['student_id'])) {
            $query->where('student_id', $filters['student_id']);
        }
        if (isset($filters['academic_period_id'])) {
            $query->where('academic_period_id', $filters['academic_period_id']);
        }

        return AcademicHistoryResource::collection($query->orderByDesc('academic_period_id')->orderByDesc('id')->paginate(20));
    }
}
