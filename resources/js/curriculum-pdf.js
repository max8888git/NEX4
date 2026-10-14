/**
 * Real PDF files made in the browser with jsPDF. Loaded as a module next to the
 * classic page scripts, which pass the layout data already worked out:
 * - curriculum-form.js → window.McuCurriculumPdf.download(spec)
 * - college-timetable.js (Dean / Program Chair dashboard) → window.McuTimetablePdf.download(spec)
 */
import { jsPDF } from 'jspdf';
import { autoTable } from 'jspdf-autotable';

const PURPLE = [92, 38, 126];
const PURPLE_DARK = [45, 22, 63];
const GOLD = [249, 185, 55];
const MUTED = [111, 95, 134];
const LINE = [217, 207, 230];
const HEAD = [243, 238, 249];

function imageData(url) {
    return fetch(url)
        .then((r) => (r.ok ? r.blob() : null))
        .then(
            (blob) =>
                blob &&
                new Promise((resolve) => {
                    const fr = new FileReader();
                    fr.onload = () => resolve(fr.result);
                    fr.onerror = () => resolve(null);
                    fr.readAsDataURL(blob);
                }),
        )
        .catch(() => null);
}

async function download(spec) {
    const doc = new jsPDF({ unit: 'mm', format: 'a4' });
    const W = doc.internal.pageSize.getWidth();
    const M = 14;
    const logo = spec.logoUrl ? await imageData(spec.logoUrl) : null;

    // header
    if (logo) {
        doc.addImage(logo, 'JPEG', M, 12, 16, 16);
    }
    const x = logo ? M + 20 : M;
    doc.setTextColor(...PURPLE_DARK);
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(15);
    doc.text(spec.collegeName || '', x, 18);
    doc.setTextColor(...PURPLE);
    doc.setFontSize(12);
    doc.text((spec.programName || '') + ' Curriculum', x, 24);
    doc.setTextColor(...MUTED);
    doc.setFont('helvetica', 'normal');
    doc.setFontSize(9);
    doc.text(spec.subtitle || '', x, 29);
    doc.setDrawColor(...GOLD);
    doc.setLineWidth(1);
    doc.line(M, 33, W - M, 33);

    let y = 39;
    for (const term of spec.terms || []) {
        if (y > 260) {
            doc.addPage();
            y = 18;
        }
        doc.setTextColor(...PURPLE_DARK);
        doc.setFont('helvetica', 'bold');
        doc.setFontSize(10.5);
        doc.text(term.title, M, y);
        autoTable(doc, {
            startY: y + 2,
            margin: { left: M, right: M },
            head: [['Code', 'Descriptive title', 'Lec', 'Lab', 'Units', 'Prerequisite']],
            body: term.courses.map((c) => [c.labCode ? c.code + '\nLab: ' + c.labCode : c.code, c.title, c.lec, c.lab, c.units, c.prereqs]),
            foot: [['Total', '', term.totals.lec, term.totals.lab, term.totals.units, '']],
            theme: 'grid',
            styles: { font: 'helvetica', fontSize: 8.5, cellPadding: 1.6, lineColor: LINE, lineWidth: 0.2, textColor: [31, 18, 48], valign: 'top' },
            headStyles: { fillColor: HEAD, textColor: [75, 61, 94], fontStyle: 'bold', fontSize: 7.5 },
            footStyles: { fillColor: [250, 248, 252], textColor: [31, 18, 48], fontStyle: 'bold' },
            columnStyles: {
                0: { cellWidth: 26 },
                2: { cellWidth: 11, halign: 'center' },
                3: { cellWidth: 11, halign: 'center' },
                4: { cellWidth: 13, halign: 'center' },
                5: { cellWidth: 34 },
            },
            rowPageBreak: 'avoid',
        });
        y = doc.lastAutoTable.finalY + 8;
    }

    if (y > 255) {
        doc.addPage();
        y = 20;
    }
    doc.setTextColor(...PURPLE_DARK);
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(10);
    doc.text(spec.totalLine || '', M, y);
    y += 16;

    const signs = spec.signatures || [];
    if (signs.length) {
        if (y > 265) {
            doc.addPage();
            y = 30;
        }
        const w = (W - M * 2 - (signs.length - 1) * 8) / signs.length;
        signs.forEach((s, i) => {
            const sx = M + i * (w + 8);
            doc.setDrawColor(31, 18, 48);
            doc.setLineWidth(0.3);
            doc.line(sx, y, sx + w, y);
            doc.setFont('helvetica', 'bold');
            doc.setFontSize(9);
            doc.setTextColor(31, 18, 48);
            doc.text(s.name, sx, y + 4.5);
            doc.setFont('helvetica', 'normal');
            doc.setTextColor(...MUTED);
            doc.setFontSize(8);
            doc.text(s.role + (s.date ? ' · ' + s.date : ''), sx, y + 8.5);
        });
    }

    const pages = doc.getNumberOfPages();
    for (let p = 1; p <= pages; p++) {
        doc.setPage(p);
        doc.setFontSize(7.5);
        doc.setTextColor(...MUTED);
        doc.text((spec.footer || '') + '   ·   Page ' + p + ' of ' + pages, M, 290);
    }
    doc.save(spec.fileName || 'curriculum.pdf');
}

/** spec: { logoUrl, collegeName, title, subtitle, sections: [{ title, rows: [[day, time, code, subject, type, room]] }], footer, fileName } */
async function downloadTimetable(spec) {
    const doc = new jsPDF({ unit: 'mm', format: 'a4' });
    const W = doc.internal.pageSize.getWidth();
    const M = 14;
    const logo = spec.logoUrl ? await imageData(spec.logoUrl) : null;

    if (logo) {
        doc.addImage(logo, 'JPEG', M, 12, 16, 16);
    }
    const x = logo ? M + 20 : M;
    doc.setTextColor(...PURPLE_DARK);
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(15);
    doc.text(spec.collegeName || '', x, 18);
    doc.setTextColor(...PURPLE);
    doc.setFontSize(12);
    doc.text(spec.title || 'Timetable', x, 24);
    doc.setTextColor(...MUTED);
    doc.setFont('helvetica', 'normal');
    doc.setFontSize(9);
    doc.text(spec.subtitle || '', x, 29);
    doc.setDrawColor(...GOLD);
    doc.setLineWidth(1);
    doc.line(M, 33, W - M, 33);

    let y = 39;
    for (const section of spec.sections || []) {
        if (y > 255) {
            doc.addPage();
            y = 18;
        }
        doc.setTextColor(...PURPLE_DARK);
        doc.setFont('helvetica', 'bold');
        doc.setFontSize(10.5);
        doc.text(section.title, M, y);
        autoTable(doc, {
            startY: y + 2,
            margin: { left: M, right: M },
            head: [['Day', 'Time', 'Code', 'Subject', 'Type', 'Room']],
            body: section.rows,
            theme: 'grid',
            styles: { font: 'helvetica', fontSize: 8.5, cellPadding: 1.6, lineColor: LINE, lineWidth: 0.2, textColor: [31, 18, 48], valign: 'top' },
            headStyles: { fillColor: HEAD, textColor: [75, 61, 94], fontStyle: 'bold', fontSize: 7.5 },
            columnStyles: {
                0: { cellWidth: 22 },
                1: { cellWidth: 32 },
                2: { cellWidth: 22, fontStyle: 'bold' },
                4: { cellWidth: 16 },
                5: { cellWidth: 34 },
            },
            rowPageBreak: 'avoid',
        });
        y = doc.lastAutoTable.finalY + 8;
    }

    const pages = doc.getNumberOfPages();
    for (let p = 1; p <= pages; p++) {
        doc.setPage(p);
        doc.setFontSize(7.5);
        doc.setTextColor(...MUTED);
        doc.text((spec.footer || '') + '   ·   Page ' + p + ' of ' + pages, M, 290);
    }
    doc.save(spec.fileName || 'timetable.pdf');
}

window.McuCurriculumPdf = { download };
window.McuTimetablePdf = { download: downloadTimetable };
