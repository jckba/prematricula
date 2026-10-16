<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Shuchkin\SimpleXLSX;
use Shuchkin\SimpleXLSXGen;
use Throwable;

class CurriculumSpreadsheet
{
    public const COLUMNS = ['codigo', 'nombre', 'creditos', 'ciclo', 'horas_teoria', 'horas_practica', 'horas_laboratorio', 'prerrequisitos'];

    public const MIME_TYPE = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

    /** @return list<array<string, mixed>> */
    public function read(UploadedFile $file, array $columns = self::COLUMNS): array
    {
        try {
            $workbook = $this->openSafely($file->getRealPath());
            $headers = null;
            $rows = [];
            $seenRows = [];

            foreach ($workbook->getSheets()[0]->sheetData->row as $xmlRow) {
                $rowNumber = (int) $xmlRow['r'];

                if ($rowNumber < 1 || $rowNumber > config('curriculum.max_rows') + 1 || isset($seenRows[$rowNumber])) {
                    $this->invalidFile('Número de fila inválido, repetido o fuera del límite permitido.');
                }

                $seenRows[$rowNumber] = true;
                $values = array_fill(0, count($columns), '');
                $cellErrors = [];
                $seenCells = [];

                foreach ($xmlRow->c as $cell) {
                    $coordinate = (string) $cell['r'];

                    if (! preg_match('/\A([A-Z])([1-9][0-9]*)\z/', $coordinate, $matches) ||
                        ord($matches[1]) - ord('A') >= count($columns) ||
                        (int) $matches[2] !== $rowNumber || isset($seenCells[$coordinate])) {
                        $this->invalidFile('El archivo debe tener exactamente '.count($columns).' columnas, sin coordenadas duplicadas o inválidas.');
                    }

                    $seenCells[$coordinate] = true;
                    $columnIndex = ord($matches[1]) - ord('A');
                    $values[$columnIndex] = $workbook->value($cell);

                    if (isset($cell->f) || (string) $cell['t'] === 'e') {
                        $cellErrors[] = [
                            'row' => $rowNumber,
                            'column_index' => $columnIndex,
                            'message' => 'No se permiten fórmulas ni celdas con errores; ingrese valores explícitos.',
                        ];
                    }
                }

                if ($rowNumber === 1) {
                    $headers = array_map(fn (mixed $value): string => is_string($value) ? trim($value) : '', $values);

                    if (count(array_unique($headers)) !== count($columns) || array_diff($columns, $headers) !== [] || $cellErrors !== []) {
                        $this->invalidFile('La fila 1 debe contener una vez cada columna: '.implode(', ', $columns).'.');
                    }

                    continue;
                }

                if ($headers === null) {
                    $this->invalidFile('Falta la fila 1 de encabezados.');
                }

                if ($cellErrors === [] && count(array_filter($values, fn (mixed $value): bool => $value !== '' && $value !== null)) === 0) {
                    continue;
                }

                $row = array_combine($headers, $values);
                $row['row'] = $rowNumber;
                $row['_errors'] = array_map(fn (array $error): array => [
                    'row' => $error['row'], 'column' => $headers[$error['column_index']], 'message' => $error['message'],
                ], $cellErrors);
                $rows[] = $row;
            }

            if ($headers === null) {
                $this->invalidFile('El archivo está vacío o no contiene los encabezados obligatorios.');
            }

            return $rows;
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable) {
            $this->invalidFile('No se pudo leer el archivo .xlsx; está corrupto o tiene una estructura no admitida.');
        }
    }

    /**
     * Bound decompression before parsing XML. The reader's public ZIP API lets
     * us reject oversized entries, external entities and active content before
     * loading a worksheet, without requiring the system ZIP extension.
     */
    private function openSafely(string $path): SimpleXLSX
    {
        $binary = file_get_contents($path);
        $endPosition = strrpos($binary, "PK\x05\x06");

        if (! str_starts_with($binary, "PK\x03\x04") || $endPosition === false || strlen($binary) - $endPosition < 22) {
            $this->invalidFile('El contenedor .xlsx está incompleto o no es un archivo ZIP válido.');
        }

        $directory = unpack('vdisk/vstart_disk/ventries_disk/ventries/Vsize/Voffset/vcomment_length', substr($binary, $endPosition + 4, 18));

        if ($directory['disk'] !== 0 || $directory['start_disk'] !== 0 || $directory['entries_disk'] !== $directory['entries'] ||
            $directory['entries'] > config('curriculum.max_archive_entries') ||
            $endPosition !== $directory['offset'] + $directory['size'] ||
            $endPosition + 22 + $directory['comment_length'] !== strlen($binary)) {
            $this->invalidFile('El directorio del contenedor .xlsx está corrupto o supera los límites permitidos.');
        }

        $workbook = new SimpleXLSX;

        if (! $workbook->unzip($binary, true) || count($workbook->package['entries']) > config('curriculum.max_archive_entries')) {
            $this->invalidFile('El contenedor .xlsx es inválido o supera el límite de entradas.');
        }

        $totalBytes = 0;
        $limit = config('curriculum.max_uncompressed_bytes');
        $hasManifest = false;

        foreach ($workbook->package['entries'] as &$entry) {
            if ($entry['error'] || $entry['ucs'] < 0 || $entry['ucs'] > $limit || ! in_array($entry['cm'], [0, 8], true)) {
                $this->invalidFile('El contenedor tiene entradas dañadas, cifradas o demasiado grandes.');
            }

            $data = $entry['cm'] === 8 ? @gzinflate($entry['data'], max(1, $limit - $totalBytes + 1)) : $entry['data'];

            if ($data === false || strlen($data) !== $entry['ucs'] || crc32($data) !== $entry['crc']) {
                $this->invalidFile('El contenedor .xlsx está corrupto o excede el límite de descompresión.');
            }

            $totalBytes += strlen($data);

            if ($totalBytes > $limit) {
                $this->invalidFile('El contenido descomprimido supera el límite permitido.');
            }

            $name = strtolower($entry['path'].'/'.$entry['name']);
            $hasManifest = $hasManifest || $name === '/[content_types].xml';

            if (str_contains($name, 'vbaproject') || str_contains($name, '/externallinks/')) {
                $this->invalidFile('No se permiten macros ni vínculos externos.');
            }

            if (str_ends_with($name, '.xml') || str_ends_with($name, '.rels')) {
                if (str_contains($data, "\0") || stripos($data, '<!DOCTYPE') !== false || stripos($data, '<!ENTITY') !== false ||
                    preg_match('/TargetMode\s*=\s*[\x22\x27]External[\x22\x27]/i', $data)) {
                    $this->invalidFile('Solo se admite XML UTF-8 sin entidades ni vínculos externos.');
                }
            }

            $entry['data'] = $data;
            $entry['cm'] = -1;
        }
        unset($entry);

        if (! $hasManifest || ! $workbook->parseEntries() || $workbook->sheetsCount() !== 1 || $workbook->isHiddenSheet(0)) {
            $this->invalidFile('El archivo debe contener exactamente una hoja de cálculo visible.');
        }

        return $workbook;
    }

    /** @param iterable<array{codigo: string, nombre: string, creditos: int, ciclo: int, horas_teoria: string, horas_practica: string, horas_laboratorio: string, prerrequisitos: string}> $courses */
    public function write(iterable $courses = []): string
    {
        $rows = [array_map(SimpleXLSXGen::raw(...), self::COLUMNS)];

        foreach ($courses as $course) {
            $rows[] = [
                SimpleXLSXGen::raw($course['codigo']),
                SimpleXLSXGen::raw($course['nombre']),
                $course['creditos'],
                $course['ciclo'],
                (float) $course['horas_teoria'],
                (float) $course['horas_practica'],
                (float) $course['horas_laboratorio'],
                SimpleXLSXGen::raw($course['prerrequisitos']),
            ];
        }

        return (string) SimpleXLSXGen::fromArray($rows, 'Malla')->freezePanes('A2')->setColWidth(2, 48);
    }

    private function invalidFile(string $message): never
    {
        throw ValidationException::withMessages(['file' => $message]);
    }
}
