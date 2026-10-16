<?php

namespace App\UseCases\AcademicPeriods;

use App\Enums\AcademicPeriodStatus;
use App\Enums\PreEnrollmentWindowType;
use App\Models\AcademicPeriod;
use App\Models\PreEnrollmentWindow;
use App\Models\User;
use App\Services\AcademicProcessLock;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class CreatePreEnrollmentWindow
{
    public function __construct(private readonly AcademicProcessLock $lock) {}

    /** @param array{tipo: string, fecha_inicio: string, fecha_fin: string, motivo: string} $data */
    public function __invoke(AcademicPeriod $period, array $data, User $actor): PreEnrollmentWindow
    {
        return $this->lock->transaction(function () use ($period, $data, $actor): PreEnrollmentWindow {
            $period = AcademicPeriod::query()->lockForUpdate()->findOrFail($period->id);
            if (! in_array($period->estado, [AcademicPeriodStatus::PLANIFICACION, AcademicPeriodStatus::PREMATRICULA], true)) {
                throw ValidationException::withMessages(['period' => 'El período no admite nuevas ventanas de prematrícula.']);
            }
            $start = CarbonImmutable::parse($data['fecha_inicio'], config('academic.timezone'))->utc();
            $end = CarbonImmutable::parse($data['fecha_fin'], config('academic.timezone'))->utc();
            if ($start->setTimezone(config('academic.timezone'))->format('Y-m-d H:i:s') !== $data['fecha_inicio'] ||
                $end->setTimezone(config('academic.timezone'))->format('Y-m-d H:i:s') !== $data['fecha_fin']) {
                throw ValidationException::withMessages(['fecha_inicio' => 'La fecha no existe en la zona horaria institucional.']);
            }
            if ($end->lte($start)) {
                throw ValidationException::withMessages(['fecha_fin' => 'La fecha final debe ser posterior al inicio.']);
            }
            $windows = $period->windows()->orderBy('fecha_inicio')->get();
            $ordinary = $windows->firstWhere('tipo', PreEnrollmentWindowType::ORDINARIA);
            if ($data['tipo'] === PreEnrollmentWindowType::ORDINARIA->value && $windows->isNotEmpty()) {
                throw ValidationException::withMessages(['tipo' => 'El período ya tiene una ventana ordinaria.']);
            }
            if ($data['tipo'] === PreEnrollmentWindowType::EXTRAORDINARIA->value &&
                ($ordinary === null || $start->lt($windows->max('fecha_fin')))) {
                throw ValidationException::withMessages(['fecha_inicio' => 'La extraordinaria requiere una ordinaria previa y comienza después de todas las ventanas anteriores.']);
            }
            if ($windows->contains(fn (PreEnrollmentWindow $window): bool => $start->lt($window->fecha_fin) && $end->gt($window->fecha_inicio))) {
                throw ValidationException::withMessages(['fecha_inicio' => 'Las ventanas de un período no pueden solaparse.']);
            }
            $window = $period->windows()->create([
                ...$data, 'fecha_inicio' => $start, 'fecha_fin' => $end,
                'created_by' => $actor->id, 'timezone' => config('academic.timezone'), 'origen' => 'API',
            ]);
            $period->update(['updated_by' => $actor->id]);

            return $window;
        });
    }
}
