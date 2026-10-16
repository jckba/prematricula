<?php

namespace App\Http\Resources;

use App\Models\PreEnrollmentDetail;
use App\Models\PreEnrollmentRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

/** @mixin PreEnrollmentRequest */
class AcademicPreEnrollmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $revision = $this->currentRevision;
        $validation = $this->academicValidation;
        $matchesRevision = $validation !== null && $validation->revision_id === $this->current_revision_id;
        $courses = $revision === null ? $this->details->map(fn (PreEnrollmentDetail $detail): array => [
            'course_id' => $detail->course_id, 'codigo' => $detail->course->codigo,
            'nombre' => $detail->course->nombre, 'creditos' => $detail->course->creditos,
            'preferencia_turno' => $detail->preferencia_turno->value,
        ])->all() : $this->revisionCourses($revision->snapshot['detalles'] ?? null);

        return ['id' => $this->id, 'estado' => $this->estado->value,
            'correccion_en_progreso' => $this->correction_window_id !== null,
            'revision_id' => $revision?->id, 'revision_number' => $revision?->revision_number,
            'fecha_envio' => $revision?->submitted_at?->toISOString(),
            'origen_cursos' => $revision === null ? 'BORRADOR' : 'REVISION_REGISTRADA',
            'cursos' => $courses, 'creditos_totales' => array_sum(array_column($courses, 'creditos')),
            'validacion_academica' => $matchesRevision ? ['estado' => $validation->estado->value,
                'revision_id' => $validation->revision_id, 'evaluated_at' => $validation->evaluated_at->toISOString(),
                'cursos' => $this->validationCourses($validation->cursos)] : null];
    }

    /** @return list<array<string, mixed>> */
    private function revisionCourses(mixed $details): array
    {
        if (! is_array($details) || ! array_is_list($details)) {
            return [];
        }

        return collect($details)
            ->filter(fn (mixed $detail): bool => is_array($detail) && isset($detail['course_id']))
            ->unique('course_id')
            ->map(fn (array $detail): array => Arr::only($detail, ['course_id', 'codigo', 'nombre', 'creditos', 'preferencia_turno']))
            ->values()->all();
    }

    /** @return list<array<string, mixed>> */
    private function validationCourses(mixed $courses): array
    {
        if (! is_array($courses) || ! array_is_list($courses)) {
            return [];
        }

        return collect($courses)->filter(fn (mixed $course): bool => is_array($course))
            ->map(function (array $course): array {
                $result = Arr::only($course, ['course_id', 'codigo', 'estado', 'motivo']);
                $prerequisites = $course['prerrequisitos'] ?? [];
                $result['prerrequisitos'] = is_array($prerequisites) && array_is_list($prerequisites)
                    ? collect($prerequisites)->filter(fn (mixed $prerequisite): bool => is_array($prerequisite))
                        ->map(fn (array $prerequisite): array => Arr::only($prerequisite, ['id', 'codigo', 'estado_historial', 'estado_validacion']))
                        ->values()->all() : [];

                return $result;
            })->values()->all();
    }
}
