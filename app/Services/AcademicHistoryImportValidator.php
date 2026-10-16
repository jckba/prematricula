<?php

namespace App\Services;

use App\Enums\AcademicHistoryStatus;
use App\Models\AcademicHistory;
use App\Models\AcademicPeriod;
use App\Models\Course;
use App\Models\Student;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class AcademicHistoryImportValidator
{
    public const COLUMNS = ['codigo_universitario', 'codigo_curso', 'codigo_periodo', 'nota_final', 'estado'];

    /** @param list<array<string, mixed>> $rawRows
     * @return array{valid: bool, history_fingerprint: string, summary: array<string, int>, errors: list<array<string, mixed>>, changes: list<array<string, mixed>>, rows: list<array<string, mixed>>}
     */
    public function validate(array $rawRows): array
    {
        $students = Student::query()->whereIn('codigo_universitario', $this->codes($rawRows, 'codigo_universitario'))->orderBy('id')->lockForUpdate()->get()->keyBy('codigo_universitario');
        $courses = Course::query()->whereIn('codigo', $this->codes($rawRows, 'codigo_curso'))->orderBy('id')->lockForUpdate()->get()->keyBy('codigo');
        $periods = AcademicPeriod::query()->whereIn('codigo', $this->codes($rawRows, 'codigo_periodo'))->orderBy('id')->lockForUpdate()->get()->keyBy('codigo');
        $triples = [];
        foreach ($rawRows as $raw) {
            $student = is_string($raw['codigo_universitario'] ?? null) ? $students->get(trim($raw['codigo_universitario'])) : null;
            $course = is_string($raw['codigo_curso'] ?? null) ? $courses->get(trim($raw['codigo_curso'])) : null;
            $period = is_string($raw['codigo_periodo'] ?? null) ? $periods->get(trim($raw['codigo_periodo'])) : null;
            if ($student !== null && $course !== null && $period !== null) {
                $triples[$student->id.':'.$course->id.':'.$period->id] = [$student->id, $course->id, $period->id];
            }
        }
        $histories = $triples === [] ? collect() : AcademicHistory::query()
            ->whereRaw('(student_id, course_id, academic_period_id) IN ('.implode(',', array_fill(0, count($triples), '(?, ?, ?)')).')', array_merge(...array_values($triples)))
            ->orderBy('id')->lockForUpdate()->get()->keyBy(fn (AcademicHistory $history): string => $history->student_id.':'.$history->course_id.':'.$history->academic_period_id);
        $errors = [];
        $rows = [];
        $changes = [];
        $seen = [];
        $summary = ['created' => 0, 'updated' => 0, 'unchanged' => 0];
        $snapshot = [];

        foreach ($rawRows as $raw) {
            $number = $raw['row'];
            array_push($errors, ...($raw['_errors'] ?? []));
            foreach (self::COLUMNS as $field) {
                if (is_string($raw[$field] ?? null)) {
                    $raw[$field] = trim($raw[$field]);
                }
            }
            if (($raw['nota_final'] ?? '') === '') {
                $raw['nota_final'] = null;
            }
            $validator = Validator::make($raw, [
                'codigo_universitario' => ['required', 'string', 'max:30'],
                'codigo_curso' => ['required', 'string', 'max:30'],
                'codigo_periodo' => ['required', 'string', 'max:20'],
                'nota_final' => ['nullable', 'numeric', 'decimal:0,2', 'between:'.config('academic.grade_min').','.config('academic.grade_max')],
                'estado' => ['required', Rule::enum(AcademicHistoryStatus::class)],
            ]);
            if ($validator->fails()) {
                foreach ($validator->errors()->messages() as $field => $messages) {
                    $errors[] = ['row' => $number, 'column' => $field, 'message' => $messages[0]];
                }

                continue;
            }
            if (in_array($raw['estado'], ['APROBADO', 'DESAPROBADO'], true) && $raw['nota_final'] === null) {
                $errors[] = ['row' => $number, 'column' => 'nota_final', 'message' => 'Un resultado final aprobado o desaprobado requiere nota.'];
            }
            if ($raw['estado'] === 'RETIRADO' && $raw['nota_final'] !== null) {
                $errors[] = ['row' => $number, 'column' => 'nota_final', 'message' => 'Un retiro no tiene nota final; deje la celda vacía.'];
            }
            $references = ['codigo_universitario' => $students->get($raw['codigo_universitario']),
                'codigo_curso' => $courses->get($raw['codigo_curso']), 'codigo_periodo' => $periods->get($raw['codigo_periodo'])];
            foreach ($references as $field => $model) {
                if ($model === null) {
                    $errors[] = ['row' => $number, 'column' => $field, 'message' => 'No existe el registro indicado por '.$field.'.'];
                }
            }
            if (in_array(null, $references, true)) {
                continue;
            }
            $ids = ['student_id' => $references['codigo_universitario']->id,
                'course_id' => $references['codigo_curso']->id, 'academic_period_id' => $references['codigo_periodo']->id];
            $key = implode(':', $ids);
            if (isset($seen[$key])) {
                $errors[] = ['row' => $number, 'column' => 'codigo_curso', 'message' => 'Resultado duplicado para alumno, curso y período; primera fila '.$seen[$key].'.'];
            }
            $seen[$key] = $number;
            $existing = $histories->get($key);
            $after = ['nota_final' => $raw['nota_final'] === null ? null : number_format((float) $raw['nota_final'], 2, '.', ''), 'estado' => $raw['estado']];
            $before = $existing === null ? null : ['nota_final' => $existing->nota_final, 'estado' => $existing->estado->value];
            $action = $before === null ? 'create' : ($before === $after ? 'unchanged' : 'update');
            $summary[match ($action) {
                'create' => 'created', 'update' => 'updated', default => 'unchanged'
            }]++;
            $rows[] = [...$ids, ...$after, 'history_id' => $existing?->id, 'before' => $before, 'row' => $number, 'action' => $action];
            $changes[] = ['row' => $number, 'codigo_universitario' => $raw['codigo_universitario'],
                'codigo_curso' => $raw['codigo_curso'], 'codigo_periodo' => $raw['codigo_periodo'], 'action' => $action, 'before' => $before, 'after' => $after];
            $snapshot[$key] = [$ids, $references['codigo_periodo']->fecha_inicio->toDateString(), $existing?->id, $before];
        }
        if ($rawRows === []) {
            $errors[] = ['row' => 2, 'column' => 'codigo_universitario', 'message' => 'El archivo no contiene resultados.'];
        }
        ksort($snapshot, SORT_STRING);

        return ['valid' => $errors === [], 'history_fingerprint' => hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR)),
            'summary' => $summary, 'errors' => $errors, 'changes' => $changes, 'rows' => $rows];
    }

    /** @param list<array<string, mixed>> $rows
     * @return list<string>
     */
    private function codes(array $rows, string $field): array
    {
        return collect($rows)->pluck($field)->filter(fn (mixed $value): bool => is_string($value))
            ->map(fn (string $value): string => trim($value))->unique()->values()->all();
    }
}
