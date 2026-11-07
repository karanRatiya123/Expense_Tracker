/**
 * ApexSpend — categories.js
 * Management page: grid of category cards, add/edit modal with icon picker
 * and color swatches, delete with confirm, quick-add mode for inline "+".
 * All writes go through api_save_category / api_delete_category (CSRF'd).
 */
(function () {
    'use strict';

    let items = Array.isArray(window.APEX_CATEGORIES) ? window.APEX_CATEGORIES.slice() : [];
    let editingId = null;
    let quickSelectId = null; // select to fill after quick add

    function esc(s) {
        return String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
    }

    // ---- Grid ----
    function render() {
        const grid = document.getElementById('catGrid');
        const empty = document.getElementById('catEmpty');
        if (!grid) return;

        items.sort((a, b) => (a.position - b.position) || a.name.localeCompare(b.name));

        if (!items.length) {
            grid.innerHTML = '';
            if (empty) empty.hidden = false;
            return;
        }
        if (empty) empty.hidden = true;

        grid.innerHTML = items.map((c) => {
            const isB = Number(c.is_builtin) === 1;
            return ''
                + '<div class="cat-card" data-id="' + esc(c.id) + '">'
                +   '<div class="cat-card-top">'
                +     '<span class="cat-tile" style="background:' + esc(c.color) + ';"><i class="fa-solid ' + esc(c.icon) + '"></i></span>'
                +     (isB ? '<span class="cat-badge locked"><i class="fa-solid fa-lock"></i> Built-in</span>'
                          : '<span class="cat-badge">Custom</span>')
                +   '</div>'
                +   '<strong class="cat-name">' + esc(c.name) + '</strong>'
                +   '<span class="cat-meta mono">' + esc(c.color) + '</span>'
                +   '<div class="cat-actions">'
                +     '<button type="button" class="icon-btn" onclick="editCat(\'' + esc(c.id) + '\')" aria-label="Edit ' + esc(c.name) + '"><i class="fa-regular fa-pen-to-square"></i></button>'
                +     (isB
                    ? '<button type="button" class="icon-btn" disabled title="Built-ins can be restyled, not deleted"><i class="fa-solid fa-lock"></i></button>'
                    : '<button type="button" class="icon-btn danger" onclick="deleteCat(\'' + esc(c.id) + '\')" aria-label="Delete ' + esc(c.name) + '"><i class="fa-regular fa-trash-can"></i></button>')
                +   '</div>'
                + '</div>';
        }).join('');

        const kpiCount = document.getElementById('catKpiCount');
        const kpiCustom = document.getElementById('catKpiCustom');
        if (kpiCount) kpiCount.textContent = String(items.length);
        if (kpiCustom) kpiCustom.textContent = String(items.filter((c) => Number(c.is_builtin) !== 1).length);
    }

    // ---- Icon picker + swatches ----
    function buildPicker() {
        const pick = document.getElementById('catIconPick');
        if (pick) {
            pick.innerHTML = (window.ApexCategories ? window.ApexCategories.ICONS : ['fa-tag']).map((ic) =>
                '<button type="button" class="cat-ic-opt" data-icon="' + ic + '" aria-label="' + ic + '"><i class="fa-solid ' + ic + '"></i></button>'
            ).join('');
            pick.addEventListener('click', (e) => {
                const btn = e.target.closest('.cat-ic-opt');
                if (!btn) return;
                pick.querySelectorAll('.cat-ic-opt').forEach((b) => b.classList.remove('is-on'));
                btn.classList.add('is-on');
                document.getElementById('catIconVal').value = btn.dataset.icon;
            });
        }

        const sw = document.getElementById('catSwatches');
        if (sw) {
            sw.innerHTML = (window.ApexCategories ? window.ApexCategories.COLORS : ['#f59e0b']).map((col) =>
                '<button type="button" class="cat-swatch" data-color="' + col + '" style="background:' + col + '" aria-label="' + col + '"></button>'
            ).join('');
            sw.addEventListener('click', (e) => {
                const btn = e.target.closest('.cat-swatch');
                if (!btn) return;
                sw.querySelectorAll('.cat-swatch').forEach((b) => b.classList.remove('is-on'));
                btn.classList.add('is-on');
                document.getElementById('catColorVal').value = btn.dataset.color;
            });
        }
    }

    function markPickers(iconVal, colorVal) {
        const pick = document.getElementById('catIconPick');
        if (pick) {
            pick.querySelectorAll('.cat-ic-opt').forEach((b) => b.classList.toggle('is-on', b.dataset.icon === iconVal));
        }
        ['catSwatches', 'quickCatSwatches'].forEach((id) => {
            const el = document.getElementById(id);
            if (el) el.querySelectorAll('.cat-swatch').forEach((b) => b.classList.toggle('is-on', b.dataset.color === colorVal));
        });
    }

    // ---- Save (create or edit) ----
    window.saveCat = async function (e) {
        if (e) e.preventDefault();
        const id = document.getElementById('catId').value;
        const name = document.getElementById('catName').value.trim();
        const icon = document.getElementById('catIconVal').value;
        const color = document.getElementById('catColorVal').value;
        if (!name) return false;

        const fd = new FormData();
        if (id) fd.append('id', id);
        fd.append('name', name);
        fd.append('icon', icon);
        fd.append('color', color);
        fd.append('_token', window.APEX_TOKEN || '');

        let res;
        try {
            const r = await fetch('../api/api_save_category.php', { method: 'POST', body: fd });
            res = await r.json();
        } catch (_) {
            showToast('Network error. Please retry.', 'error');
            return false;
        }
        if (!res.success) {
            showToast(res.error || 'Could not save.', 'error');
            return false;
        }

        // Update cache + grid without reload.
        const it = res.item;
        const idx = items.findIndex((c) => c.id === it.id);
        if (idx >= 0) items[idx] = it; else items.push(it);
        closeCatModal();
        render();
        showToast(id ? 'Category updated.' : 'Category added.', 'success');
        return false;
    };

    // ---- Edit ----
    window.editCat = function (id) {
        const c = items.find((x) => x.id === id);
        if (!c) return;
        editingId = id;
        document.getElementById('catId').value = c.id;
        document.getElementById('catName').value = c.name;
        document.getElementById('catIconVal').value = c.icon;
        document.getElementById('catColorVal').value = c.color;
        const isB = Number(c.is_builtin) === 1;
        document.getElementById('catName').readOnly = isB;
        document.getElementById('catNameHint').textContent = isB ? 'Built-in — name is locked, restyle freely' : '1–100 characters';
        document.getElementById('catSheetTitle').textContent = 'Edit category';
        document.getElementById('catModalKicker').textContent = '§ 06 · Edit';
        document.getElementById('catSaveLabel').textContent = 'Save changes';
        markPickers(c.icon, c.color);
        const m = document.getElementById('catModal');
        m.classList.add('is-on');
        m.setAttribute('aria-hidden', 'false');
        setTimeout(() => document.getElementById('catName').focus(), 60);
    };

    window.openCatModal = function () {
        editingId = null;
        document.getElementById('catId').value = '';
        document.getElementById('catName').value = '';
        document.getElementById('catName').readOnly = false;
        document.getElementById('catNameHint').textContent = '1–100 characters';
        document.getElementById('catIconVal').value = 'fa-tag';
        document.getElementById('catColorVal').value = '#f59e0b';
        document.getElementById('catSheetTitle').textContent = 'New category';
        document.getElementById('catModalKicker').textContent = '§ 06';
        document.getElementById('catSaveLabel').textContent = 'File category';
        markPickers('fa-tag', '#f59e0b');
        const m = document.getElementById('catModal');
        m.classList.add('is-on');
        m.setAttribute('aria-hidden', 'false');
        setTimeout(() => document.getElementById('catName').focus(), 60);
    };

    window.closeCatModal = function () {
        const m = document.getElementById('catModal');
        if (!m) return;
        m.classList.remove('is-on');
        m.setAttribute('aria-hidden', 'true');
        editingId = null;
    };

    // ---- Delete ----
    window.deleteCat = async function (id) {
        const c = items.find((x) => x.id === id);
        if (!c) return;
        if (!confirm('Delete "' + c.name + '"? Its entries move to "Other" and its budget is removed.')) return;

        const fd = new FormData();
        fd.append('id', id);
        fd.append('_token', window.APEX_TOKEN || '');
        let res;
        try {
            const r = await fetch('../api/api_delete_category.php', { method: 'POST', body: fd });
            res = await r.json();
        } catch (_) {
            showToast('Network error. Please retry.', 'error');
            return;
        }
        if (!res.success) {
            showToast(res.error || 'Could not delete.', 'error');
            return;
        }
        items = items.filter((x) => x.id !== id);
        render();
        showToast('Deleted. Entries reassigned to "Other".', 'info');
    };

    // ---- Quick add (used by the inline "+" next to selects on other pages) ----
    window.openQuickCat = function (selectId) {
        quickSelectId = selectId || null;
        document.getElementById('quickCatName').value = '';
        markPickers('fa-tag', '#f59e0b');
        const m = document.getElementById('quickCatModal');
        m.classList.add('is-on');
        m.setAttribute('aria-hidden', 'false');
        setTimeout(() => document.getElementById('quickCatName').focus(), 60);
    };

    window.closeQuickCat = function () {
        const m = document.getElementById('quickCatModal');
        if (!m) return;
        m.classList.remove('is-on');
        m.setAttribute('aria-hidden', 'true');
        quickSelectId = null;
    };

    window.saveQuickCat = async function (e) {
        if (e) e.preventDefault();
        const name = document.getElementById('quickCatName').value.trim();
        const colorEl = document.querySelector('#quickCatSwatches .cat-swatch.is-on');
        const color = colorEl ? colorEl.dataset.color : '#f59e0b';
        if (!name) return false;

        const fd = new FormData();
        fd.append('name', name);
        fd.append('icon', 'fa-tag');
        fd.append('color', color);
        fd.append('_token', window.APEX_TOKEN || '');

        let res;
        try {
            const r = await fetch('../api/api_save_category.php', { method: 'POST', body: fd });
            res = await r.json();
        } catch (_) {
            showToast('Network error. Please retry.', 'error');
            return false;
        }
        if (!res.success) {
            showToast(res.error || 'Could not save.', 'error');
            return false;
        }

        items.push(res.item);
        showToast('"' + res.item.name + '" added.', 'success');
        closeQuickCat();

        // Refresh any category select so the new one is selectable now.
        await (window.ApexCategories ? window.ApexCategories.get(true) : Promise.resolve());
        if (quickSelectId) {
            const sel = document.getElementById(quickSelectId);
            if (sel) window.ApexCategories.populateSelect(sel, { current: res.item.name });
        } else {
            document.querySelectorAll('select[id$="Category"]').forEach((sel) => {
                window.ApexCategories.populateSelect(sel, {});
            });
        }
        render();
        return false;
    };

    // ---- Boot ----
    document.addEventListener('DOMContentLoaded', () => {
        buildPicker();
        render();
        const scrim1 = document.getElementById('catModal');
        if (scrim1) scrim1.addEventListener('click', (e) => { if (e.target === scrim1) closeCatModal(); });
        const scrim2 = document.getElementById('quickCatModal');
        if (scrim2) scrim2.addEventListener('click', (e) => { if (e.target === scrim2) closeQuickCat(); });
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') { closeCatModal(); closeQuickCat(); }
        });
    });
})();
