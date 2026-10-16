<?php

namespace App\Http\Controllers\Api\Coordinator;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\AcademicConsultationRequest;
use App\Http\Resources\AcademicHistoryResource;
use App\Http\Resources\AcademicPreEnrollmentResource;
use App\Http\Resources\StudentAcademicResource;
use App\Models\AcademicPeriod;
use App\Models\Student;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AcademicConsultationController extends Controller
{
    public function index(AcademicConsultationRequest $request): AnonymousResourceCollection
    {
        $query = Student::query()->select('id', 'codigo_universitario', 'nombres', 'apellidos', 'estado', 'creditos_maximos');
        if ($request->has('codigo_universitario')) {
            $query->where('codigo_universitario', $request->validated('codigo_universitario'));
        }
        if ($request->has('q')) {
            $pattern = '%'.addcslashes($request->validated('q'), '\\%_').'%';
            $query->where(fn (Builder $search) => $search->whereLike('codigo_universitario', $pattern)
                ->orWhereRaw("concat_ws(' ', nombres, apellidos) ILIKE ?", [$pattern]));
        }
        if ($request->has('estado')) {
            $query->where('estado', $request->validated('estado'));
        }

        return StudentAcademicResource::collection($query
            ->orderBy($request->validated('sort', 'codigo_universitario'), $request->validated('direction', 'asc'))
            ->orderBy('id')->paginate($request->integer('per_page', 20))->withQueryString());
    }

    public function show(AcademicConsultationRequest $request, Student $student): JsonResponse
    {
        $period = AcademicPeriod::findOrFail($request->integer('academic_period_id'));
        $histories = $student->academicHistories()->with(['course:id,codigo,nombre', 'academicPeriod:id,codigo'])
            ->whereHas('academicPeriod', fn (Builder $query) => $query->where('fecha_inicio', '<', $period->fecha_inicio->toDateString()))
            ->orderByDesc(AcademicPeriod::query()->select('fecha_inicio')->whereColumn('academic_periods.id', 'academic_histories.academic_period_id'))
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 20))->withQueryString();
        $pagination = $histories->toArray();
        unset($pagination['data']);
        $enrollment = $student->preEnrollmentRequests()->where('academic_period_id', $period->id)
            ->with(['currentRevision:id,pre_enrollment_request_id,revision_number,submitted_at,snapshot',
                'academicValidation', 'details.course:id,codigo,nombre,creditos'])->first();

        return response()->json(['data' => [
            'estudiante' => new StudentAcademicResource($student),
            'periodo' => ['id' => $period->id, 'codigo' => $period->codigo],
            'historial_academico' => ['data' => AcademicHistoryResource::collection($histories->items()), 'paginacion' => $pagination],
            'prematricula' => $enrollment === null ? null : new AcademicPreEnrollmentResource($enrollment),
        ]]);
    }
}
