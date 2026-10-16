<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use DomainException;
use Dompdf\Dompdf;
use Dompdf\FontMetrics;
use Dompdf\Options;

class PreEnrollmentReceipt
{
    /** @param array<string, mixed> $snapshot */
    public function render(array $snapshot): string
    {
        if (! class_exists(Dompdf::class)) {
            throw new DomainException('El motor PDF no está instalado; no se ha registrado el envío.');
        }
        $pdf = new Dompdf(new Options([
            'isRemoteEnabled' => false, 'isPhpEnabled' => false, 'isJavascriptEnabled' => false,
            'isFontSubsettingEnabled' => true, 'defaultFont' => 'DejaVu Sans',
            'fontCache' => storage_path('framework/cache'),
        ]));
        $pdf->setPaper('A4', 'portrait');
        $canvas = $pdf->getCanvas();
        $metrics = $pdf->getFontMetrics();
        $font = $metrics->getFont('DejaVu Sans', 'normal');
        $bold = $metrics->getFont('DejaVu Sans', 'bold');
        $width = $canvas->get_width() - 72;
        $y = 30.0;
        foreach ([
            [$snapshot['institution']['university'], 12.0, $bold],
            [$snapshot['institution']['school'], 10.0, $font],
            ['CONSTANCIA DE REGISTRO DE PREMATRÍCULA', 11.0, $bold],
        ] as [$text, $size, $face]) {
            $size = min($size, $width / max(1.0, $metrics->getTextWidth($text, $face, 1.0)));
            $canvas->text(36, $y, $text, $face, $size);
            $y += 22;
        }
        $student = $snapshot['estudiante'];
        $date = CarbonImmutable::parse($snapshot['fecha_envio'])->setTimezone($snapshot['timezone'])->format('Y-m-d H:i:s T');
        foreach ([
            'Constancia: '.$snapshot['receipt_code'],
            'Estudiante: '.$student['nombres'].' '.$student['apellidos'],
            'Código universitario: '.$student['codigo_universitario'],
            'Período académico: '.$snapshot['periodo']['codigo'],
            'Fecha de envío: '.$date.'  |  Revisión: '.$snapshot['revision_number'],
        ] as $text) {
            $size = min(9.0, $width / max(1.0, $metrics->getTextWidth($text, $font, 1.0)));
            $canvas->text(36, $y, $text, $font, $size);
            $y += 17;
        }
        $y += 6;
        $columns = [72.0, $width - 220.0, 48.0, 100.0];
        $offsets = [36.0, 108.0, 108.0 + $columns[1], 156.0 + $columns[1]];
        foreach (['Código', 'Curso', 'Créditos', 'Preferencia'] as $index => $title) {
            $canvas->text($offsets[$index], $y, $title, $bold, 8.0);
        }
        $y += 19;
        $available = $canvas->get_height() - 110 - $y;
        $size = 9.0;
        do {
            $rows = [];
            $height = 0.0;
            foreach ($snapshot['detalles'] as $detail) {
                $cells = [];
                foreach ([$detail['codigo'], $detail['nombre'], (string) $detail['creditos'], $detail['preferencia_turno']] as $index => $text) {
                    $cells[] = $this->wrap($text, $columns[$index] - 6, $font, $size, $metrics);
                }
                $rowHeight = (max(array_map(count(...), $cells)) * 1.3 + 0.7) * $size;
                $rows[] = ['cells' => $cells, 'height' => $rowHeight];
                $height += $rowHeight;
            }
            if ($height <= $available) {
                break;
            }
            $size *= min(0.9, $available / max($height, 1.0));
        } while ($size > 0.000001);

        if ($height > $available) {
            throw new DomainException('No fue posible distribuir íntegramente la constancia en una página; el envío no fue registrado.');
        }
        foreach ($rows as $row) {
            foreach ($row['cells'] as $index => $lines) {
                foreach ($lines as $line => $text) {
                    $canvas->text($offsets[$index], $y + $line * $size * 1.3, $text, $font, $size);
                }
            }
            $y += $row['height'];
        }
        $canvas->line(36, $y + 3, 36 + $width, $y + 3, [0.3, 0.3, 0.3], 0.5);
        $canvas->text(36, $y + 10, 'Créditos totales: '.$snapshot['creditos_totales'], $bold, 9.0);
        $canvas->text(36, $canvas->get_height() - 67, 'Solicitud de prematrícula registrada satisfactoriamente por el backend.', $font, 8.0);
        $canvas->text(36, $canvas->get_height() - 51, 'Esta constancia no constituye matrícula definitiva.', $bold, 8.0);
        if ($canvas->get_page_count() !== 1) {
            throw new DomainException('La constancia debe tener exactamente una página.');
        }

        return $pdf->output();
    }

    /** @return list<string> */
    private function wrap(string $text, float $width, string $font, float $size, FontMetrics $metrics): array
    {
        $text = preg_replace('/[\\r\\n\\t]+/u', ' ', $text);
        $lines = [];
        $line = '';
        foreach (mb_str_split($text) as $character) {
            if ($line !== '' && $metrics->getTextWidth($line.$character, $font, $size) > $width) {
                $lines[] = $line;
                $line = '';
            }
            $line .= $character;
        }
        $lines[] = $line;

        return $lines;
    }
}
