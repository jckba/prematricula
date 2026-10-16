<?php

namespace App\UseCases\Teaching;

use App\Enums\RecordStatus;
use App\Enums\UserRole;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\User;
use App\Services\AcademicProcessLock;
use Illuminate\Validation\ValidationException;

class ManageTeacher
{
    public function __construct(private readonly AcademicProcessLock $lock) {}

    /** @param array<string, mixed> $data */
    public function save(array $data, User $actor, ?Teacher $teacher = null): Teacher
    {
        abort_unless($actor->rol === UserRole::COORDINADOR && $actor->estado === RecordStatus::ACTIVO, 403);

        return $this->lock->transaction(function () use ($data, $teacher): Teacher {
            $current = $teacher === null ? null : Teacher::query()->lockForUpdate()->findOrFail($teacher->id);
            $code = $data['codigo_institucional'] ?? $current?->codigo_institucional;
            if (Teacher::query()->where('codigo_institucional', $code)->when($current !== null, fn ($query) => $query->where('id', '!=', $current->id))->exists()) {
                throw ValidationException::withMessages(['codigo_institucional' => 'El código institucional ya está registrado.']);
            }
            if (isset($data['max_horas_semanales']) && $current !== null &&
                TeachingAssignment::query()->where('teacher_id', $current->id)->select('academic_period_id')->groupBy('academic_period_id')
                    ->havingRaw('SUM(horas_teoria_asignadas + horas_practica_asignadas + horas_laboratorio_asignadas) > ?', [$data['max_horas_semanales']])->exists()) {
                throw ValidationException::withMessages(['max_horas_semanales' => 'El límite es inferior a la carga asignada en al menos un período.']);
            }

            if ($current === null) {
                return Teacher::create($data)->refresh();
            }
            $current->fill($data)->save();

            return $current->refresh();
        });
    }
}
