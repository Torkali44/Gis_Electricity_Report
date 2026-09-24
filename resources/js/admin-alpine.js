/**
 * Admin Alpine components — must load before Alpine.start()
 */
export function registerAdminAlpine(Alpine) {
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
    }));
}
