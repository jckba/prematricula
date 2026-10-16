<?php

namespace App\Services;

final readonly class CurriculumImportReport
{
    /**
     * @param  array<int, array{row: int, codigo: string, nombre: string, creditos: int, ciclo: int, horas_teoria: string, horas_practica: string, horas_laboratorio: string, prerrequisitos: list<string>}>  $rows
     * @param  list<array{row: int, column: string, message: string}>  $errors
     * @param  list<array{row: int, codigo: string, action: string, fields: list<string>, protected: bool}>  $changes
     */
    public function __construct(
        public array $rows,
        public array $errors,
        public array $changes,
        public string $catalogFingerprint,
    ) {}

    public function isValid(): bool
    {
        return $this->errors === [];
    }

    /** @return array{created: int, updated: int, unchanged: int} */
    public function summary(): array
    {
        return [
            'created' => count(array_filter($this->changes, fn (array $change): bool => $change['action'] === 'create')),
            'updated' => count(array_filter($this->changes, fn (array $change): bool => $change['action'] === 'update')),
            'unchanged' => count(array_filter($this->changes, fn (array $change): bool => $change['action'] === 'unchanged')),
        ];
    }

    /** @return array{valid: bool, catalog_fingerprint: string, summary: array{created: int, updated: int, unchanged: int}, changes: array, errors: array} */
    public function toArray(): array
    {
        return [
            'valid' => $this->isValid(),
            'catalog_fingerprint' => $this->catalogFingerprint,
            'summary' => $this->summary(),
            'changes' => $this->changes,
            'errors' => $this->errors,
        ];
    }
}
