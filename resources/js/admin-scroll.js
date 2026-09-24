/**
 * Preserve scroll position on admin form submits.
 */

function showAdminToast(message) {
    const el = document.getElementById('ajax-status-toast');
    if (!el) {
        return;
    }
    el.textContent = message;
    el.classList.remove('hidden', 'opacity-0');
    clearTimeout(showAdminToast._t);
    showAdminToast._t = setTimeout(() => {
        el.classList.add('opacity-0');
        setTimeout(() => el.classList.add('hidden'), 300);
    }, 2600);
}

function preserveScrollBeforeSubmit() {
    document.addEventListener(
        'submit',
        (e) => {
            const form = e.target;
            if (!(form instanceof HTMLFormElement)) {
                return;
            }
            if (!form.closest('[data-admin-page]')) {
                return;
            }
            try {
                sessionStorage.setItem('adminScrollY', String(window.scrollY));
            } catch (_) {}
        },
        true
    );
}

function restoreScrollAfterLoad() {
    try {
        const y = sessionStorage.getItem('adminScrollY');
        if (y !== null) {
            sessionStorage.removeItem('adminScrollY');
            window.scrollTo(0, Number(y) || 0);
        }
    } catch (_) {}
}

export function initAdminScroll() {
    if (!document.body?.hasAttribute('data-admin-page')) {
        return;
    }
    preserveScrollBeforeSubmit();
    restoreScrollAfterLoad();
}
