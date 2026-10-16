<?php

namespace App\Services;

use App\Enums\PreEnrollmentStatus;
use App\Models\PreEnrollmentRequest;
use App\Models\PreEnrollmentRevision;
use App\Models\PreEnrollmentWindow;
use Illuminate\Support\Str;

class RecordPreEnrollmentRevision
{
    public function __construct(private readonly PreEnrollmentReceipt $receipt) {}

    public function submit(PreEnrollmentRequest $request, PreEnrollmentWindow $window): PreEnrollmentRevision
    {
        $number = ((int) $request->revisions()->max('revision_number')) + 1;
        $code = 'PRE-'.Str::uuid();
        $snapshot = $this->snapshot($request) + [
            'revision_number' => $number, 'receipt_code' => $code, 'fecha_envio' => now()->toISOString(),
            'window' => ['id' => $window->id, 'tipo' => $window->tipo->value,
                'fecha_inicio' => $window->fecha_inicio->toISOString(), 'fecha_fin' => $window->fecha_fin->toISOString()],
        ];
        $binary = $this->receipt->render($snapshot);
        $revision = $request->revisions()->create([
            'window_id' => $window->id, 'motivo' => 'ENVIO', 'snapshot' => $snapshot,
            'revision_number' => $number, 'receipt_code' => $code, 'schema_version' => 2,
            'submitted_at' => now(), 'pdf_base64' => base64_encode($binary), 'pdf_sha256' => hash('sha256', $binary),
        ]);
        $revision->courses()->attach(array_column($snapshot['detalles'], 'course_id'));

        return $revision;
    }

    /** Capture existing data without pretending it is evidence of an earlier PDF or submission date. */
    public function captureLegacy(PreEnrollmentRequest $request): PreEnrollmentRevision
    {
        $revision = $request->revisions()->create([
            'motivo' => 'LEGACY_CAPTURE', 'schema_version' => 2,
            'snapshot' => $this->snapshot($request) + ['captured_at' => now()->toISOString(),
                'historical_evidence' => false, 'fecha_envio' => $request->fecha_envio?->toISOString()],
        ]);
        $revision->courses()->attach($request->details->pluck('course_id')->all());

        return $revision;
    }

    /** @return array<string, mixed> */
    private function snapshot(PreEnrollmentRequest $request): array
    {
        $request->load(['student', 'academicPeriod', 'details.course.prerequisites']);

        return [
            'schema_version' => 2, 'estado' => PreEnrollmentStatus::ENVIADA->value,
            'institution' => ['university' => config('institution.university'), 'school' => config('institution.school')],
            'student_id' => $request->student_id, 'academic_period_id' => $request->academic_period_id,
            'estudiante' => $request->student->only(['id', 'codigo_universitario', 'nombres', 'apellidos']),
            'periodo' => ['id' => $request->academicPeriod->id, 'codigo' => $request->academicPeriod->codigo,
                'fecha_inicio' => $request->academicPeriod->fecha_inicio->toDateString()],
            'timezone' => config('academic.timezone'),
            'creditos_totales' => $request->details->sum(fn ($detail): int => $detail->course->creditos),
            'detalles' => $request->details->sortBy('course.codigo', SORT_STRING)->map(fn ($detail): array => [
                'detail_id' => $detail->id, 'course_id' => $detail->course_id,
                'codigo' => $detail->course->codigo, 'nombre' => $detail->course->nombre, 'creditos' => $detail->course->creditos,
                'estado' => $detail->course->estado->value, 'preferencia_turno' => $detail->preferencia_turno->value,
                'prerrequisitos' => $detail->course->prerequisites->map(fn ($course): array => ['id' => $course->id, 'codigo' => $course->codigo])->all(),
            ])->values()->all(),
        ];
    }
}
