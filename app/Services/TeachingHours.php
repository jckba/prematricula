<?php

namespace App\Services;

class TeachingHours
{
    public const FIELDS = ['horas_teoria_asignadas' => 'horas_teoria', 'horas_practica_asignadas' => 'horas_practica',
        'horas_laboratorio_asignadas' => 'horas_laboratorio'];

    /** @return list<string> */
    public static function validationRules(): array
    {
        return ['required', 'numeric', 'decimal:0,2', 'between:0,999.99'];
    }

    /** Convert validated nonnegative decimal hours to hundredths without floating point sums. */
    public static function hundredths(string|int|float $hours): int
    {
        $parts = explode('.', (string) $hours, 2);

        return (int) $parts[0] * 100 + (int) str_pad($parts[1] ?? '', 2, '0');
    }

    public static function decimal(int $hundredths): string
    {
        return intdiv($hundredths, 100).'.'.str_pad((string) ($hundredths % 100), 2, '0', STR_PAD_LEFT);
    }

    /** @param array<string, mixed> $hours */
    public static function total(array $hours): int
    {
        return array_sum(array_map(fn (string $field): int => self::hundredths($hours[$field] ?? 0), array_keys(self::FIELDS)));
    }
}
