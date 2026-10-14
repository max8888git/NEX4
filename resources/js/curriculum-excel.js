/**
 * Excel (.xlsx) curriculum files in the browser, with ExcelJS (loaded only when used):
 * - write(spec): the sheet with set column widths, a frozen headings row, a heading per term,
 *   and dropdowns for Year Level, Semester, room types and Elective; plus a "How to fill" sheet.
 * - read(file): the first sheet's rows of cells, for CurriculumStore.parseGrid.
 * The rows come from McuCurriculumForm.sheetRows, the same layout as the CSV.
 */
const PURPLE = 'FF5C267E';
const SOFT = 'FFF3EEF9';
const WIDTHS = [14, 42, 9, 9, 10, 15, 15, 10, 12, 22, 22, 18, 18, 18, 10];

async function lib() {
    const mod = await import('exceljs');
    return mod.default || mod;
}

function download(name, blob) {
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = name;
    document.body.appendChild(a);
    a.click();
    setTimeout(() => {
        URL.revokeObjectURL(a.href);
        a.remove();
    }, 1000);
}

// spec: { rows: [[cells] | [] | { heading }], roomTypes: [names], fileName }
async function write(spec) {
    const ExcelJS = await lib();
    const wb = new ExcelJS.Workbook();
    wb.creator = 'MCU-NEX';
    const ws = wb.addWorksheet('Curriculum', { views: [{ state: 'frozen', ySplit: 1 }] });
    const cols = spec.rows[0].length;
    ws.columns = spec.rows[0].map((h, i) => ({ width: WIDTHS[i] || 14 }));

    spec.rows.forEach((r, i) => {
        if (i === 0) {
            const head = ws.addRow(r);
            head.height = 30;
            head.eachCell((cell) => {
                cell.font = { bold: true, color: { argb: 'FFFFFFFF' } };
                cell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: PURPLE } };
                cell.alignment = { vertical: 'middle', horizontal: 'center', wrapText: true };
            });
            return;
        }
        if (r.heading) {
            const row = ws.addRow([r.heading]);
            ws.mergeCells(row.number, 1, row.number, cols);
            row.getCell(1).font = { bold: true, color: { argb: PURPLE } };
            row.getCell(1).fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: SOFT } };
            return;
        }
        ws.addRow(r);
    });

    // dropdowns and number checks for this sheet and a few hundred rows more to fill in
    const last = Math.max(ws.rowCount + 300, 400);
    const list = (values) => ({ type: 'list', allowBlank: true, formulae: ['"' + values.join(',') + '"'], showErrorMessage: true });
    const range = (col) => col + '2:' + col + last;
    ws.dataValidations.add(range('H'), list(['1', '2', '3', '4', '5', '6', '7', '8']));
    ws.dataValidations.add(range('I'), list(['1', '2', 'Summer']));
    ws.dataValidations.add(range('O'), list(['Yes']));
    ['C', 'D'].forEach((col) => ws.dataValidations.add(range(col), {
        type: 'decimal', operator: 'between', allowBlank: true, formulae: [0, 12],
        showErrorMessage: true, errorTitle: 'Units', error: 'Units are a number from 0 to 12.',
    }));
    const types = (spec.roomTypes || []).filter((t) => !/[",]/.test(t));
    if (types.length && types.join(',').length < 250) {
        ['J', 'K'].forEach((col) => ws.dataValidations.add(range(col), list(types)));
    }

    const help = wb.addWorksheet('How to fill');
    help.columns = [{ width: 110 }];
    [
        'How to fill the curriculum sheet',
        '',
        'One row per subject. Keep the first row (the column names) as it is.',
        'Course Code, Course Title, Year Level (1–8) and Semester (1, 2 or Summer) are required. Year Level, Semester, room types and Elective have dropdowns.',
        'Put a subject’s lab on the same row (Lab Units). It gets the “L” code (CCS 101L) by itself.',
        'Lecture Room Type and Lab Room Type must be one of: ' + ((spec.roomTypes || []).join(', ') || 'the room types in Data management') + '.',
        'Prerequisite 1–3: a course code from an earlier term, a year standing such as “3rd Year Standing”, or blank for none.',
        'Elective: Yes, or blank. Total Units and Hours are worked out by the system, so you can leave them blank.',
        'The purple rows (“1st Year - 2nd Semester”) only separate the terms. You can add more, or leave Year Level and Semester blank on rows under them.',
        'When it’s ready: Curriculum → Start a curriculum (or Make a new version) → A file → choose this file.',
    ].forEach((t, i) => {
        const row = help.addRow([t]);
        row.getCell(1).alignment = { wrapText: true, vertical: 'top' };
        if (i === 0) row.getCell(1).font = { bold: true, size: 14, color: { argb: PURPLE } };
    });

    const buf = await wb.xlsx.writeBuffer();
    download(spec.fileName || 'curriculum.xlsx', new Blob([buf], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' }));
}

// the first sheet (or the one named "Curriculum") as rows of text cells
async function read(file) {
    const ExcelJS = await lib();
    const wb = new ExcelJS.Workbook();
    try {
        await wb.xlsx.load(await file.arrayBuffer());
    } catch (e) {
        throw new Error('That Excel file couldn’t be read. Save it as .xlsx and try again.');
    }
    const ws = wb.getWorksheet('Curriculum') || wb.worksheets[0];
    if (!ws) throw new Error('That Excel file has no sheets.');
    const rows = [];
    ws.eachRow({ includeEmpty: true }, (row) => {
        const cells = [];
        for (let c = 1; c <= Math.max(ws.columnCount, 15); c++) {
            const cell = row.getCell(c);
            // a merged heading repeats its text in every merged cell; keep it once
            cells.push(cell.isMerged && cell.master.address !== cell.address ? '' : String(cell.text == null ? '' : cell.text).trim());
        }
        rows.push(cells);
    });
    return rows;
}

window.McuCurriculumExcel = { write, read };
