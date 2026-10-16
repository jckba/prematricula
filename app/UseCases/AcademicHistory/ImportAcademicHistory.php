<?php

namespace App\UseCases\AcademicHistory;

use App\Models\AcademicHistory;
use App\Models\AcademicHistoryChange;
use App\Models\AcademicHistoryImport;
use App\Models\User;
use App\Services\AcademicHistoryImportValidator;
use App\Services\AcademicProcessLock;
use App\Services\RevalidatePreEnrollments;
use Illuminate\Http\Exceptions\HttpResponseException;

class ImportAcademicHistory
{
    public function __construct(
        private readonly AcademicProcessLock $lock,
        private readonly AcademicHistoryImportValidator $validator,
        private readonly RevalidatePreEnrollments $revalidate,
    ) {}

    /** @param list<array<string, mixed>> $rows
     * @return array<string, mixed>
     */
    public function __invoke(array $rows, string $fingerprint, string $fileHash, string $reason, User $actor): array
    {
        return $this->lock->transaction(function () use ($rows, $fingerprint, $fileHash, $reason, $actor): array {
            $report = $this->validator->validate($rows);
            $prepared = $report['rows'];
            unset($report['rows']);
            if (! $report['valid']) {
                throw new HttpResponseException(response()->json(['data' => $report], 422));
            }
            if (! hash_equals($fingerprint, $report['history_fingerprint']) && $report['summary']['created'] + $report['summary']['updated'] > 0) {
                abort(409, 'El historial cambió desde la validación. Vuelva a validar.');
            }
            $import = AcademicHistoryImport::create(['created_by' => $actor->id, 'sha256' => $fileHash,
                'motivo' => $reason, 'summary' => $report['summary']]);
            $studentIds = [];
            foreach ($prepared as $row) {
                if ($row['action'] === 'unchanged') {
                    continue;
                }
                $after = ['nota_final' => $row['nota_final'], 'estado' => $row['estado']];
                $history = AcademicHistory::updateOrCreate([
                    'student_id' => $row['student_id'], 'course_id' => $row['course_id'], 'academic_period_id' => $row['academic_period_id'],
                ], $after);
                AcademicHistoryChange::create(['academic_history_import_id' => $import->id,
                    'academic_history_id' => $history->id, 'before' => $row['before'], 'after' => $after]);
                $studentIds[] = $row['student_id'];
            }
            $this->revalidate->forStudents(array_values(array_unique($studentIds)));

            return $report + ['import_id' => $import->id, 'sha256' => $fileHash];
        });
    }
}
