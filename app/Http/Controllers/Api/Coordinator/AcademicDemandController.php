<?php

namespace App\Http\Controllers\Api\Coordinator;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\AcademicDemandRequest;
use App\Models\AcademicPeriod;
use App\Models\Course;
use App\Services\AcademicDemand;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AcademicDemandController extends Controller
{
    public function index(AcademicDemandRequest $request, AcademicPeriod $period, AcademicDemand $demand): JsonResponse
    {
        return response()->json(['data' => $demand->forPeriod($period, $request->validated())]);
    }

    public function summary(AcademicDemandRequest $request, AcademicPeriod $period, AcademicDemand $demand): JsonResponse
    {
        return response()->json(['data' => $demand->summary($period, $request->validated())]);
    }

    public function students(AcademicDemandRequest $request, AcademicPeriod $period, Course $course, AcademicDemand $demand): JsonResponse
    {
        $query = $demand->selections($period, ['course_id' => $course->id])
            ->join('students as student', 'student.id', '=', 's.student_id')
            ->select('student.codigo_universitario', 'student.nombres', 'student.apellidos',
                's.revision_id', 's.preferencia_turno', 's.estado_validacion');
        foreach (['estado_validacion', 'preferencia_turno'] as $field) {
            if ($request->has($field)) {
                $query->where('s.'.$field, $request->validated($field));
            }
        }

        return response()->json($query->orderBy('student.codigo_universitario')->orderBy('student.id')
            ->paginate($request->integer('per_page', 20))->withQueryString());
    }

    public function export(AcademicDemandRequest $request, AcademicPeriod $period, AcademicDemand $demand): StreamedResponse
    {
        $query = $demand->courses($period, $request->validated());

        return response()->streamDownload(function () use ($query, $period): void {
            $output = fopen('php://output', 'wb');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['advertencia', AcademicDemand::NOTICE], escape: '');
            fputcsv($output, ['academic_period_id', 'course_id', 'codigo', 'nombre', 'ciclo', 'creditos', 'registrada',
                'valida', 'pendiente_resultados', 'requiere_ajuste', 'sin_validacion', 'manana', 'tarde', 'sin_preferencia'], escape: '');
            foreach ($query->cursor() as $course) {
                $row = array_map(fn (mixed $value): mixed => is_string($value) && preg_match('/^[\s]*[=+@-]/u', $value)
                    ? "'".$value : $value, array_values((array) $course));
                fputcsv($output, [$period->id, ...$row], escape: '');
            }
            fclose($output);
        }, 'demanda-'.$period->id.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
    }
}
