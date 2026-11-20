/**
 * Generador mínimo de archivos .xlsx en el navegador (sin dependencias).
 * Excel lo abre con columnas separadas en cualquier configuración regional (a diferencia del CSV, que depende
 * del separador de lista del sistema) y permite estilos: título, encabezados con color, bordes y anchos.
 *
 * Uso: downloadXlsx('archivo.xlsx', { sheetName, title, subtitle, columns: [{ header, width, numeric }], rows: [[...]] })
 */

const STYLE = { default: 0, title: 1, subtitle: 2, header: 3, text: 4, number: 5 };

const escapeXml = value => String(value ?? '').replace(/[<>&"']/g, char => ({ '<': '&lt;', '>': '&gt;', '&': '&amp;', '"': '&quot;', "'": '&apos;' }[char]))
    // Quita caracteres de control no válidos en XML.
    .replace(/[\u0000-\u0008\u000B\u000C\u000E-\u001F]/g, '');

const columnName = index => { let name = ''; for (let n = index + 1; n > 0; n = Math.floor((n - 1) / 26)) name = String.fromCharCode(65 + ((n - 1) % 26)) + name; return name; };

function cell(ref, value, style) {
    if (typeof value === 'number' && Number.isFinite(value)) return '<c r="' + ref + '" s="' + style + '"><v>' + value + '</v></c>';
    return '<c r="' + ref + '" s="' + style + '" t="inlineStr"><is><t xml:space="preserve">' + escapeXml(value) + '</t></is></c>';
}

function sheetXml({ title, subtitle, columns, rows }) {
    const last = columnName(columns.length - 1);
    const xmlRows = [];
    let r = 1;
    if (title) xmlRows.push('<row r="' + r + '" ht="24" customHeight="1">' + cell('A' + r, title, STYLE.title) + '</row>'), r++;
    if (subtitle) xmlRows.push('<row r="' + r + '">' + cell('A' + r, subtitle, STYLE.subtitle) + '</row>'), r++;
    if (title || subtitle) r++; // fila en blanco antes de la tabla
    const headerRow = r;
    xmlRows.push('<row r="' + r + '" ht="22" customHeight="1">' + columns.map((column, i) => cell(columnName(i) + r, column.header, STYLE.header)).join('') + '</row>');
    rows.forEach(values => {
        r++;
        xmlRows.push('<row r="' + r + '">' + columns.map((column, i) => cell(columnName(i) + r, values[i], column.numeric ? STYLE.number : STYLE.text)).join('') + '</row>');
    });
    const merges = [title && 'A1:' + last + '1', subtitle && 'A' + (title ? 2 : 1) + ':' + last + (title ? 2 : 1)].filter(Boolean);
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' +
        '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">' +
        // Encabezado de la tabla fijo al desplazarse.
        '<sheetViews><sheetView workbookViewId="0"><pane ySplit="' + headerRow + '" topLeftCell="A' + (headerRow + 1) + '" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>' +
        '<cols>' + columns.map((column, i) => '<col min="' + (i + 1) + '" max="' + (i + 1) + '" width="' + (column.width || 14) + '" customWidth="1"/>').join('') + '</cols>' +
        '<sheetData>' + xmlRows.join('') + '</sheetData>' +
        (rows.length ? '<autoFilter ref="A' + headerRow + ':' + last + (headerRow + rows.length) + '"/>' : '') +
        (merges.length ? '<mergeCells count="' + merges.length + '">' + merges.map(ref => '<mergeCell ref="' + ref + '"/>').join('') + '</mergeCells>' : '') +
        '</worksheet>';
}

// Colores del sistema: azul institucional #073C70 para encabezados, bordes #D8DEE5.
const STYLES_XML = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' +
    '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' +
    '<fonts count="4">' +
    '<font><sz val="11"/><name val="Calibri"/></font>' +
    '<font><b/><sz val="14"/><color rgb="FF073C70"/><name val="Calibri"/></font>' +
    '<font><i/><sz val="10"/><color rgb="FF5E7490"/><name val="Calibri"/></font>' +
    '<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>' +
    '</fonts>' +
    '<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>' +
    '<fill><patternFill patternType="solid"><fgColor rgb="FF073C70"/><bgColor indexed="64"/></patternFill></fill></fills>' +
    '<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border>' +
    '<border><left style="thin"><color rgb="FFD8DEE5"/></left><right style="thin"><color rgb="FFD8DEE5"/></right><top style="thin"><color rgb="FFD8DEE5"/></top><bottom style="thin"><color rgb="FFD8DEE5"/></bottom><diagonal/></border></borders>' +
    '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>' +
    '<cellXfs count="6">' +
    '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>' +
    '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>' +
    '<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1"/>' +
    '<xf numFmtId="0" fontId="3" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>' +
    '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment vertical="center"/></xf>' +
    '<xf numFmtId="1" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>' +
    '</cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';

function workbookFiles(options) {
    const sheetName = escapeXml((options.sheetName || 'Hoja1').replace(/[\\/?*[\]:]/g, ' ').slice(0, 31));
    return {
        '[Content_Types].xml': '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">' +
            '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/>' +
            '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>' +
            '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>' +
            '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>',
        '_rels/.rels': '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' +
            '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>',
        'xl/workbook.xml': '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">' +
            '<sheets><sheet name="' + sheetName + '" sheetId="1" r:id="rId1"/></sheets></workbook>',
        'xl/_rels/workbook.xml.rels': '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' +
            '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>' +
            '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>',
        'xl/styles.xml': STYLES_XML,
        'xl/worksheets/sheet1.xml': sheetXml(options),
    };
}

// ZIP sin compresión (método "store"), suficiente para un .xlsx pequeño.
const CRC_TABLE = (() => { const table = new Uint32Array(256); for (let n = 0; n < 256; n++) { let c = n; for (let k = 0; k < 8; k++) c = c & 1 ? 0xEDB88320 ^ (c >>> 1) : c >>> 1; table[n] = c >>> 0; } return table; })();
const crc32 = bytes => { let crc = 0xFFFFFFFF; for (const byte of bytes) crc = CRC_TABLE[(crc ^ byte) & 0xFF] ^ (crc >>> 8); return (crc ^ 0xFFFFFFFF) >>> 0; };

function zip(files) {
    const encoder = new TextEncoder();
    const chunks = [];
    const central = [];
    let offset = 0;
    const DOS_DATE = 0x0021; // 01/01/1980
    for (const [name, content] of Object.entries(files)) {
        const nameBytes = encoder.encode(name);
        const data = encoder.encode(content);
        const crc = crc32(data);
        const local = new DataView(new ArrayBuffer(30));
        [[0, 0x04034b50, 4], [4, 20, 2], [6, 0x0800, 2], [8, 0, 2], [10, 0, 2], [12, DOS_DATE, 2], [14, crc, 4], [18, data.length, 4], [22, data.length, 4], [26, nameBytes.length, 2], [28, 0, 2]]
            .forEach(([at, value, size]) => size === 4 ? local.setUint32(at, value, true) : local.setUint16(at, value, true));
        chunks.push(new Uint8Array(local.buffer), nameBytes, data);
        const entry = new DataView(new ArrayBuffer(46));
        [[0, 0x02014b50, 4], [4, 20, 2], [6, 20, 2], [8, 0x0800, 2], [10, 0, 2], [12, 0, 2], [14, DOS_DATE, 2], [16, crc, 4], [20, data.length, 4], [24, data.length, 4], [28, nameBytes.length, 2], [30, 0, 2], [32, 0, 2], [34, 0, 2], [36, 0, 2], [38, 0, 4], [42, offset, 4]]
            .forEach(([at, value, size]) => size === 4 ? entry.setUint32(at, value, true) : entry.setUint16(at, value, true));
        central.push(new Uint8Array(entry.buffer), nameBytes);
        offset += 30 + nameBytes.length + data.length;
    }
    const centralSize = central.reduce((sum, part) => sum + part.length, 0);
    const end = new DataView(new ArrayBuffer(22));
    const count = Object.keys(files).length;
    [[0, 0x06054b50, 4], [4, 0, 2], [6, 0, 2], [8, count, 2], [10, count, 2], [12, centralSize, 4], [16, offset, 4], [20, 0, 2]]
        .forEach(([at, value, size]) => size === 4 ? end.setUint32(at, value, true) : end.setUint16(at, value, true));
    return new Blob([...chunks, ...central, new Uint8Array(end.buffer)], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
}

export function buildXlsx(options) { return zip(workbookFiles(options)); }

export function downloadXlsx(filename, options) {
    const url = URL.createObjectURL(buildXlsx(options));
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    link.remove();
    setTimeout(() => URL.revokeObjectURL(url), 1000);
}
