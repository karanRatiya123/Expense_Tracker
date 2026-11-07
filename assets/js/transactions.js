// ============================================================
//  ApexSpend - transactions.js
//  Ledger search, filtering, and edit/delete actions.
// ============================================================

(function () {
    'use strict';

    const searchInput = document.getElementById('txSearch');
    const filterBtns  = document.querySelectorAll('.filters button[data-filter]');
    const rows        = Array.from(document.querySelectorAll('#ledgerBody .led-grid'));
    const visibleEl   = document.getElementById('visibleCount');
    const emptyMsg    = document.getElementById('emptyMsg');
    const endMsg      = document.getElementById('endMsg');
    const editModal   = document.getElementById('editTxModal');
    const editForm    = document.getElementById('editTxForm');
    const editClose   = document.getElementById('editTxClose');
    const addModal    = document.getElementById('addTxModal');
    const addForm     = document.getElementById('addTxForm');
    const addOpenBtn  = document.getElementById('addTxOpen');
    const addClose    = document.getElementById('addTxClose');

    let activeFilter = 'all';
    let activeQuery  = '';
    let editType     = 'expense';
    let addType      = 'expense';

    // Per-request CSRF token. Server emits window.APEX_TOKEN in transactions.php.
    const TOKEN = (typeof window.APEX_TOKEN === 'string') ? window.APEX_TOKEN : '';

    function toast(message, type) {
        if (typeof showToast === 'function') showToast(message, type);
    }

    function applyFilters() {
        let visible = 0;

        rows.forEach((row) => {
            const matchesCategory =
                activeFilter === 'all' ||
                row.getAttribute('data-category') === activeFilter ||
                row.getAttribute('data-type') === activeFilter;

            const hay = row.getAttribute('data-hay') || '';
            const matchesQuery = activeQuery === '' || hay.indexOf(activeQuery) !== -1;

            const show = matchesCategory && matchesQuery;
            row.style.display = show ? '' : 'none';
            if (show) visible++;
        });

        if (visibleEl) visibleEl.textContent = String(visible);
        if (emptyMsg) emptyMsg.hidden = visible !== 0;
        if (endMsg) endMsg.hidden = visible === 0;
    }

    function setEditType(type) {
        editType = type === 'income' ? 'income' : 'expense';
        const expenseBtn = document.getElementById('editTypeExpenseBtn');
        const incomeBtn = document.getElementById('editTypeIncomeBtn');
        if (!expenseBtn || !incomeBtn) return;

        expenseBtn.className = editType === 'expense' ? 'is-on expense' : 'expense';
        incomeBtn.className = editType === 'income' ? 'is-on income' : 'income';
    }

    function openEditModal(row) {
        if (!editModal) return;

        document.getElementById('editTxId').value = row.dataset.id || '';
        document.getElementById('editTxTitle').value = row.dataset.title || '';
        document.getElementById('editTxNote').value = row.dataset.note || '';
        document.getElementById('editTxAmount').value = row.dataset.amount || '';
        document.getElementById('editTxCategory').value = row.dataset.category || '';
        document.getElementById('editTxMethod').value = row.dataset.method || '';
        document.getElementById('editTxDate').value = row.dataset.date || '';
        setEditType(row.dataset.type || 'expense');

        editModal.classList.add('is-on');
        editModal.setAttribute('aria-hidden', 'false');
        setTimeout(() => document.getElementById('editTxTitle').focus(), 60);
    }

    function closeEditModal() {
        if (!editModal) return;
        editModal.classList.remove('is-on');
        editModal.setAttribute('aria-hidden', 'true');
    }

    async function deleteTransaction(row) {
        const id = row.dataset.id || '';
        const title = row.dataset.title || 'this entry';
        if (!id || !confirm(`Delete "${title}" from the ledger?`)) return;

        const formData = new FormData();
        formData.append('id', id);
        if (TOKEN) formData.append('_token', TOKEN);

        try {
            const response = await fetch('../api/api_delete_transaction.php', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();

            if (result.success) {
                toast('Entry deleted. Reloading...', 'success');
                setTimeout(() => location.reload(), 500);
            } else {
                toast(`Delete failed: ${result.error}`, 'error');
            }
        } catch (err) {
            toast('Error deleting transaction.', 'error');
        }
    }

    async function saveTransaction(event) {
        event.preventDefault();

        const title = document.getElementById('editTxTitle').value.trim();
        const amount = parseFloat(document.getElementById('editTxAmount').value);

        if (!title || isNaN(amount) || amount <= 0) {
            toast('Enter a valid title and a positive amount.', 'error');
            return;
        }

        const formData = new FormData();
        formData.append('id', document.getElementById('editTxId').value);
        formData.append('title', title);
        formData.append('note', document.getElementById('editTxNote').value.trim());
        formData.append('amount', amount);
        formData.append('category', document.getElementById('editTxCategory').value);
        formData.append('method', document.getElementById('editTxMethod').value.trim());
        formData.append('date', document.getElementById('editTxDate').value);
        formData.append('type', editType);
        if (TOKEN) formData.append('_token', TOKEN);

        try {
            const response = await fetch('../api/api_update_transaction.php', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();

            if (result.success) {
                toast('Entry updated. Reloading...', 'success');
                setTimeout(() => location.reload(), 500);
            } else {
                toast(`Update failed: ${result.error}`, 'error');
            }
        } catch (err) {
            toast('Error updating transaction.', 'error');
        }
    }

    // ---- Add modal ----

    function setAddType(type) {
        addType = type === 'income' ? 'income' : 'expense';
        const exp = document.getElementById('addTypeExpenseBtn');
        const inc = document.getElementById('addTypeIncomeBtn');
        if (!exp || !inc) return;
        exp.className = addType === 'expense' ? 'is-on expense' : 'expense';
        inc.className = addType === 'income' ? 'is-on income' : 'income';
    }

    function openAddModal() {
        if (!addModal) return;
        // Reset the form; default to now in local datetime format.
        if (addForm) addForm.reset();
        setAddType('expense');
        const dt = document.getElementById('addTxDate');
        if (dt && !dt.value) {
            const now = new Date();
            const pad = (n) => String(n).padStart(2, '0');
            dt.value = `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}T${pad(now.getHours())}:${pad(now.getMinutes())}`;
        }
        addModal.classList.add('is-on');
        addModal.setAttribute('aria-hidden', 'false');
        setTimeout(() => {
            const f = document.getElementById('addTxTitle');
            if (f) f.focus();
        }, 60);
    }

    function closeAddModal() {
        if (!addModal) return;
        addModal.classList.remove('is-on');
        addModal.setAttribute('aria-hidden', 'true');
    }

    async function submitAddTransaction(event) {
        event.preventDefault();

        const title = document.getElementById('addTxTitle').value.trim();
        const amount = parseFloat(document.getElementById('addTxAmount').value);
        const note = document.getElementById('addTxNote').value.trim();
        const method = document.getElementById('addTxMethod').value.trim();
        const category = document.getElementById('addTxCategory').value;
        const date = document.getElementById('addTxDate').value;

        if (!title || isNaN(amount) || amount <= 0) {
            toast('Enter a valid title and a positive amount.', 'error');
            return;
        }

        const formData = new FormData();
        formData.append('title', title);
        formData.append('amount', amount);
        formData.append('category', category);
        formData.append('method', method);
        formData.append('note', note);
        formData.append('date', date);
        formData.append('type', addType);
        if (TOKEN) formData.append('_token', TOKEN);

        try {
            const response = await fetch('../api/api_add_transaction.php', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();

            if (result.success) {
                toast(`Filed “${title}” successfully. Reloading…`, 'success');
                closeAddModal();
                setTimeout(() => location.reload(), 800);
            } else {
                toast(`Failed: ${result.error}`, 'error');
            }
        } catch (err) {
            toast('Error saving transaction.', 'error');
        }
    }

    if (searchInput) {
        searchInput.addEventListener('input', (e) => {
            activeQuery = (e.target.value || '').trim().toLowerCase();
            applyFilters();
        });
    }

    filterBtns.forEach((btn) => {
        btn.addEventListener('click', () => {
            filterBtns.forEach((b) => b.classList.remove('is-on'));
            btn.classList.add('is-on');
            activeFilter = btn.getAttribute('data-filter') || 'all';
            applyFilters();
        });
    });

    rows.forEach((row) => {
        const editBtn = row.querySelector('.edit-tx');
        const deleteBtn = row.querySelector('.delete-tx');
        if (editBtn) editBtn.addEventListener('click', () => openEditModal(row));
        if (deleteBtn) deleteBtn.addEventListener('click', () => deleteTransaction(row));
    });

    document.querySelectorAll('[data-edit-type]').forEach((btn) => {
        btn.addEventListener('click', () => setEditType(btn.dataset.editType));
    });

    if (editForm) editForm.addEventListener('submit', saveTransaction);
    if (editClose) editClose.addEventListener('click', closeEditModal);
    if (editModal) {
        editModal.addEventListener('click', (e) => {
            if (e.target === editModal) closeEditModal();
        });
    }

    if (addOpenBtn) addOpenBtn.addEventListener('click', openAddModal);
    if (addClose) addClose.addEventListener('click', closeAddModal);
    if (addForm) addForm.addEventListener('submit', submitAddTransaction);
    document.querySelectorAll('[data-add-type]').forEach((btn) => {
        btn.addEventListener('click', () => setAddType(btn.dataset.addType));
    });
    if (addModal) {
        addModal.addEventListener('click', (e) => {
            if (e.target === addModal) closeAddModal();
        });
    }

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            closeEditModal();
            closeAddModal();
        }
    });

    applyFilters();
})();
