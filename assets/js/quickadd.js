/**
 * ApexSpend — quickadd.js
 * The inline "+" next to every category select (Ledger + Budgets modals).
 * Builds one lightweight quick-add sheet on demand, posts to
 * api_save_category.php, then refreshes ApexCategories and preselects the
 * new category in the select that opened it. Not loaded on categories.php —
 * its own categories.js provides the full-page editor instead.
 */
(function () {
    'use strict';

    let selectTarget = null;
    let built = false;
    let scrim = null;

    function COLORS() {
        return (window.ApexCategories && window.ApexCategories.COLORS) || ['#f59e0b', '#3b82f6', '#a855f7', '#4ade80', '#ec4899', '#22d3ee'];
    }

    function escape(s) {
        return String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
    }

    function build() {
        if (built) return;
        scrim = document.createElement('div');
        scrim.className = 'scrim';
        scrim.id = 'quickCatModal';
        scrim.setAttribute('aria-hidden', 'true');
        scrim.innerHTML = ''
            + '<div class="sheet" role="dialog" aria-labelledby="quickCatTitle">'
            +   '<div class="sheet-head">'
            +     '<div><span class="kicker-num">§ 06</span><h3 id="quickCatTitle">Quick add</h3></div>'
            +     '<button type="button" class="close" aria-label="Close"><i class="fa-solid fa-xmark"></i></button>'
            +   '</div>'
            +   '<form id="quickCatForm">'
            +     '<div class="field">'
            +       '<div class="field-row"><label for="quickCatName">Category name</label></div>'
            +       '<div class="input-shell">'
            +         '<i class="fa-solid fa-tag" aria-hidden="true"></i>'
            +         '<input type="text" id="quickCatName" maxlength="100" placeholder="e.g. Gym &amp; Fitness" required>'
            +       '</div>'
            +     '</div>'
            +     '<div class="field">'
            +       '<div class="field-row"><label>Color</label></div>'
            +       '<div class="cat-swatches" role="radiogroup" aria-label="Pick a color">'
            +         COLORS().map((c, i) => '<button type="button" class="cat-swatch' + (i === 0 ? ' is-on' : '') + '" data-color="' + c + '" style="background:' + c + '" aria-label="' + c + '"></button>').join('')
            +       '</div>'
            +     '</div>'
            +     '<button type="submit" class="submit">'
            +       '<i class="fa-solid fa-plus" aria-hidden="true"></i><span>Create &amp; use</span>'
            +     '</button>'
            +   '</form>'
            + '</div>';
        document.body.appendChild(scrim);

        scrim.addEventListener('click', (e) => { if (e.target === scrim) window.closeQuickCat(); });
        scrim.querySelector('.close').addEventListener('click', window.closeQuickCat);
        scrim.querySelector('.cat-swatches').addEventListener('click', (e) => {
            const btn = e.target.closest('.cat-swatch');
            if (!btn) return;
            scrim.querySelectorAll('.cat-swatch').forEach((b) => b.classList.remove('is-on'));
            btn.classList.add('is-on');
        });
        document.getElementById('quickCatForm').addEventListener('submit', save);
        document.addEventListener('keydown', (e) => { if (e.key === 'Escape') window.closeQuickCat(); });
        built = true;
    }

    window.openQuickCat = function (selectId) {
        selectTarget = selectId || null;
        build();
        document.getElementById('quickCatName').value = '';
        scrim.classList.add('is-on');
        scrim.setAttribute('aria-hidden', 'false');
        setTimeout(() => document.getElementById('quickCatName').focus(), 60);
    };

    window.closeQuickCat = function () {
        if (!scrim) return;
        scrim.classList.remove('is-on');
        scrim.setAttribute('aria-hidden', 'true');
        selectTarget = null;
    };

    async function save(e) {
        e.preventDefault();
        const name = document.getElementById('quickCatName').value.trim();
        const sw = scrim.querySelector('.cat-swatch.is-on');
        const color = sw ? sw.dataset.color : '#f59e0b';
        if (!name) return false;

        const fd = new FormData();
        fd.append('name', name);
        fd.append('icon', 'fa-tag');
        fd.append('color', color);
        fd.append('_token', window.APEX_TOKEN || '');

        let res;
        try {
            const resR = await fetch('../api/api_save_category.php', { method: 'POST', body: fd });
            res = await resR.json();
        } catch (_) {
            if (window.showToast) window.showToast('Network error. Please retry.', 'error');
            return false;
        }
        if (!res.success) {
            if (window.showToast) window.showToast(res.error || 'Could not save category.', 'error');
            return false;
        }

        window.closeQuickCat();
        if (window.showToast) window.showToast('"' + res.item.name + '" added — already selected.', 'success');

        // Refresh the live cache, then refresh + preselect every category select.
        if (window.ApexCategories) {
            await window.ApexCategories.get(true);
            const selectAll = () => {
                document.querySelectorAll('select[id$="Category"], select[id="bCategory"]').forEach((sel) => {
                    window.ApexCategories.populateSelect(sel, sel === document.getElementById(selectTarget) ? { current: res.item.name } : {});
                });
            };
            selectAll();
        } else if (selectTarget) {
            const sel = document.getElementById(selectTarget);
            if (sel) {
                const o = document.createElement('option');
                o.value = res.item.name;
                o.textContent = res.item.name;
                o.selected = true;
                sel.appendChild(o);
            }
        }
        return false;
    }
})();
