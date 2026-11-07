/**
 * ApexSpend — Shared toast helper
 * Loaded by every authenticated page (dashboard, ledger, budgets).
 * Use window.ApexToast.show(...) from page scripts. The `message` argument is
 * always inserted via textContent — never innerHTML — so user-controlled
 * strings cannot inject markup.
 */

(function () {
    function showToast(message, type) {
        const container = document.getElementById('toastContainer');
        if (!container) return;
        const toast = document.createElement('div');
        const t = (type || 'info').toLowerCase();
        toast.className = 'toast ' + t;
        let icon = 'fa-circle-info';
        if (t === 'success') icon = 'fa-circle-check';
        if (t === 'error')   icon = 'fa-circle-exclamation';
        const iconEl = document.createElement('i');
        iconEl.className = 'fa-solid ' + icon;
        iconEl.setAttribute('aria-hidden', 'true');
        const text = document.createElement('span');
        text.textContent = String(message);
        toast.append(iconEl, text);
        container.appendChild(toast);
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(6px)';
            setTimeout(() => toast.remove(), 250);
        }, 3200);
    }

    // Public API + back-compat global for code that calls showToast(...) directly.
    window.ApexToast = { show: showToast };
    window.showToast = showToast;
})();