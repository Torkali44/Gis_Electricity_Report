/**
 * Admin Alpine components — must load before Alpine.start()
 */

import * as XLSX from 'xlsx';

/**
 * Auto-color logic:
 *  Valid / Yes / نعم / OK / Correct / Pass / Passed / صح / سليم   → green
 *  Invalid / No  / لا  / Fail / Error / Bad / Wrong / خطأ / غير سليم → red
 *  empty                                                              → keep as-is
 */
function autoColor(value) {
    const v = (value || '').trim().toLowerCase();
    if (v === '') return null; // no change

    const greenWords = [
        'valid', 'yes', 'نعم', 'ok', 'correct', 'pass', 'passed',
        'صح', 'سليم', 'موجود', 'جيد', 'ممتاز', 'مقبول', 'صالح', 'يعمل',
        'good', 'fine', 'acceptable', 'working',
    ];
    const redWords = [
        'invalid', 'notvalid', 'not valid', 'not_valid', 'no', 'لا',
        'fail', 'failed', 'error', 'bad', 'wrong',
        'خطأ', 'غير سليم', 'غير موجود', 'معطل', 'تالف', 'لا يعمل',
        'not ok', 'not good', 'fault', 'broken', 'missing',
    ];

    if (/^not\s*valid$/i.test(v) || v === 'notvalid') return 'red';
    if (greenWords.some(w => v === w || v.startsWith(w))) return 'green';
    if (redWords.some(w => v === w || v.startsWith(w)))   return 'red';

    // If value is a pure number, don't change color
    if (!isNaN(parseFloat(v)) && isFinite(v)) return null;

    return null; // unknown → no automatic change
}

/* ═══════════════════════════════════════════════════════════════════
 * Module-level Excel helpers (shared across all format parsers)
 * ═══════════════════════════════════════════════════════════════════ */

/** Extract RGB hex from a SheetJS cell's style fill (solid or pattern). */
function _getCellBgRgb(cell) {
    if (!cell || !cell.s) return null;
    const s = cell.s;
    // SheetJS solid fill: fgColor holds the background for patternType='solid'
    const raw = s.fgColor?.rgb || s.fgColor?.argb
             || s.bgColor?.rgb || s.bgColor?.argb
             || null;
    if (!raw || raw === '00000000' || raw === 'FFFFFFFF' || raw === 'FF000000') return null;
    // Strip alpha channel (8-char ARGB → 6-char RGB)
    return (raw.length === 8 ? raw.slice(2) : raw).toUpperCase();
}

/** Map an Excel cell's background RGB to our 'green' | 'red' | null label. */
function _rgbToLabel(rgb) {
    if (!rgb) return null;
    const r = parseInt(rgb.slice(0, 2), 16);
    const g = parseInt(rgb.slice(2, 4), 16);
    const b = parseInt(rgb.slice(4, 6), 16);
    // Green family: #00B050, #92D050, #70AD47, #00FF00, #008000 …
    if (g > r && g > b && g > 80) return 'green';
    // Red family: #FF0000, #C00000, #FF4444, #FF0000 …
    if (r > g * 1.4 && r > b * 1.4 && r > 120) return 'red';
    return null;
}

/**
 * Convert a sheet to [{vals: string[], colors: ('green'|'red'|null)[]}]
 * Handles merged cells by filling the top-left value across the merge range.
 * Prefers ExcelJS-style fill when provided via optional colorMap.
 */
function _sheetToMatrix(sheet, colorMap = null) {
    if (!sheet || !sheet['!ref']) return [];
    const range   = XLSX.utils.decode_range(sheet['!ref']);
    const merges  = sheet['!merges'] || [];

    const mergeMap = {};
    for (const m of merges) {
        const topLeft = XLSX.utils.encode_cell({ r: m.s.r, c: m.s.c });
        const tlCell  = sheet[topLeft];
        const tlVal   = tlCell ? String(tlCell.v ?? '').trim() : '';
        for (let R = m.s.r; R <= m.e.r; R++) {
            for (let C = m.s.c; C <= m.e.c; C++) {
                mergeMap[XLSX.utils.encode_cell({ r: R, c: C })] = tlVal;
            }
        }
    }

    const rows = [];
    for (let R = range.s.r; R <= range.e.r; R++) {
        const vals = [], colors = [];
        for (let C = range.s.c; C <= range.e.c; C++) {
            const addr = XLSX.utils.encode_cell({ r: R, c: C });
            const cell = sheet[addr];
            const mergedVal = mergeMap[addr];
            vals.push(mergedVal !== undefined ? mergedVal : (cell ? String(cell.v ?? '').trim() : ''));
            let color = colorMap?.[`${R}:${C}`] ?? null;
            if (!color && cell) color = _rgbToLabel(_getCellBgRgb(cell));
            colors.push(color);
        }
        rows.push({ vals, colors });
    }
    return rows;
}

/** Build per-sheet row/col → green|red maps using ExcelJS (reads fills reliably). */
async function _excelJsColorMaps(arrayBuffer) {
    try {
        const ExcelJS = (await import('exceljs')).default;
        const wb = new ExcelJS.Workbook();
        await wb.xlsx.load(arrayBuffer);
        const byName = {};
        wb.eachSheet((ws) => {
            const map = {};
            ws.eachRow({ includeEmpty: true }, (row, rowNumber) => {
                row.eachCell({ includeEmpty: true }, (cell, colNumber) => {
                    const fill = cell.fill;
                    if (!fill || fill.type !== 'pattern') return;
                    const argb = fill.fgColor?.argb || fill.bgColor?.argb || fill.fgColor?.rgb;
                    if (!argb) return;
                    const rgb = (String(argb).length === 8 ? String(argb).slice(2) : String(argb)).toUpperCase();
                    const label = _rgbToLabel(rgb);
                    if (label) map[`${rowNumber - 1}:${colNumber - 1}`] = label;
                });
            });
            byName[ws.name] = map;
        });
        return byName;
    } catch (e) {
        console.warn('ExcelJS color read skipped:', e);
        return null;
    }
}

/** Detect which of the 4 formats the matrix represents. */
function _detectFormat(matrix) {
    let sawSummary = false;
    for (const { vals } of matrix) {
        const flat = vals.map(v => String(v).toLowerCase().trim());
        const raw0 = String(vals[0] ?? '').trim();

        // Grounding table keywords (check first — Ohm / تأريض)
        if (flat.some(v => v.includes('فحص التأريض') || v.includes('grounding check'))) return 'grounding';
        if (flat.some(v => v.includes('هل كابل التأريض') || v.includes('قراءة فحص التأريض'))) return 'grounding';
        if (flat.some(v => v.includes('تأريض') || /\bohm\b/i.test(v))) return 'grounding';
        if (flat.some(v => /^[ryb]-[rybn]$/i.test(v))) return 'grounding';

        // Arabic / English board detail (لوحة التوزيع export)
        if (/قاطع\s*الحماية/.test(raw0) || raw0 === 'النوع' || raw0.includes('قيمة القاطع') || raw0.includes('ملف التعريف')) {
            return 'mcb_image';
        }
        if (flat[0] === 'elcb' || flat[0] === 'لوحة التوزيع') return 'mcb_image';
        if (flat.some(v =>
            v.includes('mcb rating') || v.includes('validation status') || v.includes('cable size')
            || v === 'status' || v.includes('have lable') || v.includes('have label')
        )) {
            return 'mcb_image';
        }

        // Column-header detailed MCB
        if (flat.some(v => v.includes('mcb left') || v.includes('status left') || v.includes('cable left'))) return 'mcb_detailed';

        // Summary format keywords
        if (flat.some(v => v.includes('صفوف صحيحة') || v.includes('عدد mcb') || v.includes('mcb count') || v.includes('ملخص عام'))) {
            sawSummary = true;
        }
    }
    return sawSummary ? 'mcb_summary' : 'mcb_summary';
}

/** True if value looks like Valid/Invalid status. */
function _looksLikeStatus(v) {
    const t = String(v ?? '').trim().toLowerCase().replace(/\s+/g, '');
    return ['valid', 'invalid', 'notvalid', 'ok', 'fail', 'passed', 'failed'].includes(t)
        || t === 'سليم' || t === 'غيرسليم';
}

/** True if value looks like an MCB amp rating (10A, 30A, Spare…). */
function _looksLikeMcb(v) {
    const t = String(v ?? '').trim();
    if (!t) return false;
    if (/^\d+(\.\d+)?\s*a$/i.test(t)) return true;
    if (/^spare$/i.test(t)) return true;
    return false;
}

/**
 * Detect left/right column indices from an MCB header row.
 * Excel لوحة التوزيع uses: Status | Cable | MCB | # | MCB | Cable | Status
 * Report/UI uses:          MCB | Cable | Status | MCB | Cable | Status
 */
function _detectMcbColMap(rowVals) {
    const lower = rowVals.map(v => String(v ?? '').toLowerCase().trim());
    const statusIdx = [], cableIdx = [], mcbIdx = [];
    lower.forEach((v, i) => {
        if (!v || v === '#') return;
        if (v === 'status' || v.includes('validation')) statusIdx.push(i);
        else if (v.includes('cable')) cableIdx.push(i);
        else if (v.includes('mcb')) mcbIdx.push(i);
    });

    if (statusIdx.length && cableIdx.length && mcbIdx.length) {
        // Status | Cable | MCB | # | MCB | Cable | Status
        if (statusIdx[0] < cableIdx[0] && cableIdx[0] < mcbIdx[0]) {
            return {
                left:  { status: statusIdx[0], cable: cableIdx[0], mcb: mcbIdx[0] },
                right: {
                    mcb: mcbIdx[1] ?? mcbIdx[0],
                    cable: cableIdx[1] ?? cableIdx[0],
                    status: statusIdx[1] ?? statusIdx[0],
                },
            };
        }
        // MCB | Cable | Status | MCB | Cable | Status
        if (mcbIdx[0] < cableIdx[0] && cableIdx[0] < statusIdx[0]) {
            return {
                left:  { mcb: mcbIdx[0], cable: cableIdx[0], status: statusIdx[0] },
                right: {
                    mcb: mcbIdx[1] ?? statusIdx[0] + 1,
                    cable: cableIdx[1] ?? statusIdx[0] + 2,
                    status: statusIdx[1] ?? statusIdx[0] + 3,
                },
            };
        }
    }
    return null;
}

/** Infer column map from a data row when header detection fails. */
function _inferColMapFromData(rowVals) {
    const v = rowVals.map(x => String(x ?? '').trim());
    // Status | Cable | MCB | # | MCB | Cable | Status  (7 cols, # in middle)
    if (v.length >= 7 && _looksLikeStatus(v[0]) && _looksLikeMcb(v[2]) && _looksLikeMcb(v[4]) && _looksLikeStatus(v[6])) {
        return {
            left:  { status: 0, cable: 1, mcb: 2 },
            right: { mcb: 4, cable: 5, status: 6 },
        };
    }
    // MCB | Cable | Status | MCB | Cable | Status
    if (v.length >= 6 && _looksLikeMcb(v[0]) && _looksLikeStatus(v[2])) {
        return {
            left:  { mcb: 0, cable: 1, status: 2 },
            right: { mcb: 3, cable: 4, status: 5 },
        };
    }
    // Status | Cable | MCB without right side fully present
    if (v.length >= 3 && _looksLikeStatus(v[0]) && _looksLikeMcb(v[2])) {
        return {
            left:  { status: 0, cable: 1, mcb: 2 },
            right: { mcb: 4, cable: 5, status: 6 },
        };
    }
    return {
        left:  { mcb: 0, cable: 1, status: 2 },
        right: { mcb: 3, cable: 4, status: 5 },
    };
}

function _defaultMcbColMap() {
    return {
        left:  { mcb: 0, cable: 1, status: 2 },
        right: { mcb: 3, cable: 4, status: 5 },
    };
}

/** Normalize Have Label Arabic/English to Yes/No for the report. */
function _normalizeHaveLabel(value) {
    const t = String(value ?? '').trim();
    if (!t) return { value: 'Yes', color: 'green' };
    const lo = t.toLowerCase();
    if (t === 'موجود' || t === 'نعم' || lo === 'yes' || lo === 'y') {
        return { value: 'Yes', color: 'green' };
    }
    if (t === 'غير موجود' || t === 'لا' || lo === 'no' || lo === 'n') {
        return { value: 'No', color: 'red' };
    }
    return { value: t, color: _autoColorStr(t) ?? 'green' };
}

function _normStatus(v) {
    const t = String(v ?? '').trim();
    if (!t) return '';
    if (/^not\s*valid$/i.test(t) || /^notvalid$/i.test(t)) return 'Invalid';
    return t;
}

function _cellAt(vals, colors, idx) {
    if (idx == null || idx < 0) return { v: '', c: null };
    return { v: String(vals[idx] ?? '').trim(), c: colors[idx] ?? null };
}

/** True if any val in positions [from, to] is non-empty. */
function _hasData(vals, from, to) {
    for (let i = from; i <= to; i++) {
        if (String(vals[i] ?? '').trim() !== '') return true;
    }
    return false;
}

/** Resolve color: prefer Excel cell color, fallback to text-based auto-color, then default. */
function _resolveColor(cellColor, textValue, fallback = 'green') {
    if (cellColor) return cellColor;
    return autoColor(textValue) ?? fallback;
}

/** Simple alias for autoColor for internal use. */
function _autoColorStr(v) { return autoColor(v); }

/** Build a normalised MCB row object from raw values + colors. Never invents empty Valid rows. */
function _buildMcbRow(lm, lmc, lc, lcc, ls, lsc, rm, rmc, rc, rcc, rs, rsc) {
    const n = v => String(v ?? '').trim();
    const leftMcb = n(lm), leftCable = n(lc), leftStatus = _normStatus(ls);
    const rightMcb = n(rm), rightCable = n(rc), rightStatus = _normStatus(rs);
    return {
        left_mcb:           leftMcb,
        left_mcb_color:     _resolveColor(lmc, leftMcb, leftMcb ? 'green' : 'green'),
        left_cable:         leftCable,
        left_cable_color:   _resolveColor(lcc, leftCable, leftCable ? 'green' : 'green'),
        left_status:        leftStatus,
        left_status_color:  leftStatus ? _resolveColor(lsc, leftStatus) : 'green',
        right_mcb:          rightMcb,
        right_mcb_color:    _resolveColor(rmc, rightMcb, rightMcb ? 'green' : 'green'),
        right_cable:        rightCable,
        right_cable_color:  _resolveColor(rcc, rightCable, rightCable ? 'green' : 'green'),
        right_status:       rightStatus,
        right_status_color: rightStatus ? _resolveColor(rsc, rightStatus) : 'green',
    };
}

function _mcbRowHasData(row) {
    return !!(row.left_mcb || row.left_cable || row.left_status
        || row.right_mcb || row.right_cable || row.right_status);
}

export function registerAdminAlpine(Alpine) {
    Alpine.store('presets', {
        descriptions: [],
        comments: [],
        panel: null,

        hydrate(descriptions = [], comments = []) {
            this.descriptions = Array.isArray(descriptions) ? descriptions : [];
            this.comments = Array.isArray(comments) ? comments : [];
        },

        open(which) { this.panel = this.panel === which ? null : which; },
        close() { this.panel = null; },
    });

    /* Keep thin widget for optional local helpers */
    Alpine.data('presetsWidget', () => ({
        showPresets: false,
    }));

    Alpine.store('adminUi', {
        confirmOpen: false,
        confirmMessage: '',
        confirmForm: null,
        toastOpen: false,
        toastMessage: '',
        toastType: 'success',
        toastTimer: null,

        askConfirm(message, formEl) {
            this.confirmMessage = message;
            this.confirmForm = formEl;
            this.confirmOpen = true;
        },

        cancelConfirm() {
            this.confirmOpen = false;
            this.confirmForm = null;
        },

        acceptConfirm() {
            const form = this.confirmForm;
            this.confirmOpen = false;
            this.confirmForm = null;
            if (form) {
                form.submit();
            }
        },

        showToast(message, type = 'success', ms = 3500) {
            this.toastMessage = message;
            this.toastType = type;
            this.toastOpen = true;
            if (this.toastTimer) {
                clearTimeout(this.toastTimer);
            }
            this.toastTimer = setTimeout(() => {
                this.toastOpen = false;
            }, ms);
        },

        closeToast() {
            this.toastOpen = false;
            if (this.toastTimer) {
                clearTimeout(this.toastTimer);
                this.toastTimer = null;
            }
        },
    });

    const emptyMcbRow = () => ({
        left_mcb: '', left_mcb_color: 'green',
        left_cable: '', left_cable_color: 'green',
        left_status: 'Valid', left_status_color: 'green',
        right_mcb: '', right_mcb_color: 'green',
        right_cable: '', right_cable_color: 'green',
        right_status: 'Valid', right_status_color: 'green',
    });

    const emptyGroup = () => ({
        elcb: '300mA',
        have_label_value: 'Yes',
        have_label_color: 'green',
        rows: [emptyMcbRow()],
    });

    const defaultVoltages = () => ([
        { label: 'R-Y', value: '', color: 'none' },
        { label: 'R-B', value: '', color: 'none' },
        { label: 'Y-B', value: '', color: 'none' },
        { label: 'R-N', value: '', color: 'none' },
        { label: 'Y-N', value: '', color: 'none' },
        { label: 'B-N', value: '', color: 'none' },
    ]);

    Alpine.data('reportShowPage', (config = {}) => ({
        open: false,
        step: 1,
        editingId: null,
        storeUrl: config.storeUrl || '',
        formAction: config.storeUrl || '',
        editMap: config.editMap || {},
        showDescPicker: false,
        showCommentPicker: false,
        name: '',
        description: '',
        comments: [{ text: '', color: 'none' }],
        existingImages: [],
        removeImages: [],
        hasTable: false,
        tableType: 'mcb_panel',
        tableTitle: '',
        grounding_cable_value: 'نعم',
        grounding_cable_color: 'green',
        grounding_reading_value: '',
        grounding_reading_color: 'green',
        voltages: defaultVoltages(),
        mcbGroups: [emptyGroup()],

        /* Presets live on the page component so the picker always sees server data */
        presetDescriptions: Array.isArray(config.presetDescriptions) ? [...config.presetDescriptions] : [],
        presetComments: Array.isArray(config.presetComments) ? [...config.presetComments] : [],

        init() {
            const descs = Array.isArray(config.presetDescriptions) ? config.presetDescriptions : [];
            const comments = Array.isArray(config.presetComments) ? config.presetComments : [];
            this.presetDescriptions = [...descs];
            this.presetComments = [...comments];
            Alpine.store('presets').hydrate(descs, comments);

            window.addEventListener('add-comment-from-preset', (e) => {
                if (!this.open) return;
                const { text, color } = e.detail || {};
                if (text) this.comments.push({ text, color: color || 'none' });
            });
        },

        pickDescription(text) {
            this.description = text || '';
            this.showDescPicker = false;
        },

        pickComment(preset) {
            this.comments.push({
                text: preset?.text || '',
                color: preset?.color || 'none',
            });
            this.showCommentPicker = false;
        },

        /* ── Excel import state ── */
        excelImporting: false,
        excelFileName: '',

        openCreate() {
            this.resetForm();
            this.editingId = null;
            this.formAction = this.storeUrl;
            this.open = true;
            this.step = 1;
            document.body.classList.add('overflow-hidden');
        },

        openEditById(id) {
            const payload = this.editMap[id] || this.editMap[String(id)];
            if (payload) {
                this.openEdit(payload);
            }
        },

        openEdit(payload) {
            this.resetForm();
            this.editingId = payload.id;
            this.formAction = payload.update_url;
            this.name = payload.name || '';
            this.description = payload.description || '';
            this.comments = payload.comments?.length ? payload.comments : [{ text: '', color: 'none' }];
            this.existingImages = payload.images || [];
            this.hasTable = !!payload.has_table;
            this.tableType = payload.table_type || 'mcb_panel';
            this.tableTitle = payload.table_title || '';
            this.grounding_cable_value = payload.grounding_cable_value || 'نعم';
            this.grounding_cable_color = payload.grounding_cable_color || 'green';
            this.grounding_reading_value = payload.grounding_reading_value || '';
            this.grounding_reading_color = payload.grounding_reading_color || 'green';
            this.voltages = payload.voltages?.length ? payload.voltages : defaultVoltages();
            this.mcbGroups = payload.mcb_groups?.length ? payload.mcb_groups : [emptyGroup()];
            this.open = true;
            this.step = 1;
            document.body.classList.add('overflow-hidden');
        },

        closeModal() {
            this.open = false;
            document.body.classList.remove('overflow-hidden');
        },

        resetForm() {
            this.name = '';
            this.description = '';
            this.comments = [{ text: '', color: 'none' }];
            this.existingImages = [];
            this.removeImages = [];
            this.hasTable = false;
            this.tableType = 'mcb_panel';
            this.tableTitle = '';
            this.grounding_cable_value = 'نعم';
            this.grounding_cable_color = 'green';
            this.grounding_reading_value = '';
            this.grounding_reading_color = 'green';
            this.voltages = defaultVoltages();
            this.mcbGroups = [emptyGroup()];
            this.step = 1;
            this.excelFileName = '';
            this.excelImporting = false;
        },

        nextStep() {
            if (this.step === 1 && !this.name.trim()) {
                Alpine.store('adminUi').showToast('اكتب اسم القسم أولاً', 'warning');
                return;
            }
            if (this.step < 3) this.step++;
        },

        prevStep() {
            if (this.step > 1) this.step--;
        },

        /** Expose autoColor to Alpine templates (grounding inputs etc.). */
        autoColorFn(v) { return autoColor(v); },

        toggleRemoveImage(path) {
            const i = this.removeImages.indexOf(path);
            if (i >= 0) this.removeImages.splice(i, 1);
            else this.removeImages.push(path);
        },

        isRemoved(path) {
            return this.removeImages.includes(path);
        },

        addComment() {
            this.comments.push({ text: '', color: 'none' });
        },

        addVoltage() {
            this.voltages.push({ label: '', value: '', color: 'none' });
        },

        addMcbRow(gi) {
            this.mcbGroups[gi].rows.push(emptyMcbRow());
        },

        addMcbGroup() {
            this.mcbGroups.push({
                elcb: '30mA',
                have_label_value: '',
                have_label_color: 'none',
                rows: [emptyMcbRow()],
            });
        },

        /* ─────────────────────────────────────────
         * AUTO-COLOR helpers
         * ───────────────────────────────────────── */

        /**
         * Call when user finishes typing in a value field.
         * obj   = the reactive object (e.g. a row)
         * field = base field name  (e.g. 'left_status')
         * Auto-sets obj[field + '_color'] if the value matches a keyword.
         */
        applyAutoColor(obj, field) {
            const color = autoColor(obj[field]);
            if (color !== null) {
                obj[field + '_color'] = color;
            }
        },

        /** Apply auto-color to every cell in a group row */
        applyAutoColorRow(row) {
            ['left_mcb','left_cable','left_status','right_mcb','right_cable','right_status']
                .forEach(f => this.applyAutoColor(row, f));
        },

        /* ─────────────────────────────────────────
         * EXCEL IMPORT  (all 4 formats)
         * ─────────────────────────────────────────
         *
         * Format A — MCB Image-style  (Image 1):
         *   Row: ELCB | | | 300mA
         *   Row: Have Label? | | | Yes
         *   Row: MCB Rating | Cable Size | Validation Status | MCB Rating | Cable Size | Validation Status
         *   Data rows (cols 0-5) with green/red cell backgrounds
         *
         * Format B — Grounding table  (Image 2):
         *   Row: فحص التأريض  (section header)
         *   Row: هل كابل التأريض موجود؟ | نعم/لا
         *   Row: قراءة فحص التأريض | 4.01 Ohm
         *   Row: ضغط لوحة التوزيع الرئيسية
         *   Data rows: R-Y | 408.6 VOLT …
         *
         * Format C — Column-header MCB  (existing detailed):
         *   Header row: ELCB | MCB Left | Cable Left | Status Left | …
         *   Data rows
         *
         * Format D — Summary MCB  (existing summary):
         *   رقم | اسم اللوحة | الوصف | عدد ELCB | عدد MCB | صفوف صحيحة …
         * ───────────────────────────────────────── */

        importFromExcel(event) {
            const file = event.target.files[0];
            if (!file) return;
            this.excelImporting = true;
            this.excelFileName  = file.name;

            const reader = new FileReader();
            reader.onload = async (e) => {
                try {
                    const buffer = e.target.result;
                    const data = new Uint8Array(buffer);
                    const colorMaps = await _excelJsColorMaps(buffer);

                    const workbook = XLSX.read(data, {
                        type: 'array',
                        cellStyles: true,
                        cellNF: false,
                        cellDates: false,
                    });

                    const detailGroups = [];
                    let groundingMatrix = null;
                    let sawAnyMatrix = false;

                    for (const sheetName of workbook.SheetNames) {
                        const sheet = workbook.Sheets[sheetName];
                        const colorMap = colorMaps?.[sheetName] ?? null;
                        const matrix = _sheetToMatrix(sheet, colorMap);
                        if (!matrix.length || matrix.every(r => r.vals.every(v => !String(v).trim()))) {
                            continue;
                        }
                        sawAnyMatrix = true;
                        const fmt = _detectFormat(matrix);

                        // Skip summary sheet when workbook has detail panels
                        if (fmt === 'mcb_summary') continue;

                        if (fmt === 'grounding') {
                            groundingMatrix = matrix;
                            continue;
                        }
                        if (fmt === 'mcb_detailed') {
                            const groups = this._parseMcbDetailedGroups(matrix);
                            detailGroups.push(...groups);
                            continue;
                        }
                        if (fmt === 'mcb_image') {
                            detailGroups.push(...this._parseMcbImageGroups(matrix, sheetName));
                        }
                    }

                    let toastMsg = '';

                    if (groundingMatrix && !detailGroups.length) {
                        this._importGrounding(groundingMatrix);
                        toastMsg = `تم استيراد جدول التأريض من "${file.name}"`;
                    } else if (detailGroups.length) {
                        this.mcbGroups = detailGroups;
                        this.hasTable = true;
                        this.tableType = 'mcb_panel';
                        const rows = detailGroups.reduce((n, g) => n + g.rows.length, 0);
                        const floors = [...new Set(detailGroups.map(g => g.source_sheet).filter(Boolean))];
                        const floorPart = floors.length ? ` من ${floors.length} طابق` : '';
                        toastMsg = `تم استيراد ${detailGroups.length} قاطع حماية (${rows} صف)${floorPart} — مطابق لملف الإكسل`;
                        if (groundingMatrix) {
                            toastMsg += ' · ملاحظة: شيت التأريض لم يُدمج هنا — أضفه كقسم منفصل';
                        }
                        // Fill empty section name from first panel title
                        if (!String(this.name || '').trim()) {
                            const titles = [...new Set(detailGroups.map(g => g.panel_title).filter(Boolean))];
                            if (titles.length === 1) this.name = titles[0];
                            else if (titles.length > 1) this.name = titles.join(' + ');
                        }
                    } else if (!sawAnyMatrix) {
                        Alpine.store('adminUi').showToast('الملف فارغ أو لا يحتوي بيانات قابلة للقراءة', 'error');
                        return;
                    } else {
                        Alpine.store('adminUi').showToast(
                            'لم يُعثر على جداول بيانات في الشيتات. تأكد أن الملف يحتوي شيت اللوحة (Status / Cable / MCB) وليس الملخص فقط.',
                            'warning',
                        );
                        return;
                    }

                    if (this.hasTable) {
                        Alpine.store('adminUi').showToast(toastMsg, 'success');
                    }
                } catch (err) {
                    console.error('Excel import error:', err);
                    Alpine.store('adminUi').showToast('حدث خطأ أثناء قراءة الملف: ' + err.message, 'error');
                } finally {
                    this.excelImporting = false;
                    event.target.value  = '';
                }
            };
            reader.readAsArrayBuffer(file);
        },

        /* ══════════════════════════════════════════
         * Format A — MCB Image / لوحة التوزيع export
         * Supports:
         *  - Status|Cable|MCB|#|MCB|Cable|Status
         *  - MCB|Cable|Status|MCB|Cable|Status
         *  - Arabic: قاطع الحماية / النوع / قيمة القاطع / ملف التعريف
         * ══════════════════════════════════════════ */
        _parseMcbImageGroups(matrix, sheetName = '') {
            const groups = [];
            let cur = null;
            let dataMode = false;
            let colMap = null;
            let panelTitle = '';
            let breakerNo = 0;
            let elcbType = '';
            let elcbRating = '';

            const sheetLabel = String(sheetName || '').trim();

            const finalizeElcbLabel = () => {
                if (!cur) return;
                const parts = [];
                if (panelTitle) parts.push(panelTitle);
                else if (sheetLabel && !/ملخص/.test(sheetLabel)) parts.push(sheetLabel);
                if (breakerNo) parts.push(`قاطع ${breakerNo}`);
                const tech = [elcbType, elcbRating].filter(Boolean).join(' / ')
                    || String(cur.elcb || '').trim()
                    || 'ELCB';
                parts.push(tech);
                cur.elcb = parts.join(' · ');
                cur.panel_title = panelTitle || sheetLabel || '';
                cur.source_sheet = sheetLabel;
                cur.breaker_no = breakerNo || null;
            };

            const pushCur = () => {
                if (cur && cur.rows.length > 0) {
                    finalizeElcbLabel();
                    groups.push(cur);
                }
                cur = null;
                elcbType = '';
                elcbRating = '';
            };

            const ensureGroup = () => {
                if (!cur) {
                    cur = {
                        elcb: 'ELCB',
                        have_label_value: 'Yes',
                        have_label_color: 'green',
                        rows: [],
                        panel_title: panelTitle || sheetLabel,
                        source_sheet: sheetLabel,
                        breaker_no: breakerNo || null,
                    };
                }
            };

            const findElcbValue = (vals) => {
                for (let i = 1; i < vals.length; i++) {
                    const t = String(vals[i] ?? '').trim();
                    if (!t) continue;
                    const lo = t.toLowerCase();
                    if (lo.includes('have') || lo.includes('label') || lo.includes('lable')) continue;
                    if (lo === 'yes' || lo === 'no' || lo === 'نعم' || lo === 'لا') continue;
                    if (lo.includes('mcb') || lo.includes('cable') || lo.includes('validation') || lo === 'status') continue;
                    return t;
                }
                return '';
            };

            const findHaveLabel = (vals, colors) => {
                for (let i = 0; i < vals.length; i++) {
                    const lo = String(vals[i] ?? '').toLowerCase().trim();
                    if (!lo.includes('have') && !lo.includes('label') && !lo.includes('lable')) continue;
                    for (let j = i + 1; j < vals.length; j++) {
                        const t = String(vals[j] ?? '').trim();
                        if (!t) continue;
                        const norm = _normalizeHaveLabel(t);
                        return { value: norm.value, color: colors[j] ?? norm.color };
                    }
                }
                return null;
            };

            for (const { vals, colors } of matrix) {
                const rowVals = vals.map(v => String(v ?? '').trim());
                const v0 = rowVals[0] ?? '';
                const v0lo = v0.toLowerCase();
                const joined = rowVals.join(' ').toLowerCase();
                const v1 = rowVals[1] ?? '';

                // Notes / meta rows — stop data mode
                if (v0.includes('الملاحظات') || v0.includes('التوصيات') || v0 === 'م' || v0.includes('لا توجد ملاحظات')) {
                    dataMode = false;
                    continue;
                }

                // Panel title: لوحة التوزيع | الطابق الأول
                if (v0 === 'لوحة التوزيع') {
                    if (v1 && v1 !== '—') panelTitle = v1;
                    dataMode = false;
                    continue;
                }
                if (v0 === 'الوصف') {
                    dataMode = false;
                    continue;
                }

                // ── Arabic: قاطع الحماية N → new ELCB group
                if (/قاطع\s*الحماية/.test(v0)) {
                    pushCur();
                    const numMatch = v0.match(/(\d+)/) || v1.match(/(\d+)/);
                    breakerNo = numMatch ? parseInt(numMatch[1], 10) : (breakerNo + 1);
                    elcbType = '';
                    elcbRating = '';
                    cur = {
                        elcb: 'ELCB',
                        have_label_value: 'Yes',
                        have_label_color: 'green',
                        rows: [],
                        panel_title: panelTitle || sheetLabel,
                        source_sheet: sheetLabel,
                        breaker_no: breakerNo,
                    };
                    dataMode = false;
                    continue;
                }

                // ── Arabic meta rows under قاطع الحماية
                if (v0 === 'النوع' && cur) {
                    if (v1) elcbType = v1;
                    dataMode = false;
                    continue;
                }
                if ((v0.includes('قيمة القاطع') || v0lo.includes('breaker rating')) && cur) {
                    if (v1) elcbRating = v1;
                    dataMode = false;
                    continue;
                }
                if ((v0.includes('ملف التعريف') || v0lo.includes('label file')) && cur) {
                    if (v1) {
                        const norm = _normalizeHaveLabel(v1);
                        cur.have_label_value = norm.value;
                        cur.have_label_color = colors[1] ?? norm.color;
                    }
                    dataMode = false;
                    continue;
                }

                // ── English ELCB header
                if (v0lo === 'elcb') {
                    pushCur();
                    breakerNo += 1;
                    elcbType = findElcbValue(rowVals) || 'ELCB';
                    elcbRating = '';
                    const have = findHaveLabel(rowVals, colors);
                    cur = {
                        elcb: elcbType,
                        have_label_value: have?.value || 'Yes',
                        have_label_color: have?.color || 'green',
                        rows: [],
                        panel_title: panelTitle || sheetLabel,
                        source_sheet: sheetLabel,
                        breaker_no: breakerNo,
                    };
                    dataMode = false;
                    continue;
                }

                // ── Have Label on its own row
                if (joined.includes('have label') || joined.includes('have lable') || (v0lo.includes('label') && !v0lo.includes('mcb') && !v0.includes('ملف'))) {
                    ensureGroup();
                    const have = findHaveLabel(rowVals, colors);
                    if (have) {
                        cur.have_label_value = have.value;
                        cur.have_label_color = have.color;
                    } else {
                        const valIdx = rowVals.findIndex((t, i) => i > 0 && t !== '');
                        if (valIdx >= 0) {
                            const norm = _normalizeHaveLabel(rowVals[valIdx]);
                            cur.have_label_value = norm.value;
                            cur.have_label_color = colors[valIdx] ?? norm.color;
                        }
                    }
                    dataMode = false;
                    continue;
                }

                // ── Column header row → detect Status|Cable|MCB vs MCB|Cable|Status
                const looksLikeHeader = (
                    (joined.includes('mcb') && joined.includes('cable'))
                    || (joined.includes('status') && joined.includes('cable'))
                    || (joined.includes('validation') && joined.includes('cable'))
                ) && rowVals.filter(v => /status|cable|mcb|validation/i.test(v)).length >= 2;

                if (looksLikeHeader) {
                    colMap = _detectMcbColMap(rowVals) || _defaultMcbColMap();
                    dataMode = true;
                    ensureGroup();
                    continue;
                }

                // ── Data row
                if (dataMode && cur) {
                    if (rowVals.every(v => v === '')) continue;
                    if (v0lo === 'elcb' || /قاطع\s*الحماية/.test(v0)) continue;

                    // Skip the # index column values being mistaken as full rows without header
                    if (!colMap) colMap = _inferColMapFromData(rowVals);
                    // Re-infer if first mapped cells look swapped (Status landed in MCB)
                    if (_looksLikeStatus(rowVals[colMap.left.mcb]) && _looksLikeMcb(rowVals[colMap.left.status])) {
                        colMap = _inferColMapFromData(rowVals);
                    }

                    const L = colMap.left, R = colMap.right;
                    const lm = _cellAt(rowVals, colors, L.mcb);
                    const lc = _cellAt(rowVals, colors, L.cable);
                    const ls = _cellAt(rowVals, colors, L.status);
                    const rm = _cellAt(rowVals, colors, R.mcb);
                    const rc = _cellAt(rowVals, colors, R.cable);
                    const rs = _cellAt(rowVals, colors, R.status);

                    // Never store the circuit index ("1 | 2") as an MCB rating
                    const scrub = (cell) => (/^\d+\s*\|\s*\d+$/.test(cell.v) ? { v: '', c: null } : cell);
                    const row = _buildMcbRow(
                        scrub(lm).v, scrub(lm).c, lc.v, lc.c, ls.v, ls.c,
                        scrub(rm).v, scrub(rm).c, rc.v, rc.c, rs.v, rs.c,
                    );
                    if (_mcbRowHasData(row)) cur.rows.push(row);
                }
            }

            pushCur();
            return groups;
        },

        _importMcbImage(matrix) {
            const groups = this._parseMcbImageGroups(matrix);
            if (groups.length > 0) {
                this.mcbGroups = groups;
                this.hasTable = true;
                this.tableType = 'mcb_panel';
            } else {
                Alpine.store('adminUi').showToast('لم يتم العثور على صفوف بيانات في ملف MCB', 'warning');
            }
        },

        /* ══════════════════════════════════════════
         * Format B — Grounding table (Image 2)
         * ══════════════════════════════════════════ */
        _importGrounding(matrix) {
            let cableValue   = 'نعم';
            let cableColor   = 'green';
            let readingValue = '';
            let readingColor = 'green';
            const voltages   = [];
            let inVoltage    = false;

            for (const { vals, colors } of matrix) {
                // Normalise both cells
                const a  = String(vals[0] ?? '').trim();
                const b  = String(vals[1] ?? '').trim();
                const alo = a.toLowerCase();
                const blo = b.toLowerCase();

                // Section headers — skip
                if (alo.includes('فحص التأريض') || alo.includes('grounding check') ||
                    alo.includes('جدول') || (a !== '' && b === '' && !inVoltage && vals.slice(2).every(v => String(v).trim() === ''))) {
                    if (alo.includes('ضغط') || alo.includes('voltage') || alo.includes('main panel') || alo.includes('لوحة التوزيع')) {
                        inVoltage = true;
                    }
                    continue;
                }
                if (blo.includes('ضغط') || blo.includes('voltage') || blo.includes('main panel') || blo.includes('لوحة التوزيع')) {
                    inVoltage = true;
                    continue;
                }

                // Grounding cable row — detect by keyword in either col
                const cableKw = v => v.includes('كابل') || v.includes('grounding cable') || v.includes('have grounding') || v.includes('cable');
                const readKw  = v => v.includes('قراءة') || v.includes('reading') || v.includes('ohm');

                if (cableKw(alo)) {
                    // Prefer explicit نعم/لا token when cell says "نعم / لا"
                    let raw = b || a;
                    if (/نعم/.test(raw) && /لا/.test(raw)) {
                        raw = (colors[1] === 'red' || colors[0] === 'red') ? 'لا' : 'نعم';
                    }
                    cableValue = raw;
                    cableColor = colors[1] ?? colors[0] ?? _autoColorStr(cableValue) ?? 'green';
                    continue;
                }
                if (cableKw(blo)) {
                    let raw = a;
                    if (/نعم/.test(raw) && /لا/.test(raw)) {
                        raw = colors[0] === 'red' ? 'لا' : 'نعم';
                    }
                    cableValue = raw;
                    cableColor = colors[0] ?? _autoColorStr(a) ?? 'green';
                    continue;
                }
                if (readKw(alo)) {
                    readingValue = b || a;
                    readingColor = colors[1] ?? colors[0] ?? _autoColorStr(readingValue) ?? 'green';
                    continue;
                }
                if (readKw(blo)) {
                    readingValue = a;
                    readingColor = colors[0] ?? _autoColorStr(a) ?? 'green';
                    continue;
                }

                // Voltage rows — label is like R-Y, R-B, Y-B, R-N, Y-N, B-N
                const voltLabelRe = /^[RYBNE]-[RYBNE]/i;
                if (voltLabelRe.test(a) && b) {
                    voltages.push({ label: a, value: b, color: colors[1] ?? colors[0] ?? 'none' });
                    continue;
                }
                if (voltLabelRe.test(b) && a) {
                    voltages.push({ label: b, value: a, color: colors[0] ?? colors[1] ?? 'none' });
                    continue;
                }

                // If in voltage section and both cells have data
                if (inVoltage && a && b) {
                    // Guess which is label vs value
                    const aIsLabel = voltLabelRe.test(a) || /^[A-Z]+-[A-Z]+$/i.test(a);
                    voltages.push({
                        label: aIsLabel ? a : b,
                        value: aIsLabel ? b : a,
                        color: aIsLabel ? (colors[1] ?? 'none') : (colors[0] ?? 'none'),
                    });
                }
            }

            // Apply to state
            this.grounding_cable_value   = cableValue;
            this.grounding_cable_color   = cableColor;
            this.grounding_reading_value = readingValue;
            this.grounding_reading_color = readingColor;
            if (voltages.length > 0) this.voltages = voltages;
            this.hasTable  = true;
            this.tableType = 'grounding_main';
        },

        /* ══════════════════════════════════════════
         * Format C — Column-header detailed MCB
         * ══════════════════════════════════════════ */
        _parseMcbDetailedGroups(matrix) {
            const hkw = {
                elcb:         ['elcb'],
                have_label:   ['have label', 'have lable'],
                mcb_left:     ['mcb left', 'left mcb', 'يسار mcb'],
                cable_left:   ['cable left', 'left cable', 'كابل يسار'],
                status_left:  ['status left', 'left status', 'حالة يسار'],
                mcb_right:    ['mcb right', 'right mcb', 'يمين mcb'],
                cable_right:  ['cable right', 'right cable', 'كابل يمين'],
                status_right: ['status right', 'right status', 'حالة يمين'],
            };

            let headerRow = -1;
            let colMap = {};
            for (let i = 0; i < Math.min(matrix.length, 12); i++) {
                const { vals } = matrix[i];
                const matched = {};
                vals.forEach((v, ci) => {
                    const n = String(v).toLowerCase().trim().replace(/\s+/g, ' ');
                    for (const [k, kws] of Object.entries(hkw)) {
                        if (kws.some(kw => n.includes(kw))) matched[k] = ci;
                    }
                });
                if (Object.keys(matched).length >= 3) { headerRow = i; colMap = matched; break; }
            }
            if (headerRow < 0) return [];

            const groups = {};
            const order = [];
            const g = (row, k) => colMap[k] !== undefined ? String(row[colMap[k]] ?? '').trim() : '';
            const gc = (colors, k) => colMap[k] !== undefined ? (colors[colMap[k]] ?? null) : null;

            for (const { vals, colors } of matrix.slice(headerRow + 1)) {
                if (vals.every(v => String(v).trim() === '')) continue;
                const elcb = g(vals, 'elcb') || '300mA';
                if (!groups[elcb]) {
                    groups[elcb] = {
                        elcb,
                        have_label_value: g(vals, 'have_label') || 'Yes',
                        have_label_color: 'green',
                        rows: [],
                    };
                    order.push(elcb);
                }
                const row = _buildMcbRow(
                    g(vals, 'mcb_left'), gc(colors, 'mcb_left'),
                    g(vals, 'cable_left'), gc(colors, 'cable_left'),
                    g(vals, 'status_left'), gc(colors, 'status_left'),
                    g(vals, 'mcb_right'), gc(colors, 'mcb_right'),
                    g(vals, 'cable_right'), gc(colors, 'cable_right'),
                    g(vals, 'status_right'), gc(colors, 'status_right'),
                );
                if (_mcbRowHasData(row)) groups[elcb].rows.push(row);
            }
            return order.map(k => groups[k]).filter(g => g.rows.length > 0);
        },

        _importMcbDetailed(matrix) {
            const groups = this._parseMcbDetailedGroups(matrix);
            if (groups.length > 0) {
                this.mcbGroups = groups;
                this.hasTable = true;
                this.tableType = 'mcb_panel';
            }
        },
    }));
}
