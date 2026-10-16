<?php

namespace App\Console\Commands;

use App\Enums\AcademicPeriodStatus;
use App\Enums\PreEnrollmentStatus;
use App\Models\PreEnrollmentRequest;
use App\Services\AcademicProcessLock;
use App\Services\RevalidatePreEnrollments;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('academic:revalidate')]
#[Description('Inicializa o actualiza la validación académica de prematrículas vigentes sin alterar solicitudes ni ventanas')]
class RevalidateAcademicPreEnrollments extends Command
{
    public function handle(AcademicProcessLock $lock, RevalidatePreEnrollments $revalidate): int
    {
        $count = 0;
        $lock->transaction(function () use ($revalidate, &$count): void {
            PreEnrollmentRequest::whereIn('estado', [PreEnrollmentStatus::BORRADOR, PreEnrollmentStatus::ENVIADA])
                ->whereHas('academicPeriod', fn ($query) => $query->where('estado', '!=', AcademicPeriodStatus::CERRADO))
                ->orderBy('id')->chunkById(100, function ($requests) use ($revalidate, &$count): void {
                    foreach ($requests as $request) {
                        $revalidate->evaluate($request);
                        $count++;
                    }
                });
        });
        $this->info("Prematrículas revalidadas: {$count}.");

        return self::SUCCESS;
    }
}
