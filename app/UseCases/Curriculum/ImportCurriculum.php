<?php

namespace App\UseCases\Curriculum;

use App\Exceptions\CurriculumValidationException;
use App\Models\User;
use App\Services\CurriculumCatalog;
use App\Services\CurriculumImportReport;
use App\Services\CurriculumValidator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ImportCurriculum
{
    public function __construct(
        private readonly CurriculumCatalog $catalog,
        private readonly CurriculumValidator $validator,
    ) {}

    /** @param list<array<string, mixed>> $rows */
    public function __invoke(array $rows, string $expectedFingerprint, string $fileHash, User $actor): CurriculumImportReport
    {
        return $this->catalog->transaction(function (Collection $courses) use ($rows, $expectedFingerprint, $fileHash, $actor): CurriculumImportReport {
            $report = $this->validator->validate($rows, $courses);

            if (! $report->isValid()) {
                throw new CurriculumValidationException($report);
            }

            $summary = $report->summary();

            if (! hash_equals($expectedFingerprint, $report->catalogFingerprint) && $summary['created'] + $summary['updated'] > 0) {
                abort(409, 'El catálogo cambió desde la validación. Vuelva a validar el archivo.');
            }

            $this->catalog->persist($report->rows, $courses);

            DB::afterCommit(fn () => Log::info('Malla curricular importada.', [
                'user_id' => $actor->id, 'sha256' => $fileHash, ...$summary,
            ]));

            return $report;
        });
    }
}
