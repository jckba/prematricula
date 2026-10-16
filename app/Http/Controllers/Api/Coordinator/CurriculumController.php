<?php

namespace App\Http\Controllers\Api\Coordinator;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ConfirmCurriculumImportRequest;
use App\Http\Requests\Api\CurriculumFileRequest;
use App\Models\Course;
use App\Services\CurriculumCatalog;
use App\Services\CurriculumImportReport;
use App\Services\CurriculumSpreadsheet;
use App\Services\CurriculumValidator;
use App\UseCases\Curriculum\ImportCurriculum;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class CurriculumController extends Controller
{
    public function template(CurriculumSpreadsheet $spreadsheet): Response
    {
        Gate::authorize('manageCurriculum', Course::class);

        return $this->download($spreadsheet->write(), 'plantilla-malla.xlsx');
    }

    public function validateFile(CurriculumFileRequest $request, CurriculumSpreadsheet $spreadsheet, CurriculumCatalog $catalog, CurriculumValidator $validator): JsonResponse
    {
        $file = $request->file('file');
        $rows = $spreadsheet->read($file);
        $report = $catalog->transaction(fn (Collection $courses): CurriculumImportReport => $validator->validate($rows, $courses));

        return response()->json([
            'data' => $report->toArray() + ['sha256' => hash_file('sha256', $file->getRealPath())],
        ], $report->isValid() ? 200 : 422);
    }

    public function import(ConfirmCurriculumImportRequest $request, CurriculumSpreadsheet $spreadsheet, ImportCurriculum $import): JsonResponse
    {
        $file = $request->file('file');
        $fileHash = hash_file('sha256', $file->getRealPath());

        if (! hash_equals($request->validated('sha256'), $fileHash)) {
            abort(409, 'El archivo no coincide con el validado. Vuelva a validarlo.');
        }

        $report = $import($spreadsheet->read($file), $request->validated('catalog_fingerprint'), $fileHash, $request->user());

        return response()->json([
            'message' => 'Malla curricular importada correctamente.',
            'data' => $report->toArray() + ['sha256' => $fileHash],
        ]);
    }

    public function export(CurriculumSpreadsheet $spreadsheet, CurriculumCatalog $catalog): Response
    {
        Gate::authorize('manageCurriculum', Course::class);
        $content = $catalog->transaction(fn (Collection $courses): string => $spreadsheet->write($courses
            ->sortBy('codigo', SORT_STRING)
            ->map(fn (Course $course): array => [
                ...$course->only(['codigo', ...CurriculumCatalog::ACADEMIC_FIELDS]),
                'prerrequisitos' => $course->prerequisites->pluck('codigo')->sort(SORT_STRING)->implode(';'),
            ])));

        return $this->download($content, 'malla-curricular.xlsx');
    }

    private function download(string $content, string $filename): Response
    {
        return response($content, 200, [
            'Content-Type' => CurriculumSpreadsheet::MIME_TYPE,
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
