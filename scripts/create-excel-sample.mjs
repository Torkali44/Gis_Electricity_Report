/**
 * Generates 4 sample Excel files — one for each supported import format.
 * Run: node scripts/create-excel-sample.mjs
 *
 * Uses ExcelJS for proper cell-background-color support.
 */
import ExcelJS from 'exceljs';
import { fileURLToPath } from 'url';
import { dirname, join } from 'path';
import { mkdirSync } from 'fs';

const __dir = dirname(fileURLToPath(import.meta.url));
const outDir = join(__dir, '..', 'public', 'excel-samples');
mkdirSync(outDir, { recursive: true });

/* ── Color helpers ─────────────────────────────────────────────────── */
const GREEN = { argb: 'FF00B050' };  // Excel standard green
const RED   = { argb: 'FFFF0000' };  // Excel standard red

function fillGreen() { return { type: 'pattern', pattern: 'solid', fgColor: GREEN }; }
function fillRed()   { return { type: 'pattern', pattern: 'solid', fgColor: RED };   }

function applyFill(cell, isValid) {
    cell.fill = isValid ? fillGreen() : fillRed();
    cell.font = { bold: true, color: { argb: 'FFFFFFFF' } };
}

function border() {
    const b = { style: 'thin', color: { argb: 'FF999999' } };
    return { top: b, left: b, bottom: b, right: b };
}

function hdr(cell, text) {
    cell.value = text;
    cell.font  = { bold: true };
    cell.fill  = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFD9D9D9' } };
    cell.border = border();
    cell.alignment = { horizontal: 'center', vertical: 'middle' };
}

function val(cell, text, isValid = null) {
    cell.value  = text;
    cell.border = border();
    cell.alignment = { horizontal: 'center', vertical: 'middle' };
    if (isValid !== null) applyFill(cell, isValid);
}

/* ══════════════════════════════════════════════════════════════════════
 * File 1 — MCB Image-style  (Format A — matches Image 1)
 * Structure: ELCB header rows + Have Label + column headers + data rows
 * ══════════════════════════════════════════════════════════════════════ */
async function createMcbImage() {
    const wb = new ExcelJS.Workbook();
    const ws = wb.addWorksheet('جدول MCB - نوع الصورة');
    ws.properties.defaultColWidth = 14;

    const groups = [
        {
            elcb: '300mA',
            haveLabel: 'Yes',
            rows: [
                { lm:'32A', lc:'4 mm²',          ls:'Valid',   rm:'32A', rc:'4 mm²',          rs:'Valid'   },
                { lm:'32A', lc:'4 mm²',          ls:'Valid',   rm:'32A', rc:'4 mm²',          rs:'Valid'   },
                { lm:'32A', lc:'4 mm²',          ls:'Valid',   rm:'32A', rc:'4 mm²',          rs:'Valid'   },
                { lm:'20A', lc:'Spare',          ls:'Valid',   rm:'32A', rc:'Spare',          rs:'Valid'   },
                { lm:'32A', lc:'Spare',          ls:'Valid',   rm:'20A', rc:'2.5 mm²',        rs:'Valid'   },
                { lm:'10A', lc:'2.5 ring mm²',  ls:'Invalid', rm:'10A', rc:'2.5 mm²',        rs:'Invalid' },
                { lm:'10A', lc:'1.5 mm²',        ls:'Valid',   rm:'32A', rc:'1.5 mm²',        rs:'Invalid' },
                { lm:'10A', lc:'1.5 mm²',        ls:'Valid',   rm:'10A', rc:'1.5 mm²',        rs:'Valid'   },
                { lm:'32A', lc:'2.5 mm²',        ls:'Invalid', rm:'10A', rc:'1.5 mm²',        rs:'Valid'   },
            ],
        },
        {
            elcb: '30mA',
            haveLabel: 'Yes',
            rows: [
                { lm:'32A', lc:'Spare',          ls:'Valid',   rm:'32A', rc:'2.5 ring mm²',  rs:'Valid'   },
                { lm:'10A', lc:'Spare',          ls:'Valid',   rm:'10A', rc:'2.5 ring mm²',  rs:'Invalid' },
                { lm:'32A', lc:'4 mm²',          ls:'Valid',   rm:'32A', rc:'2.5+1.5 mm²',   rs:'Invalid' },
                { lm:'32A', lc:'4 mm²',          ls:'Valid',   rm:'10A', rc:'Spare',          rs:'Valid'   },
                { lm:'20A', lc:'2.5 mm²',        ls:'Valid',   rm:'20A', rc:'Spare',          rs:'Valid'   },
            ],
        },
    ];

    let r = 1;
    for (const g of groups) {
        // ELCB row
        const elcbRow = ws.getRow(r++);
        val(elcbRow.getCell(1), 'ELCB');
        val(elcbRow.getCell(4), g.elcb);

        // Have Label row
        const labelRow = ws.getRow(r++);
        val(labelRow.getCell(1), 'Have Label?');
        const hlCell = labelRow.getCell(4);
        val(hlCell, g.haveLabel, true);

        // Column headers
        const hdrRow = ws.getRow(r++);
        ['MCB Rating','Cable Size','Validation Status','MCB Rating','Cable Size','Validation Status'].forEach((h,i) => hdr(hdrRow.getCell(i+1), h));

        // Data rows
        for (const d of g.rows) {
            const dr = ws.getRow(r++);
            val(dr.getCell(1), d.lm, d.ls==='Valid');
            val(dr.getCell(2), d.lc, d.ls==='Valid');
            val(dr.getCell(3), d.ls, d.ls==='Valid');
            val(dr.getCell(4), d.rm, d.rs==='Valid');
            val(dr.getCell(5), d.rc, d.rs==='Valid');
            val(dr.getCell(6), d.rs, d.rs==='Valid');
        }
        r++; // blank separator
    }

    await wb.xlsx.writeFile(join(outDir, 'sample-A-mcb-image-style.xlsx'));
    console.log('✅  sample-A-mcb-image-style.xlsx');
}

/* ══════════════════════════════════════════════════════════════════════
 * File 2 — Grounding table  (Format B — matches Image 2)
 * ══════════════════════════════════════════════════════════════════════ */
async function createGrounding() {
    const wb = new ExcelJS.Workbook();
    const ws = wb.addWorksheet('فحص التأريض واللوحة الرئيسية');
    ws.properties.defaultColWidth = 30;

    const rows = [
        // [col-A (value/header), col-B (label/header), isValid or null]
        ['فحص التأريض', null,                                null    ], // section header
        ['نعم/لا',       'هل كابل التأريض موجود؟',            true    ], // green
        ['4.01 Ohm',    'قراءة فحص التأريض',                  false   ], // red (high ohm = bad)
        ['ضغط لوحة التوزيع الرئيسية', null,                   null    ], // section header
        ['408.6 VOLT',  'R-Y',                                null    ],
        ['405.0 VOLT',  'R-B',                                null    ],
        ['404.9 VOLT',  'Y-B',                                null    ],
        ['235.8 VOLT',  'R-N',                                null    ],
        ['235.3 VOLT',  'Y-N',                                null    ],
        ['236.8 VOLT',  'B-N',                                null    ],
    ];

    rows.forEach(([a, b, isValid], i) => {
        const row = ws.getRow(i + 1);
        const ca = row.getCell(1);
        const cb = row.getCell(2);

        if (b === null) {
            // Section header — merge A+B
            ca.value = a;
            ca.font  = { bold: true };
            ca.fill  = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFD9E1F2' } };
            ca.alignment = { horizontal: 'center' };
            ca.border = border();
            cb.border = border();
            ws.mergeCells(i+1, 1, i+1, 2);
        } else {
            val(ca, a, isValid);
            val(cb, b);
        }
    });

    ws.getColumn(1).width = 22;
    ws.getColumn(2).width = 35;

    await wb.xlsx.writeFile(join(outDir, 'sample-B-grounding.xlsx'));
    console.log('✅  sample-B-grounding.xlsx');
}

/* ══════════════════════════════════════════════════════════════════════
 * File 3 — Column-header detailed MCB  (Format C)
 * ══════════════════════════════════════════════════════════════════════ */
async function createMcbDetailed() {
    const wb = new ExcelJS.Workbook();
    const ws = wb.addWorksheet('MCB Detailed');
    ws.properties.defaultColWidth = 14;

    const headers = ['ELCB','Have Label','MCB Left','Cable Left','Status Left','MCB Right','Cable Right','Status Right'];
    const hRow = ws.getRow(1);
    headers.forEach((h,i) => hdr(hRow.getCell(i+1), h));

    const data = [
        { elcb:'300mA', hl:'Yes', lm:'10A', lc:'1.5', ls:'Valid',   rm:'16A', rc:'2.5', rs:'Valid'   },
        { elcb:'300mA', hl:'',   lm:'10A', lc:'1.5', ls:'Valid',   rm:'10A', rc:'1.5', rs:'Invalid' },
        { elcb:'300mA', hl:'',   lm:'16A', lc:'2.5', ls:'Valid',   rm:'20A', rc:'4',   rs:'Valid'   },
        { elcb:'300mA', hl:'',   lm:'10A', lc:'1.5', ls:'Invalid', rm:'10A', rc:'1.5', rs:'Valid'   },
        { elcb:'30mA',  hl:'Yes', lm:'10A', lc:'1.5', ls:'Valid',   rm:'10A', rc:'1.5', rs:'Valid'   },
        { elcb:'30mA',  hl:'',   lm:'16A', lc:'2.5', ls:'Valid',   rm:'6A',  rc:'1',   rs:'Invalid' },
        { elcb:'30mA',  hl:'',   lm:'10A', lc:'1.5', ls:'Valid',   rm:'10A', rc:'1.5', rs:'Valid'   },
    ];

    data.forEach((d,i) => {
        const row = ws.getRow(i+2);
        val(row.getCell(1), d.elcb); val(row.getCell(2), d.hl);
        val(row.getCell(3), d.lm, d.ls==='Valid'); val(row.getCell(4), d.lc, d.ls==='Valid'); val(row.getCell(5), d.ls, d.ls==='Valid');
        val(row.getCell(6), d.rm, d.rs==='Valid'); val(row.getCell(7), d.rc, d.rs==='Valid'); val(row.getCell(8), d.rs, d.rs==='Valid');
    });

    await wb.xlsx.writeFile(join(outDir, 'sample-C-mcb-detailed.xlsx'));
    console.log('✅  sample-C-mcb-detailed.xlsx');
}

/* ══════════════════════════════════════════════════════════════════════
 * File 4 — Summary MCB  (Format D)
 * ══════════════════════════════════════════════════════════════════════ */
async function createMcbSummary() {
    const wb = new ExcelJS.Workbook();
    const ws = wb.addWorksheet('ملخص اللوحات');

    const headers = ['رقم','اسم اللوحة / الطابق','الوصف','عدد ELCB','عدد MCB المدخلة','صفوف صحيحة','صفوف غير صحيحة','عدد الملاحظات','ملاحظات غير سليمة'];
    const hRow = ws.getRow(1);
    headers.forEach((h,i) => hdr(hRow.getCell(i+1), h));

    const data = [
        [1, 'الطابق الأول',  '—', 2, 24, 24, 0, 0, 0],
        [2, 'الطابق الأرضي', '—', 2, 24, 23, 1, 1, 1],
    ];

    data.forEach((d,i) => {
        const row = ws.getRow(i+2);
        d.forEach((v,j) => val(row.getCell(j+1), v));
    });

    [18,25,12,12,18,14,18,16,18].forEach((w,i) => { ws.getColumn(i+1).width = w; });

    await wb.xlsx.writeFile(join(outDir, 'sample-D-mcb-summary.xlsx'));
    console.log('✅  sample-D-mcb-summary.xlsx');
}

/* ── Run all ─────────────────────────────────────────────────────────── */
Promise.all([createMcbImage(), createGrounding(), createMcbDetailed(), createMcbSummary()])
    .then(() => console.log('\n✅  All 4 sample files saved to public/excel-samples/'))
    .catch(err => { console.error(err); process.exit(1); });
