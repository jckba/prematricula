<?php

namespace App\Exceptions;

use App\Services\CurriculumImportReport;
use Exception;
use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\JsonResponse;

class CurriculumValidationException extends Exception implements ShouldntReport
{
    public function __construct(public readonly CurriculumImportReport $report)
    {
        parent::__construct('La malla curricular contiene errores.');
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'data' => $this->report->toArray(),
        ], 422);
    }
}
