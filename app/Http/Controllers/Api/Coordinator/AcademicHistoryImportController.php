<?php

namespace App\Http\Controllers\Api\Coordinator;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\AcademicHistoryFileRequest;
use App\Http\Requests\Api\ConfirmAcademicHistoryImportRequest;
use App\Http\Resources\AcademicHistoryImportResource;
use App\Models\AcademicHistory;
use App\Models\AcademicHistoryImport;
use App\Services\AcademicHistoryImportValidator;
use App\Services\AcademicProcessLock;
use App\Services\CurriculumSpreadsheet;
use App\UseCases\AcademicHistory\ImportAcademicHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Shuchkin\SimpleXLSXGen;

class AcademicHistoryImportController extends Controller
{
    public function template(): Response
    {
        Gate::authorize('import', AcademicHistory::class);

        return response((string) SimpleXLSXGen::fromArray([AcademicHistoryImportValidator::COLUMNS], 'Resultados'), 200, [
            'Content-Type' => CurriculumSpreadsheet::MIME_TYPE,
            'Content-Disposition' => 'attachment; filename="plantilla-historial.xlsx"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function validateFile(AcademicHistoryFileRequest $request, CurriculumSpreadsheet $spreadsheet, AcademicProcessLock $lock, AcademicHistoryImportValidator $validator): JsonResponse
    {
        $file = $request->file('file');
        $rows = $spreadsheet->read($file, AcademicHistoryImportValidator::COLUMNS);
        $report = $lock->transaction(fn (): array => $validator->validate($rows));
        unset($report['rows']);

        return response()->json(['data' => $report + ['sha256' => hash_file('sha256', $file->getRealPath())]], $report['valid'] ? 200 : 422);
    }

    public function import(ConfirmAcademicHistoryImportRequest $request, CurriculumSpreadsheet $spreadsheet, ImportAcademicHistory $import): JsonResponse
    {
        $file = $request->file('file');
        $hash = hash_file('sha256', $file->getRealPath());
        if (! hash_equals($hash, $request->validated('sha256'))) {
            abort(409, 'El archivo no coincide con el validado.');
        }

        return response()->json(['data' => $import(
            $spreadsheet->read($file, AcademicHistoryImportValidator::COLUMNS),
            $request->validated('history_fingerprint'), $hash, $request->validated('motivo'), $request->user(),
        )]);
    }

    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('import', AcademicHistory::class);
        $imports = AcademicHistoryImport::orderByDesc('id')->paginate(20);

        return AcademicHistoryImportResource::collection($imports);
    }

    public function show(AcademicHistoryImport $import): AcademicHistoryImportResource
    {
        Gate::authorize('import', AcademicHistory::class);

        return new AcademicHistoryImportResource($import->load('changes'));
    }
}
