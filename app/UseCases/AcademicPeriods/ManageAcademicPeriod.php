<?php

namespace App\UseCases\AcademicPeriods;

use App\Enums\AcademicPeriodStatus;
use App\Models\AcademicPeriod;
use App\Models\User;
use App\Services\AcademicProcessLock;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ManageAcademicPeriod
{
    public function __construct(private readonly AcademicProcessLock $lock) {}

    /** @param array<string, mixed> $data */
    public function save(array $data, User $actor, ?AcademicPeriod $period = null): AcademicPeriod
    {
        return $this->lock->transaction(function () use ($data, $actor, $period): AcademicPeriod {
            $current = $period === null ? null : AcademicPeriod::query()->lockForUpdate()->findOrFail($period->id);
            $base = $current?->only(['codigo', 'estado']) ?? [];
            if ($current !== null) {
                $base += ['fecha_inicio' => $current->fecha_inicio->toDateString(), 'fecha_fin' => $current->fecha_fin->toDateString()];
            }
            $merged = array_replace($base, $data);
            Validator::make($merged, [
                'codigo' => ['required', 'string', 'max:20', Rule::unique('academic_periods')->ignore($current?->id)],
                'fecha_inicio' => ['required', 'date_format:Y-m-d'],
                'fecha_fin' => ['required', 'date_format:Y-m-d', 'after_or_equal:fecha_inicio'],
                'estado' => ['required', Rule::enum(AcademicPeriodStatus::class)],
            ])->validate();

            if ($current !== null && ($current->academicHistories()->exists() || $current->preEnrollmentRequests()->exists() || $current->teachingAssignments()->withTrashed()->exists()) &&
                ($current->codigo !== $merged['codigo'] || $current->fecha_inicio->toDateString() !== $merged['fecha_inicio'] || $current->fecha_fin->toDateString() !== $merged['fecha_fin'])) {
                throw ValidationException::withMessages(['period' => 'Un período referenciado conserva código y fechas de clases. Puede administrar su estado y añadir ventanas.']);
            }

            if ($current === null) {
                return AcademicPeriod::create($merged + ['created_by' => $actor->id, 'updated_by' => $actor->id]);
            }

            $current->fill($merged + ['updated_by' => $actor->id])->save();

            return $current->refresh();
        });
    }

    public function delete(AcademicPeriod $period): void
    {
        $this->lock->transaction(function () use ($period): void {
            $current = AcademicPeriod::query()->lockForUpdate()->findOrFail($period->id);
            if ($current->windows()->exists() || $current->academicHistories()->exists() || $current->preEnrollmentRequests()->exists() || $current->teachingAssignments()->withTrashed()->exists()) {
                abort(409, 'No se puede eliminar un período referenciado.');
            }
            $current->delete();
        });
    }
}
