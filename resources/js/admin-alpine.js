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
        'invalid', 'no', 'لا', 'fail', 'failed', 'error', 'bad', 'wrong',
        'خطأ', 'غير سليم', 'غير موجود', 'معطل', 'تالف', 'لا يعمل',
        'not ok', 'not good', 'fault', 'broken', 'missing',
    ];

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
 */
function _sheetToMatrix(sheet) {
    if (!sheet || !sheet['!ref']) return [];
    const range   = XLSX.utils.decode_range(sheet['!ref']);
    const merges  = sheet['!merges'] || [];

    // Build merged-cell lookup: cellAddr → value of top-left
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
            colors.push(cell ? _rgbToLabel(_getCellBgRgb(cell)) : null);
        }
        rows.push({ vals, colors });
    }
    return rows;
}

/** Detect which of the 4 formats the matrix represents. */
function _detectFormat(matrix) {
    for (const { vals } of matrix) {
        const flat = vals.map(v => String(v).toLowerCase().trim());

        // Grounding table keywords
        if (flat.some(v => v.includes('فحص التأريض') || v.includes('grounding check') || v.includes('grounding'))) return 'grounding';
        if (flat.some(v => v.includes('تأريض') || v.includes('ohm'))) return 'grounding';
        if (flat.some(v => /^[rybRYB]-[rybRYBnN]$/.test(v))) return 'grounding';

        // MCB Image-1 style: "ELCB" alone in col-0, value in col-3
        if (flat[0] === 'elcb' && flat.slice(1, 3).every(v => v === '') && flat[3] && flat[3] !== 'elcb') return 'mcb_image';

        // Column-header detailed MCB
        if (flat.some(v => v.includes('mcb left') || v.includes('status left') || v.includes('cable left'))) return 'mcb_detailed';
        if (flat.some(v => v.includes('mcb rating') || v.includes('validation status') || v.includes('cable size'))) return 'mcb_image';

        // Summary format keywords
        if (flat.some(v => v.includes('صفوف صحيحة') || v.includes('عدد mcb') || v.includes('mcb count'))) return 'mcb_summary';
    }
    return 'mcb_summary'; // safest fallback
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

/** Build a normalised MCB row object from raw values + colors. */
function _buildMcbRow(lm, lmc, lc, lcc, ls, lsc, rm, rmc, rc, rcc, rs, rsc) {
    const n = v => String(v ?? '').trim();
    return {
        left_mcb:           n(lm),
        left_mcb_color:     _resolveColor(lmc, n(lm)),
        left_cable:         n(lc),
        left_cable_color:   _resolveColor(lcc, n(lc)),
        left_status:        n(ls) || 'Valid',
        left_status_color:  _resolveColor(lsc, n(ls) || 'Valid'),
        right_mcb:          n(rm),
        right_mcb_color:    _resolveColor(rmc, n(rm)),
        right_cable:        n(rc),
        right_cable_color:  _resolveColor(rcc, n(rc)),
        right_status:       n(rs) || 'Valid',
        right_status_color: _resolveColor(rsc, n(rs) || 'Valid'),
    };
}

export function registerAdminAlpine(Alpine) {
    /* ─────────────────────────────────────────────────────────────────
     * presetsWidget — reusable localStorage-backed presets component.
     * Usage: x-data="presetsWidget('descriptions')"
     *        x-data="presetsWidget('comments')"
     * ───────────────────────────────────────────────────────────────── */
    Alpine.data('presetsWidget', (namespace) => ({
        showPresets: false,
        presets: [],
        _key: `gis_presets_${namespace}`,

        init() {
            try {
                this.presets = JSON.parse(localStorage.getItem(this._key) || '[]');
            } catch {
                this.presets = [];
            }
        },

        _persist() {
            localStorage.setItem(this._key, JSON.stringify(this.presets));
        },

        /** value: string (description) OR {text,color} object (comment) */
        savePreset(value) {
            if (!value) return;
            const str = typeof value === 'string' ? value.trim() : null;
            const obj = (typeof value === 'object' && value !== null) ? value : null;

            if (str !== null) {
                if (str === '' || this.presets.includes(str)) return;
                this.presets.unshift(str);
            } else if (obj !== null) {
                const text = (obj.text || '').trim();
                if (!text) return;
                const exists = this.presets.some(p => p.text === text && p.color === obj.color);
                if (exists) return;
                this.presets.unshift({ text, color: obj.color || 'none' });
            }

            if (this.presets.length > 30) this.presets = this.presets.slice(0, 30);
            this._persist();
            this.showPresets = true;
            Alpine.store('adminUi').showToast('تم الحفظ في المحفوظات ✓', 'success', 1800);
        },

        removePreset(i) {
            this.presets.splice(i, 1);
            this._persist();
        },

        /** For descriptions: dispatch event that textarea listens to */
        applyPreset(value, dispatch) {
            dispatch('preset-selected', { type: 'descriptions', value });
            this.showPresets = false;
        },

        /** For comments: bubble up via window event */
        addCommentFromPreset(preset) {
            window.dispatchEvent(new CustomEvent('add-comment-from-preset', {
                detail: typeof preset === 'string'
                    ? { text: preset, color: 'none' }
                    : preset,
            }));
            this.showPresets = false;
        },
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

        /* ── Excel import state ── */
        excelImporting: false,
        excelFileName: '',

        openCreate() {            this.resetForm();
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

        /** Listen for preset comment insertion from presetsWidget */
        init() {
            window.addEventListener('add-comment-from-preset', (e) => {
                if (!this.open) return;
                const { text, color } = e.detail || {};
                if (text) this.comments.push({ text, color: color || 'none' });
            });
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
            reader.onload = (e) => {
                try {
                    const data = new Uint8Array(e.target.result);
                    const workbook = XLSX.read(data, {
                        type: 'array',
                        cellStyles: true,   // read fill colours
                        cellNF: false,
                        cellDates: false,
                    });

                    const sheetName = workbook.SheetNames[0];
                    const sheet     = workbook.Sheets[sheetName];
                    const matrix    = _sheetToMatrix(sheet);   // [{vals[], colors[]}, …]

                    const fmt = _detectFormat(matrix);
                    let toastMsg = '';

                    if (fmt === 'grounding') {
                        this._importGrounding(matrix);
                        toastMsg = `تم استيراد جدول التأريض من "${file.name}"`;
                    } else if (fmt === 'mcb_image') {
                        this._importMcbImage(matrix);
                        toastMsg = `تم استيراد ${this.mcbGroups.length} مجموعة ELCB من "${file.name}"`;
                    } else if (fmt === 'mcb_detailed') {
                        this._importMcbDetailed(matrix);
                        toastMsg = `تم استيراد ${this.mcbGroups.length} مجموعة من "${file.name}"`;
                    } else {
                        this._importMcbSummary(matrix);
                        toastMsg = `تم استيراد ${this.mcbGroups.length} لوحة من "${file.name}"`;
                    }

                    Alpine.store('adminUi').showToast(toastMsg, 'success');
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
         * Format A — MCB Image style (Image 1)
         * ══════════════════════════════════════════ */
        _importMcbImage(matrix) {
            const groups = [];
            let cur = null;
            let dataMode = false;

            for (const { vals, colors } of matrix) {
                const v0 = String(vals[0] ?? '').trim();
                const v0lo = v0.toLowerCase();

                // ── ELCB header row: "ELCB" in col-0, value in col-3 or col-4
                if (v0lo === 'elcb' && !_hasData(vals, 1, 2)) {
                    if (cur) groups.push(cur);
                    const elcbVal = String(vals[3] ?? vals[4] ?? '').trim();
                    cur = { elcb: elcbVal, have_label_value: 'Yes', have_label_color: 'green', rows: [] };
                    dataMode = false;
                    continue;
                }

                // ── Have Label row
                if (v0lo.includes('have label') || v0lo.includes('have lable') || v0lo.includes('label')) {
                    if (cur) {
                        const valIdx = [3, 4, 1].find(i => String(vals[i] ?? '').trim() !== '') ?? 3;
                        cur.have_label_value = String(vals[valIdx] ?? '').trim() || 'Yes';
                        cur.have_label_color = colors[valIdx] ?? _autoColorStr(cur.have_label_value) ?? 'green';
                    }
                    dataMode = false;
                    continue;
                }

                // ── Column header row: MCB Rating | Cable Size | Validation Status …
                if (v0lo.includes('mcb rating') || v0lo.includes('mcb') && v0lo.includes('rating')) {
                    dataMode = true;
                    continue;
                }

                // ── Data row
                if (dataMode && cur) {
                    const rowVals   = vals.map(v => String(v ?? '').trim());
                    const rowColors = colors;

                    // Skip truly empty rows
                    if (rowVals.every(v => v === '')) { dataMode = false; continue; }

                    // Left: cols 0,1,2  |  Right: cols 3,4,5
                    const row = _buildMcbRow(
                        rowVals[0], rowColors[0],   // left MCB
                        rowVals[1], rowColors[1],   // left cable
                        rowVals[2], rowColors[2],   // left status
                        rowVals[3], rowColors[3],   // right MCB
                        rowVals[4], rowColors[4],   // right cable
                        rowVals[5], rowColors[5],   // right status
                    );
                    cur.rows.push(row);
                }
            }

            if (cur && cur.rows.length > 0) groups.push(cur);

            if (groups.length > 0) {
                this.mcbGroups = groups;
                this.hasTable  = true;
                this.tableType = 'mcb_panel';
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
                    // value is in col-B; colour from col-B or col-A
                    cableValue = b || a;
                    cableColor = colors[1] ?? colors[0] ?? _autoColorStr(cableValue) ?? 'green';
                    continue;
                }
                if (cableKw(blo)) {
                    cableValue = a;
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
        _importMcbDetailed(matrix) {
            // Find header row and column map
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
                    const n = String(v).toLowerCase().trim().replace(/\s+/g,' ');
                    for (const [k, kws] of Object.entries(hkw)) {
                        if (kws.some(kw => n.includes(kw))) matched[k] = ci;
                    }
                });
                if (Object.keys(matched).length >= 3) { headerRow = i; colMap = matched; break; }
            }
            if (headerRow < 0) { this._importMcbSummary(matrix); return; }

            const groups = {};
            const order  = [];
            const g = (row, k) => colMap[k] !== undefined ? String(row[colMap[k]] ?? '').trim() : '';
            const gc = (colors, k) => colMap[k] !== undefined ? (colors[colMap[k]] ?? null) : null;

            for (const { vals, colors } of matrix.slice(headerRow + 1)) {
                if (vals.every(v => String(v).trim() === '')) continue;
                const elcb = g(vals, 'elcb') || '300mA';
                if (!groups[elcb]) { groups[elcb] = { elcb, have_label_value: g(vals,'have_label') || 'Yes', have_label_color: 'green', rows: [] }; order.push(elcb); }
                groups[elcb].rows.push(_buildMcbRow(
                    g(vals,'mcb_left'),   gc(colors,'mcb_left'),
                    g(vals,'cable_left'), gc(colors,'cable_left'),
                    g(vals,'status_left'),gc(colors,'status_left'),
                    g(vals,'mcb_right'),  gc(colors,'mcb_right'),
                    g(vals,'cable_right'),gc(colors,'cable_right'),
                    g(vals,'status_right'),gc(colors,'status_right'),
                ));
            }
            if (order.length > 0) { this.mcbGroups = order.map(k => groups[k]); this.hasTable = true; this.tableType = 'mcb_panel'; }
        },

        /* ══════════════════════════════════════════
         * Format D — Summary MCB
         * ══════════════════════════════════════════ */
        _importMcbSummary(matrix) {
            const newGroups = [];
            for (const { vals } of matrix) {
                const v = vals.map(c => String(c ?? '').trim());
                if (v.every(x => x === '')) continue;
                const nums = v.map(x => { const n = parseFloat(x); return isNaN(n) ? null : n; });
                const panelName = v.find(x => x && isNaN(parseFloat(x))) || '';
                const elcbCount = nums.find((n,i) => n !== null && i >= 1) ?? 2;
                const mcbCount  = nums.find((n,i) => n !== null && i >= 2 && n !== elcbCount) ?? 24;
                const validRows = nums.find((n,i) => n !== null && i >= 3) ?? mcbCount;
                const totalRows = Math.ceil(mcbCount / 2);
                const circuitRows = [];
                for (let r = 0; r < totalRows; r++) {
                    const lv = (r*2+1) <= validRows, rv = (r*2+2) <= validRows;
                    circuitRows.push(_buildMcbRow(
                        '','green','','green', lv?'Valid':'Invalid', lv?'green':'red',
                        '','green','','green', rv?'Valid':'Invalid', rv?'green':'red',
                    ));
                }
                newGroups.push({ elcb: `${elcbCount}`, have_label_value: panelName||'Yes', have_label_color:'green', rows: circuitRows.length?circuitRows:[emptyMcbRow()] });
            }
            if (newGroups.length > 0) { this.mcbGroups = newGroups; this.hasTable = true; this.tableType = 'mcb_panel'; }
        },
    }));
}
