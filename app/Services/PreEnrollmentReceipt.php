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

        $options = new Options([
            'isRemoteEnabled' => false,
            'isPhpEnabled' => false,
            'isJavascriptEnabled' => false,
            'isFontSubsettingEnabled' => true,
            'defaultFont' => 'DejaVu Sans',
            'fontCache' => storage_path('framework/cache'),
        ]);

        $pdf = new Dompdf($options);
        $pdf->setPaper('A4', 'portrait');

        $html = $this->buildHtml($snapshot);
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->render();

        $canvas = $pdf->getCanvas();
        if ($canvas->get_page_count() !== 1) {
            throw new DomainException('La constancia debe tener exactamente una página.');
        }

        return $pdf->output();
    }

    /** @param array<string, mixed> $snapshot */
    public function buildHtml(array $snapshot): string
    {
        $university = ! empty($snapshot['institution']['university']) && $snapshot['institution']['university'] !== 'Universidad'
            ? (string) $snapshot['institution']['university']
            : 'UNIVERSIDAD NACIONAL DE TRUJILLO';

        $office = ! empty($snapshot['institution']['office'])
            ? mb_strtoupper((string) $snapshot['institution']['office'])
            : 'OFICINA DE SISTEMAS';

        $rawSchool = (string) ($snapshot['institution']['school'] ?? 'INFORMATICA');

        $student = $snapshot['estudiante'] ?? [];
        $codigoUniversitario = (string) ($student['codigo_universitario'] ?? '');
        $apellidos = mb_strtoupper((string) ($student['apellidos'] ?? ''));
        $nombres = mb_strtoupper((string) ($student['nombres'] ?? ''));
        $estudianteNombreCompleto = trim($apellidos.' '.$nombres);

        $facultad = mb_strtoupper((string) config('institution.faculty', 'CIENCIAS FISICAS Y MATEMATICAS'));

        $escuela = ! empty($rawSchool) && $rawSchool !== 'Escuela'
            ? mb_strtoupper($rawSchool)
            : 'INFORMATICA';

        $sede = mb_strtoupper((string) config('institution.campus', 'TRUJILLO'));
        $periodo = (string) ($snapshot['periodo']['codigo'] ?? '');

        // Preferencia: extraer turno(s) para que aparezca una sola vez abajo de periodo académico
        $preferenciasRaw = array_unique(array_filter(array_column($snapshot['detalles'] ?? [], 'preferencia_turno')));
        $prefLabels = [
            'MANANA' => 'MAÑANA',
            'TARDE' => 'TARDE',
            'NOCHE' => 'NOCHE',
        ];
        $prefTexts = array_map(fn (string $p): string => $prefLabels[$p] ?? $p, $preferenciasRaw);
        $preferencia = ! empty($prefTexts) ? implode(', ', $prefTexts) : 'MAÑANA';

        $tz = $snapshot['timezone'] ?? config('academic.timezone', 'America/Lima');
        try {
            $timeZone = new \DateTimeZone($tz);
        } catch (\Throwable) {
            $timeZone = new \DateTimeZone('America/Lima');
        }

        if (class_exists(CarbonImmutable::class)) {
            $date = CarbonImmutable::parse($snapshot['fecha_envio'])->setTimezone($tz);
            $dayOfWeekIso = $date->dayOfWeekIso;
            $day = $date->day;
            $month = $date->month;
            $year = $date->year;
            $fechaHoraEmision = $date->format('Y-m-d H:i:s');
        } else {
            $date = (new \DateTimeImmutable($snapshot['fecha_envio']))->setTimezone($timeZone);
            $dayOfWeekIso = (int) $date->format('N');
            $day = (int) $date->format('j');
            $month = (int) $date->format('n');
            $year = (int) $date->format('Y');
            $fechaHoraEmision = $date->format('Y-m-d H:i:s');
        }

        $dias = [
            1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves',
            5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo',
        ];
        $meses = [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
            5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
            9 => 'Setiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
        ];
        $diaSemana = $dias[$dayOfWeekIso] ?? '';
        $mesNombre = $meses[$month] ?? '';
        $fechaPie = sprintf('%s, %s %d de %s del %d', $sede, $diaSemana, $day, $mesNombre, $year);

        $watermarkDataUri = $this->getImageDataUri('images/unt_escudo.png', 'images/unt_watermark.png');
        $photoDataUri = $this->getImageDataUri('images/avatar_placeholder.png');
        $qrDataUri = $this->getImageDataUri('images/qr_default.png');

        $detalles = $snapshot['detalles'] ?? [];
        $totalCursos = count($detalles);
        $creditosTotales = $snapshot['creditos_totales'] ?? 0;

        // Estilos dinámicos para garantizar exactamente 1 página A4 incluso con hasta 80 cursos
        $tableFontSize = '8pt';
        $tablePadding = '3pt 4pt';
        $detailsMarginBottom = '7pt';
        $photoHeight = '68pt';
        $photoWidth = '58pt';
        $signaturesMarginTop = '42pt';
        $bottomSectionMarginTop = '38pt';

        if ($totalCursos > 40) {
            $tableFontSize = '3.5pt';
            $tablePadding = '0.5pt 1pt';
            $detailsMarginBottom = '2pt';
            $photoHeight = '42pt';
            $photoWidth = '36pt';
            $signaturesMarginTop = '8pt';
            $bottomSectionMarginTop = '4pt';
        } elseif ($totalCursos > 20) {
            $tableFontSize = '5.5pt';
            $tablePadding = '1pt 2pt';
            $detailsMarginBottom = '4pt';
            $photoHeight = '52pt';
            $photoWidth = '44pt';
            $signaturesMarginTop = '14pt';
            $bottomSectionMarginTop = '10pt';
        } elseif ($totalCursos > 10) {
            $tableFontSize = '6.5pt';
            $tablePadding = '1.5pt 2.5pt';
            $detailsMarginBottom = '5pt';
            $signaturesMarginTop = '24pt';
            $bottomSectionMarginTop = '18pt';
        }

        $coursesTableHtml = '';
        if ($totalCursos > 0) {
            $rowsHtml = '';
            foreach ($detalles as $detail) {
                $cCodigo = htmlspecialchars((string) ($detail['codigo'] ?? ''), ENT_QUOTES, 'UTF-8');
                $cNombre = htmlspecialchars((string) ($detail['nombre'] ?? ''), ENT_QUOTES, 'UTF-8');
                $cCreditos = htmlspecialchars((string) ($detail['creditos'] ?? ''), ENT_QUOTES, 'UTF-8');
                $rowsHtml .= "<tr><td class=\"col-code\">{$cCodigo}</td><td class=\"col-name\">{$cNombre}</td><td class=\"col-credits\">{$cCreditos}</td></tr>";
            }

            $coursesTableHtml = <<<HTML
            <table class="courses-table" style="font-size: {$tableFontSize};">
                <thead>
                    <tr>
                        <th class="col-code" style="padding: {$tablePadding};">CÓDIGO</th>
                        <th class="col-name" style="padding: {$tablePadding};">CURSO / ASIGNATURA</th>
                        <th class="col-credits" style="padding: {$tablePadding};">CRÉDITOS</th>
                    </tr>
                </thead>
                <tbody>
                    {$rowsHtml}
                </tbody>
                <tfoot>
                    <tr class="total-row">
                        <td colspan="2" style="text-align: right; padding: {$tablePadding}; font-weight: bold;">Créditos totales:</td>
                        <td class="col-credits" style="padding: {$tablePadding}; font-weight: bold;">{$creditosTotales}</td>
                    </tr>
                </tfoot>
            </table>
HTML;
        }

        $directorEscuela = stripos($escuela, 'ESCUELA') === false ? 'ESCUELA '.$escuela : $escuela;

        $watermarkHtml = $watermarkDataUri !== ''
            ? "<div class=\"watermark\"><img src=\"{$watermarkDataUri}\" alt=\"Escudo UNT\" /></div>"
            : '';

        $photoHtml = $photoDataUri !== ''
            ? "<img src=\"{$photoDataUri}\" alt=\"Foto estudiante\" />"
            : '<div style="width:100%;height:100%;background:#e5e5e5;"></div>';

        $qrHtml = $qrDataUri !== ''
            ? "<img src=\"{$qrDataUri}\" alt=\"Código QR de validación\" />"
            : '';

        return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Constancia de Prematrícula</title>
    <style>
        @page {
            margin: 18pt 30pt 16pt 30pt;
            size: A4 portrait;
        }
        * {
            box-sizing: border-box;
        }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            margin: 0;
            padding: 0;
            color: #111111;
            line-height: 1.25;
            position: relative;
        }
        .watermark {
            position: absolute;
            top: 130pt;
            left: 0;
            width: 100%;
            text-align: center;
            z-index: 0;
            pointer-events: none;
        }
        .watermark img {
            width: 360pt;
            height: auto;
            opacity: 0.22;
            margin: 0 auto;
            display: inline-block;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 3pt;
        }
        .header-left {
            vertical-align: middle;
            text-align: left;
        }
        .header-university {
            font-size: 11pt;
            font-weight: bold;
            color: #000000;
            margin-bottom: 2pt;
        }
        .header-office {
            font-size: 9pt;
            color: #222222;
        }
        .header-right {
            width: 75pt;
            text-align: center;
            vertical-align: top;
        }
        .photo-box {
            display: inline-block;
            width: {$photoWidth};
            height: {$photoHeight};
            border: 1px solid #666666;
            text-align: center;
            overflow: hidden;
            background-color: #f2f2f2;
        }
        .photo-box img {
            width: 100%;
            height: 100%;
            display: block;
        }
        .student-code-header {
            font-size: 7pt;
            font-weight: bold;
            margin-top: 2pt;
            color: #111111;
        }
        .header-line {
            border-bottom: 1.5pt solid #000000;
            margin-top: 2pt;
            margin-bottom: 7pt;
        }
        .doc-title {
            font-size: 12.5pt;
            font-weight: bold;
            text-align: center;
            margin-top: 3pt;
            margin-bottom: 6pt;
            letter-spacing: 0.3pt;
        }
        .statement-p {
            font-size: 8.5pt;
            text-align: justify;
            margin-top: 0;
            margin-bottom: 4pt;
            line-height: 1.25;
        }
        .statement-valid {
            font-size: 8.5pt;
            text-align: justify;
            margin-top: 0;
            margin-bottom: 6pt;
            line-height: 1.25;
        }
        .details-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: {$detailsMarginBottom};
        }
        .details-table td {
            padding: 1.2pt 0;
            font-size: 8.5pt;
            vertical-align: top;
        }
        .details-table .lbl {
            font-weight: bold;
            width: 170pt;
            color: #000000;
        }
        .details-table .val {
            font-weight: bold;
            color: #111111;
        }
        .courses-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 3pt;
            margin-bottom: 4pt;
        }
        .courses-table th {
            border-top: 1.5pt solid #333333;
            border-bottom: 1.5pt solid #333333;
            font-weight: bold;
            text-align: left;
            padding: 3pt 4pt;
        }
        .courses-table td {
            border-bottom: 0.5pt solid #cccccc;
            vertical-align: middle;
            padding: 2.5pt 4pt;
        }
        .courses-table .col-code {
            width: 75pt;
            font-weight: bold;
        }
        .courses-table .col-name {
            /* flex width */
        }
        .courses-table .col-credits {
            width: 55pt;
            text-align: center;
        }
        .courses-table .total-row td {
            border-top: 1.5pt solid #333333;
            border-bottom: 1.5pt solid #333333;
            background-color: transparent;
        }
        .signatures-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: {$signaturesMarginTop};
            margin-bottom: 4pt;
        }
        .signatures-table td {
            width: 50%;
            text-align: center;
            vertical-align: bottom;
            padding: 0 15pt;
        }
        .sig-space {
            height: 38pt;
        }
        .sig-line {
            border-top: 1.2pt solid #000000;
            margin: 0 auto 5pt auto;
            width: 185pt;
        }
        .sig-role {
            font-size: 7.5pt;
            font-weight: bold;
            line-height: 1.3;
        }
        .sig-name {
            font-size: 7.5pt;
            font-weight: bold;
            line-height: 1.3;
        }
        .sig-sub {
            font-size: 7.5pt;
            color: #222222;
        }
        .bottom-section {
            margin-top: {$bottomSectionMarginTop};
        }
        .date-line {
            text-align: right;
            font-size: 8pt;
            margin-top: 0;
            margin-bottom: 6pt;
            color: #111111;
        }
        .instructions-box {
            border: 1px solid #222222;
            padding: 4pt 6pt;
            margin-top: 0;
            margin-bottom: 5pt;
            background-color: #ffffff;
        }
            margin-top: 2pt;
            margin-bottom: 4pt;
            background-color: #ffffff;
        }
        .instructions-table {
            width: 100%;
            border-collapse: collapse;
        }
        .instructions-table .qr-col {
            width: 70pt;
            text-align: center;
            vertical-align: middle;
            padding-right: 5pt;
            border-right: 1px solid #cccccc;
        }
        .instructions-table .qr-col img {
            width: 60pt;
            height: 60pt;
            display: block;
            margin: 0 auto;
        }
        .instructions-table .text-col {
            padding-left: 7pt;
            vertical-align: middle;
            font-size: 6.8pt;
            line-height: 1.3;
        }
        .instructions-list {
            margin: 0;
            padding-left: 10pt;
        }
        .instructions-list li {
            margin-bottom: 1.5pt;
        }
        .footer-line {
            border-bottom: 1px solid #555555;
            margin-top: 3pt;
            margin-bottom: 2pt;
        }
        .footer-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 6.5pt;
            color: #222222;
        }
        .footer-left {
            text-align: left;
            width: 33%;
        }
        .footer-center {
            text-align: center;
            width: 33%;
        }
        .footer-right {
            text-align: right;
            width: 34%;
        }
    </style>
</head>
<body>
    <div style="position: relative;">
    {$watermarkHtml}

    <table class="header-table">
        <tr>
            <td class="header-left">
                <div class="header-university">{$university}</div>
                <div class="header-office">{$office}</div>
            </td>
            <td class="header-right">
                <div class="photo-box">
                    {$photoHtml}
                </div>
                <div class="student-code-header">Código: {$codigoUniversitario}</div>
            </td>
        </tr>
    </table>

    <div class="header-line"></div>

    <div class="doc-title">CONSTANCIA DE PREMATRÍCULA</div>

    <p class="statement-p">
        El presente documento da constancia de que, el estudiante en mención ha realizado y finalizado correctamente con el registro de su <strong>PREMATRÍCULA - {$periodo}</strong>
    </p>
    <p class="statement-valid">
        Siendo válido y parte de los requisitos para la matrícula en el periodo correspondiente.
    </p>

    <table class="details-table">
        <tr>
            <td class="lbl">CÓDIGO:</td>
            <td class="val">{$codigoUniversitario}</td>
        </tr>
        <tr>
            <td class="lbl">APELLIDOS:</td>
            <td class="val">{$apellidos}</td>
        </tr>
        <tr>
            <td class="lbl">NOMBRES:</td>
            <td class="val">{$nombres}</td>
        </tr>
        <tr>
            <td class="lbl">FACULTAD:</td>
            <td class="val">{$facultad}</td>
        </tr>
        <tr>
            <td class="lbl">ESCUELA:</td>
            <td class="val">{$escuela}</td>
        </tr>
        <tr>
            <td class="lbl">SEDE:</td>
            <td class="val">{$sede}</td>
        </tr>
        <tr>
            <td class="lbl">PERIODO:</td>
            <td class="val">{$periodo}</td>
        </tr>
        <tr>
            <td class="lbl">PREFERENCIA:</td>
            <td class="val">{$preferencia}</td>
        </tr>
        <tr>
            <td class="lbl">FECHA Y HORA DE EMISIÓN:</td>
            <td class="val">{$fechaHoraEmision}</td>
        </tr>
    </table>

    {$coursesTableHtml}

    <table class="signatures-table">
        <tr>
            <td>
                <div class="sig-space"></div>
                <div class="sig-line"></div>
                <div class="sig-role">DIRECTOR</div>
                <div class="sig-role">{$directorEscuela}</div>
            </td>
            <td>
                <div class="sig-space"></div>
                <div class="sig-line"></div>
                <div class="sig-name">{$estudianteNombreCompleto}</div>
                <div class="sig-sub">{$codigoUniversitario}</div>
            </td>
        </tr>
    </table>

    <div class="bottom-section">
        <div class="date-line">{$fechaPie}</div>

        <div class="instructions-box">
            <table class="instructions-table">
                <tr>
                    <td class="qr-col">
                        {$qrHtml}
                    </td>
                    <td class="text-col">
                        <ul class="instructions-list">
                            <li>El presente documento es parte de los requisitos para matricularse en el periodo correspondiente.</li>
                            <li>Imprimir, firmar la CONSTANCIA y presentarlo con su secretaria de escuela para su validación.</li>
                            <li>Se recomienda que la entrega del documento sea de forma presencial en OFICINA DE SU ESCUELA y se realice dentro las fechas establecidas para evitar inconvenientes.</li>
                            <li>Esta constancia no constituye matrícula definitiva.</li>
                        </ul>
                    </td>
                </tr>
            </table>
        </div>

        <div class="footer-line"></div>

        <table class="footer-table">
            <tr>
                <td class="footer-left">🌐 https://unitru.edu.pe/</td>
                <td class="footer-center">📞 44209020</td>
                <td class="footer-right">📍 AV. JUAN PABLO II S/N URB. SAN ANDRES</td>
            </tr>
        </table>
    </div>
    </div>
</body>
</html>
HTML;
    }

    private function getImageDataUri(string ...$relativePaths): string
    {
        foreach ($relativePaths as $rel) {
            $full = public_path($rel);
            if (is_file($full)) {
                $ext = pathinfo($full, PATHINFO_EXTENSION);
                $mime = $ext === 'png' ? 'image/png' : ($ext === 'jpg' || $ext === 'jpeg' ? 'image/jpeg' : 'image/png');
                $data = file_get_contents($full);
                if ($data !== false) {
                    return 'data:'.$mime.';base64,'.base64_encode($data);
                }
            }
        }

        return '';
    }
}
