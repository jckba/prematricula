<?php

namespace App\Services;

use App\Models\Course;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Validator;

class CurriculumValidator
{
    public function __construct(private readonly CurriculumCatalog $catalog) {}

    /** @return array<string, array<int, string>> */
    public static function academicRules(): array
    {
        return [
            'codigo' => ['required', 'string', 'max:30', 'regex:/\A[A-Za-z0-9][A-Za-z0-9._-]*\z/'],
            'nombre' => ['required', 'string', 'max:150', 'not_regex:/[\x00-\x08\x0B\x0C\x0E-\x1F]/'],
            'creditos' => ['required', 'numeric', 'integer', 'between:1,32767'],
            'ciclo' => ['required', 'numeric', 'integer', 'between:1,32767'],
            'horas_teoria' => ['required', 'numeric', 'decimal:0,2', 'between:0,999.99'],
            'horas_practica' => ['required', 'numeric', 'decimal:0,2', 'between:0,999.99'],
            'horas_laboratorio' => ['required', 'numeric', 'decimal:0,2', 'between:0,999.99'],
            'prerrequisitos' => ['present', 'array', 'max:5000'],
            'prerrequisitos.*' => ['required', 'string', 'max:30', 'regex:/\A[A-Za-z0-9][A-Za-z0-9._-]*\z/', 'distinct:strict'],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rawRows
     * @param  Collection<int, Course>  $courses
     */
    public function validate(array $rawRows, Collection $courses): CurriculumImportReport
    {
        $errors = [];
        $rows = [];
        $changes = [];
        $seenCodes = [];
        $byCode = $courses->keyBy('codigo');
        $graph = $courses->mapWithKeys(fn (Course $course): array => [
            $course->codigo => $course->prerequisites->pluck('codigo')->all(),
        ])->all();

        foreach ($rawRows as $rawRow) {
            $rowNumber = $rawRow['row'];
            array_push($errors, ...($rawRow['_errors'] ?? []));
            foreach ($rawRow as $field => $value) {
                if (is_string($value)) {
                    $rawRow[$field] = trim($value);
                }
            }

            $prerequisites = $rawRow['prerrequisitos'] ?? null;

            if (is_string($prerequisites)) {
                if (mb_strlen($prerequisites) > 32767) {
                    $errors[] = ['row' => $rowNumber, 'column' => 'prerrequisitos', 'message' => 'La celda supera el máximo de 32767 caracteres.'];

                    continue;
                }

                $rawRow['prerrequisitos'] = $prerequisites === '' ? [] : array_map(trim(...), explode(';', $prerequisites));
            }

            $validator = Validator::make($rawRow, self::academicRules(), [
                'required' => 'La columna :attribute es obligatoria.',
                'string' => 'La columna :attribute debe contener texto.',
                'integer' => 'La columna :attribute debe contener un entero.',
                'numeric' => 'La columna :attribute debe contener un número.',
                'between' => 'La columna :attribute debe estar entre :min y :max.',
                'decimal' => 'La columna :attribute admite como máximo dos decimales.',
                'distinct' => 'No se permiten prerrequisitos repetidos.',
                'regex' => 'La columna :attribute tiene un formato inválido.',
            ]);

            if ($validator->fails()) {
                foreach ($validator->errors()->messages() as $field => $messages) {
                    $errors[] = ['row' => $rowNumber, 'column' => explode('.', $field)[0], 'message' => $messages[0]];
                }

                continue;
            }

            $row = $validator->validated();
            $row['row'] = $rowNumber;
            $row['creditos'] = (int) $row['creditos'];
            $row['ciclo'] = (int) $row['ciclo'];

            foreach (['horas_teoria', 'horas_practica', 'horas_laboratorio'] as $field) {
                $row[$field] = number_format((float) $row[$field], 2, '.', '');
            }

            sort($row['prerrequisitos'], SORT_STRING);

            if (isset($seenCodes[$row['codigo']])) {
                $errors[] = ['row' => $rowNumber, 'column' => 'codigo', 'message' => 'Código duplicado; ya aparece en la fila '.$seenCodes[$row['codigo']].'.'];
            } else {
                $seenCodes[$row['codigo']] = $rowNumber;
            }

            $rows[] = $row;
            $graph[$row['codigo']] = $row['prerrequisitos'];
        }

        if ($rawRows === []) {
            $errors[] = ['row' => 2, 'column' => 'codigo', 'message' => 'El archivo no contiene cursos.'];
        }

        foreach ($rows as $row) {
            foreach ($row['prerrequisitos'] as $prerequisite) {
                if ($prerequisite === $row['codigo']) {
                    $errors[] = ['row' => $row['row'], 'column' => 'prerrequisitos', 'message' => 'Un curso no puede ser su propio prerrequisito.'];
                } elseif (! array_key_exists($prerequisite, $graph)) {
                    $errors[] = ['row' => $row['row'], 'column' => 'prerrequisitos', 'message' => 'El prerrequisito '.$prerequisite.' no existe en el catálogo ni en el archivo.'];
                }
            }

            $existing = $byCode->get($row['codigo']);
            $fields = [];

            foreach (CurriculumCatalog::ACADEMIC_FIELDS as $field) {
                if ($existing === null || $existing->getAttribute($field) !== $row[$field]) {
                    $fields[] = $field;
                }
            }

            if ($existing === null || $existing->prerequisites->pluck('codigo')->sort(SORT_STRING)->values()->all() !== $row['prerrequisitos']) {
                $fields[] = 'prerrequisitos';
            }

            $protected = $existing !== null && $this->catalog->isProtected($existing);

            if ($protected && $fields !== []) {
                foreach ($fields as $field) {
                    $errors[] = ['row' => $row['row'], 'column' => $field, 'message' => 'El curso ya está utilizado en historial o prematrícula; sus datos académicos y prerrequisitos son inmutables.'];
                }
            }

            $changes[] = [
                'row' => $row['row'],
                'codigo' => $row['codigo'],
                'action' => $existing === null ? 'create' : ($fields === [] ? 'unchanged' : 'update'),
                'fields' => $fields,
                'protected' => $protected,
            ];
        }

        $cyclicCodes = $this->cyclicCodes($graph);

        foreach ($rows as $row) {
            if (isset($cyclicCodes[$row['codigo']])) {
                $errors[] = ['row' => $row['row'], 'column' => 'prerrequisitos', 'message' => 'El curso participa en una dependencia circular.'];
            }
        }

        if ($cyclicCodes !== [] && ! array_intersect_key($cyclicCodes, $seenCodes)) {
            $errors[] = ['row' => 1, 'column' => 'prerrequisitos', 'message' => 'El catálogo existente contiene dependencias circulares.'];
        }

        return new CurriculumImportReport($rows, $errors, $changes, $this->catalog->fingerprint($courses));
    }

    /**
     * @param  array<string, list<string>>  $graph
     * @return array<string, true>
     */
    private function cyclicCodes(array $graph): array
    {
        $visited = [];
        $path = [];
        $positions = [];
        $cyclic = [];
        foreach (array_keys($graph) as $code) {
            $this->visit((string) $code, $graph, $visited, $path, $positions, $cyclic);
        }

        return $cyclic;
    }

    /**
     * @param  array<string, list<string>>  $graph
     * @param  array<string, true>  $visited
     * @param  list<string>  $path
     * @param  array<string, int>  $positions
     * @param  array<string, true>  $cyclic
     */
    private function visit(string $code, array $graph, array &$visited, array &$path, array &$positions, array &$cyclic): void
    {
        if (isset($positions[$code])) {
            foreach (array_slice($path, $positions[$code]) as $cycleCode) {
                $cyclic[$cycleCode] = true;
            }

            return;
        }
        if (isset($visited[$code])) {
            return;
        }
        $visited[$code] = true;
        $positions[$code] = count($path);
        $path[] = $code;
        foreach ($graph[$code] ?? [] as $prerequisite) {
            $this->visit($prerequisite, $graph, $visited, $path, $positions, $cyclic);
        }
        array_pop($path);
        unset($positions[$code]);
    }
}
