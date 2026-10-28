/**
 * Section image annotator — arrows baked into a composite JPEG for the report.
 */
function drawCursorArrow(ctx, tipX, tipY, canvasW, canvasH, angleDeg, sizeFactor) {
    const ref = Math.min(canvasW, canvasH);
    const size = Math.max(30, ref * 0.08 * sizeFactor);
    const angle = (angleDeg * Math.PI) / 180;

    ctx.save();
    ctx.translate(tipX, tipY);
    ctx.rotate(angle);
    ctx.shadowColor = 'rgba(0, 0, 0, 0.45)';
    ctx.shadowBlur = 8;
    ctx.fillStyle = '#ef4444';
    ctx.strokeStyle = '#ffffff';
    ctx.lineWidth = Math.max(2, ref * 0.005);
    ctx.lineJoin = 'round';
    ctx.beginPath();
    ctx.moveTo(0, 0);
    ctx.lineTo(0, size);
    ctx.lineTo(size * 0.3, size * 0.75);
    ctx.lineTo(size * 0.6, size * 1.2);
    ctx.lineTo(size * 0.75, size * 1.1);
    ctx.lineTo(size * 0.45, size * 0.65);
    ctx.lineTo(size * 0.9, size * 0.65);
    ctx.closePath();
    ctx.fill();
    ctx.stroke();
    ctx.restore();
}

function dataUrlToBlob(dataUrl) {
    const parts = dataUrl.split(',');
    const mime = parts[0].match(/:(.*?);/)?.[1] ?? 'image/jpeg';
    const binary = atob(parts[1] ?? '');
    const u8 = new Uint8Array(binary.length);
    for (let i = 0; i < binary.length; i++) u8[i] = binary.charCodeAt(i);
    return new Blob([u8], { type: mime });
}

export function registerSectionAnnotate(Alpine) {
    Alpine.data('sectionAnnotate', () => ({
        open: false,
        imageUrl: '',
        imagePath: '',
        actionUrl: '',
        arrows: [],
        selected: -1,
        arrowSize: 1,
        arrowAngle: -45,
        dragging: false,
        viewW: 0,
        viewH: 0,
        saving: false,

        openEditor({ url, path, action }) {
            this.imageUrl = url;
            this.imagePath = path;
            this.actionUrl = action;
            this.arrows = [];
            this.selected = -1;
            this.open = true;
            this.$nextTick(() => this.setupViewport());
        },

        close() {
            this.open = false;
            this.imageUrl = '';
            this.imagePath = '';
        },

        setupViewport() {
            const img = this.$refs.img;
            const viewport = this.$refs.viewport;
            if (!img || !viewport || !img.naturalWidth) return;
            const maxW = Math.min(viewport.parentElement?.clientWidth || 640, 720);
            const maxH = Math.min(window.innerHeight * 0.55, 480);
            const ratio = img.naturalWidth / img.naturalHeight;
            let vw = maxW;
            let vh = vw / ratio;
            if (vh > maxH) {
                vh = maxH;
                vw = vh * ratio;
            }
            this.viewW = Math.round(vw);
            this.viewH = Math.round(vh);
            viewport.style.width = `${this.viewW}px`;
            viewport.style.height = `${this.viewH}px`;
            img.style.width = `${this.viewW}px`;
            img.style.height = `${this.viewH}px`;
            this.renderSvg();
        },

        addArrow() {
            this.arrows.push({ x: 0.5, y: 0.45, size: this.arrowSize, angle: this.arrowAngle });
            this.selected = this.arrows.length - 1;
            this.renderSvg();
        },

        removeSelected() {
            if (this.selected < 0) return;
            this.arrows.splice(this.selected, 1);
            this.selected = this.arrows.length ? Math.min(this.selected, this.arrows.length - 1) : -1;
            this.renderSvg();
        },

        clearArrows() {
            this.arrows = [];
            this.selected = -1;
            this.renderSvg();
        },

        onViewportClick(e) {
            if (e.target.closest('[data-drag-handle]')) return;
            const rect = this.$refs.viewport.getBoundingClientRect();
            const nx = (e.clientX - rect.left) / this.viewW;
            const ny = (e.clientY - rect.top) / this.viewH;
            if (this.selected >= 0 && this.arrows[this.selected]) {
                this.arrows[this.selected].x = Math.min(1, Math.max(0, nx));
                this.arrows[this.selected].y = Math.min(1, Math.max(0, ny));
                this.renderSvg();
            }
        },

        startDrag(e, index) {
            e.preventDefault();
            e.stopPropagation();
            this.selected = index;
            this.dragging = true;
            const move = (ev) => {
                if (!this.dragging || !this.arrows[this.selected]) return;
                const rect = this.$refs.viewport.getBoundingClientRect();
                this.arrows[this.selected].x = Math.min(1, Math.max(0, (ev.clientX - rect.left) / this.viewW));
                this.arrows[this.selected].y = Math.min(1, Math.max(0, (ev.clientY - rect.top) / this.viewH));
                this.renderSvg();
            };
            const up = () => {
                this.dragging = false;
                window.removeEventListener('mousemove', move);
                window.removeEventListener('mouseup', up);
            };
            window.addEventListener('mousemove', move);
            window.addEventListener('mouseup', up);
        },

        syncSelectedStyle() {
            if (this.selected >= 0 && this.arrows[this.selected]) {
                this.arrows[this.selected].size = Number(this.arrowSize);
                this.arrows[this.selected].angle = Number(this.arrowAngle);
                this.renderSvg();
            }
        },

        renderSvg() {
            const svg = this.$refs.svg;
            if (!svg) return;
            svg.querySelectorAll('[data-dyn]').forEach((n) => n.remove());
            this.arrows.forEach((a, i) => {
                const tx = a.x * 1000;
                const ty = a.y * 1000;
                const size = 120 * (a.size || 1);
                const d = `M ${tx},${ty} L ${tx},${ty + size} L ${tx + size * 0.3},${ty + size * 0.75} L ${tx + size * 0.6},${ty + size * 1.2} L ${tx + size * 0.75},${ty + size * 1.1} L ${tx + size * 0.45},${ty + size * 0.65} L ${tx + size * 0.9},${ty + size * 0.65} Z`;
                const p = document.createElementNS('http://www.w3.org/2000/svg', 'path');
                p.setAttribute('data-dyn', '1');
                p.setAttribute('d', d);
                p.setAttribute('fill', '#ef4444');
                p.setAttribute('stroke', '#fff');
                p.setAttribute('stroke-width', i === this.selected ? '5' : '3');
                p.setAttribute('transform', `rotate(${a.angle || -45} ${tx} ${ty})`);
                p.style.cursor = 'pointer';
                p.addEventListener('click', (ev) => {
                    ev.stopPropagation();
                    this.selected = i;
                    this.arrowSize = a.size || 1;
                    this.arrowAngle = a.angle || -45;
                    this.renderSvg();
                });
                svg.appendChild(p);
            });
        },

        async save() {
            const img = this.$refs.img;
            if (!img?.naturalWidth || !this.actionUrl) return;
            this.saving = true;
            try {
                const outW = img.naturalWidth;
                const outH = img.naturalHeight;
                const canvas = document.createElement('canvas');
                canvas.width = outW;
                canvas.height = outH;
                const ctx = canvas.getContext('2d');
                ctx.drawImage(img, 0, 0, outW, outH);
                this.arrows.forEach((a) => {
                    drawCursorArrow(ctx, a.x * outW, a.y * outH, outW, outH, a.angle || -45, a.size || 1);
                });
                const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', 0.92));
                const fd = new FormData();
                fd.append('path', this.imagePath);
                fd.append('composite', new File([blob], 'annotated.jpg', { type: 'image/jpeg' }));
                fd.append('_token', document.querySelector('meta[name="csrf-token"]')?.content
                    || document.querySelector('input[name="_token"]')?.value
                    || '');

                const res = await fetch(this.actionUrl, {
                    method: 'POST',
                    body: fd,
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                });

                if (res.ok) {
                    const json = await res.json().catch(() => ({}));
                    Alpine.store('adminUi').showToast('تم حفظ الصورة مع الأسهم', 'success');
                    // Update preview in open editor list if same path
                    window.dispatchEvent(new CustomEvent('section-image-updated', {
                        detail: { path: this.imagePath, url: json.url || this.imageUrl + '?t=' + Date.now() },
                    }));
                    this.close();
                    setTimeout(() => window.location.reload(), 600);
                    return;
                }
                Alpine.store('adminUi').showToast('تعذر حفظ الصورة', 'error');
            } catch (e) {
                console.error(e);
                Alpine.store('adminUi').showToast('خطأ أثناء حفظ الصورة', 'error');
            } finally {
                this.saving = false;
            }
        },
    }));
}
