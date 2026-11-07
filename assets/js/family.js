/**
 * ApexSpend — Family Space
 * Create → name → code → Continue → members page.
 * Join → code validated server-side → members page.
 */

let famMode = 'create';

function openFamilyModal(mode, presetName) {
    famMode = mode === 'join' ? 'join' : 'create';
    const m = document.getElementById('familyModal');
    if (!m) return;
    const title = document.getElementById('familySheetTitle');
    const kicker = document.getElementById('familyModalKicker');
    const nameInput = document.getElementById('famModalName');
    const codeInput = document.getElementById('famModalCodeInput');
    const nameWrap = document.getElementById('famModalNameWrap');
    const codeWrap = document.getElementById('famModalCodeWrap');
    const submitLabel = document.getElementById('famModalSubmitLabel');
    const note = document.getElementById('famModalNote');
    const isJoin = famMode === 'join';

    if (title) title.textContent = isJoin ? 'Join family' : 'Name your family';
    if (kicker) kicker.textContent = isJoin ? '§ 05 · Join' : '§ 05 · Create';
    if (nameWrap) nameWrap.hidden = isJoin;
    if (codeWrap) codeWrap.hidden = !isJoin;
    if (submitLabel) submitLabel.textContent = isJoin ? 'Join family' : 'Create family';
    if (note) note.textContent = isJoin
        ? 'Wrong code cannot join · ask a member to re-share.'
        : 'You become admin · members join with your code.';

    document.getElementById('famStep1').hidden = false;
    document.getElementById('famStep2').hidden = true;

    if (presetName && nameInput && !nameInput.value) nameInput.value = presetName;

    m.classList.add('is-on');
    m.setAttribute('aria-hidden', 'false');
    setTimeout(() => { (isJoin ? codeInput : nameInput)?.focus(); }, 60);
}

function closeFamilyModal() {
    const m = document.getElementById('familyModal');
    if (!m) return;
    m.classList.remove('is-on');
    m.setAttribute('aria-hidden', 'true');
}

async function familyModalSubmit(e) {
    if (e) e.preventDefault();
    if (famMode === 'join') {
        const code = (document.getElementById('famModalCodeInput').value || '').trim();
        await doJoin(code);
    } else {
        const name = (document.getElementById('famModalName').value || '').trim();
        await doCreate(name);
    }
    return false;
}

// Inline cards (locked page): Join posts directly, Create opens the modal.
async function familyCardJoin(e) {
    if (e) e.preventDefault();
    const code = (document.getElementById('famJoinCode').value || '').trim();
    await doJoin(code, document.getElementById('famJoinCode'));
    return false;
}

function familyCardCreate(e) {
    if (e) e.preventDefault();
    const name = (document.getElementById('famCreateName').value || '').trim();
    if (!name || name.length < 2) {
        showToast('Give your family a name first (min. 2 characters).', 'error');
        return false;
    }
    openFamilyModal('create', name);
    return false;
}

async function doCreate(name) {
    if (!name || name.length < 2) {
        showToast('Give your family a name first (min. 2 characters).', 'error');
        return;
    }
    const fd = new FormData();
    fd.append('name', name);
    fd.append('_token', window.APEX_TOKEN || '');
    let res;
    try {
        const r = await fetch('../api/api_create_family.php', { method: 'POST', body: fd });
        res = await r.json();
    } catch (_) {
        showToast('Network error. Please retry.', 'error');
        return;
    }
    if (!res.success) {
        showToast(res.error || 'Could not create family.', 'error');
        return;
    }
    // Step 2: show the code → Continue opens the members page.
    document.getElementById('famStep1').hidden = true;
    document.getElementById('famStep2').hidden = false;
    document.getElementById('famStep2CodeBlock').hidden = false;
    document.getElementById('famStep2Label').textContent = 'Your invite code — share it';
    document.getElementById('famModalCode').textContent = res.invite_code;
    document.getElementById('famStep2Text').textContent =
        '“' + res.name + '” is ready. Share the code so members can join.';
}

async function doJoin(code, focusEl) {
    code = (code || '').trim();
    if (code.length < 4) {
        showToast('Enter the invite code first (min. 4 characters).', 'error');
        if (focusEl) focusEl.focus();
        else document.getElementById('famModalCodeInput')?.focus();
        return;
    }
    const fd = new FormData();
    fd.append('code', code);
    fd.append('_token', window.APEX_TOKEN || '');
    let res;
    try {
        const r = await fetch('../api/api_join_family.php', { method: 'POST', body: fd });
        res = await r.json();
    } catch (_) {
        showToast('Network error. Please retry.', 'error');
        return;
    }
    if (!res.success) {
        showToast(res.error || 'Could not join family.', 'error');
        return;
    }
    // Step 2: confirm → Continue opens the members page.
    const modalOn = document.getElementById('familyModal')?.classList.contains('is-on');
    if (modalOn) {
        document.getElementById('famStep1').hidden = true;
        document.getElementById('famStep2').hidden = false;
        document.getElementById('famStep2CodeBlock').hidden = true;
        document.getElementById('famStep2Text').textContent =
            (res.already ? 'You are already in “' : 'You joined “') + res.name + '”. Members and shared entries are inside.';
    } else {
        showToast('Joined “' + res.name + '”. Opening…', 'success');
        setTimeout(() => window.location.reload(), 700);
    }
}

async function leaveFamily() {
    if (!confirm('Leave this family? You will lose access to the shared ledger.')) return;
    const fd = new FormData();
    fd.append('_token', window.APEX_TOKEN || '');
    try {
        const r = await fetch('../api/api_leave_family.php', { method: 'POST', body: fd });
        const res = await r.json();
        if (!res.success) {
            showToast(res.error || 'Could not leave.', 'error');
            return;
        }
    } catch (_) {
        showToast('Network error. Please retry.', 'error');
        return;
    }
    window.location.reload();
}

function copyInviteCode() {
    const el = document.getElementById('famInviteCode');
    const code = el ? el.textContent.trim() : '';
    if (navigator.clipboard && code) {
        navigator.clipboard.writeText(code).then(
            () => showToast('Code "' + code + '" copied.', 'success'),
            () => showToast('Copy blocked by browser.', 'error')
        );
    }
}

function copyFamilyCode() {
    const codeEl = document.getElementById('famModalCode');
    const code = codeEl ? codeEl.textContent.trim() : '';
    if (navigator.clipboard && code) {
        navigator.clipboard.writeText(code).then(
            () => showToast('Code "' + code + '" copied.', 'success'),
            () => showToast('Copy blocked by browser.', 'error')
        );
    }
}

// Fullscreen veil button — scroll to the Join card.
function gotoFamilyJoin() {
    const input = document.getElementById('famJoinCode');
    if (input) {
        input.scrollIntoView({ behavior: 'smooth', block: 'center' });
        setTimeout(() => input.focus({ preventScroll: true }), 450);
    }
}

document.addEventListener('DOMContentLoaded', () => {
    wireFamHeatFilter();
    // Heatmap member filter — All or one member, rebuilt live from server data.
function wireFamHeatFilter() {
    const btns = document.querySelectorAll('.fam-heat-filters button');
    if (!btns.length) return;
    btns.forEach((b) => {
        b.addEventListener('click', () => {
            btns.forEach((x) => x.classList.remove('is-on'));
            b.classList.add('is-on');
            window.heatmapMemberFilter = b.dataset.member || 'all';
            if (typeof buildHeatmap === 'function') buildHeatmap();
            updateHeatCount();
        });
    });
    updateHeatCount();
}

// Active-day counter under the heatmap head — proves the data is live.
function updateHeatCount() {
    const el = document.getElementById('heatDayCount');
    if (!el || typeof heatmapData === 'undefined') return;
    let days = 0, total = 0;
    for (const k in heatmapData) {
        if (heatmapData[k].total > 0) { days++; total += heatmapData[k].total; }
    }
    el.textContent = days + ' active day' + (days === 1 ? '' : 's');
}

const upper = (el) => {
        if (!el) return;
        el.addEventListener('input', () => {
            const pos = el.selectionStart;
            el.value = el.value.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 6);
            try { el.setSelectionRange(pos, pos); } catch (_) {}
        });
    };
    upper(document.getElementById('famJoinCode'));
    upper(document.getElementById('famModalCodeInput'));
    const modal = document.getElementById('familyModal');
    if (modal) {
        modal.addEventListener('click', (e) => { if (e.target === modal) closeFamilyModal(); });
    }
    const memModal = document.getElementById('memberModal');
    if (memModal) {
        memModal.addEventListener('click', (e) => { if (e.target === memModal) closeMemberModal(); });
    }
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') { closeFamilyModal(); closeMemberModal(); } });
});

// Fullscreen lock overlay — hides everything behind it by default.
function closeFamLock() {
    document.getElementById('famLockScreen')?.classList.add('is-hidden');
}
function showFamLock() {
    document.getElementById('famLockScreen')?.classList.remove('is-hidden');
}

// ================= MEMBER DETAIL =================
let memCurrentId = null;

function escapeHtml(s) {
    return String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
}

function fmtINR(n) {
    const num = Number(n) || 0;
    return num.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function fmtDate(iso) {
    const d = new Date(String(iso).replace(' ', 'T'));
    if (isNaN(d.getTime())) return '—';
    return d.toLocaleDateString('en-IN', { day: '2-digit', month: 'short' }) + ' · ' +
        String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0');
}

async function openMemberModal(userId) {
    memCurrentId = userId;
    const m = document.getElementById('memberModal');
    if (!m || !window.FAM_ID) return;
    document.getElementById('memName').textContent = 'Loading…';
    document.getElementById('memEmail').textContent = '';
    document.getElementById('memAvatar').textContent = '?';
    document.getElementById('memStats').innerHTML = '';
    document.getElementById('memTxList').innerHTML = '<p class="mem-empty">Loading entries…</p>';
    document.getElementById('memRemoveBtn').hidden = true;
    m.classList.add('is-on');
    m.setAttribute('aria-hidden', 'false');

    let res;
    try {
        const r = await fetch('../api/api_get_member.php?family_id=' + encodeURIComponent(window.FAM_ID) + '&user_id=' + encodeURIComponent(userId));
        res = await r.json();
    } catch (_) {
        showToast('Network error. Please retry.', 'error');
        closeMemberModal();
        return;
    }
    if (!res.success) {
        showToast(res.error || 'Could not load member.', 'error');
        closeMemberModal();
        return;
    }

    const mem = res.member, st = res.stats;
    document.getElementById('memAvatar').textContent = (mem.name || '?').trim().charAt(0).toUpperCase();
    document.getElementById('memName').textContent = mem.name + (mem.id === window.FAM_USER_ID ? ' (you)' : '');
    document.getElementById('memEmail').textContent = mem.email + ' · joined ' + fmtDate(mem.joined_at);
    const roleEl = document.getElementById('memRole');
    roleEl.textContent = mem.role === 'admin' ? 'Admin' : 'Member';

    document.getElementById('memStats').innerHTML =
        '<div class="mem-stat"><span>Inflow</span><strong class="pos">+ ₹' + fmtINR(st.income) + '</strong></div>' +
        '<div class="mem-stat"><span>Outflow</span><strong>− ₹' + fmtINR(st.expense) + '</strong></div>' +
        '<div class="mem-stat"><span>Net</span><strong>₹' + fmtINR(st.net) + '</strong></div>' +
        '<div class="mem-stat"><span>Top category</span><strong>' + escapeHtml(st.top_category || '—') + '</strong></div>';

    const list = document.getElementById('memTxList');
    document.getElementById('memTxCount').textContent = st.count + ' total';
    if (!res.transactions.length) {
        list.innerHTML = '<p class="mem-empty">No entries yet.</p>';
    } else {
        list.innerHTML = res.transactions.map((t) => {
            const inc = t.type === 'income';
            return '<div class="mem-tx">' +
                '<span class="mem-tx-when">' + escapeHtml(fmtDate(t.date)) + '</span>' +
                '<span class="mem-tx-main"><strong>' + escapeHtml(t.title) + '</strong>' + escapeHtml(t.note || '') + '</span>' +
                '<span class="mem-tx-amt' + (inc ? ' pos' : '') + '">' + (inc ? '+' : '−') + ' ₹' + fmtINR(t.amount) + '</span>' +
                '</div>';
        }).join('');
    }

    // Admin can remove any member except themselves.
    if (window.FAM_IS_ADMIN && mem.id !== window.FAM_USER_ID) {
        document.getElementById('memRemoveBtn').hidden = false;
    }
}

function closeMemberModal() {
    const m = document.getElementById('memberModal');
    if (!m) return;
    m.classList.remove('is-on');
    m.setAttribute('aria-hidden', 'true');
    memCurrentId = null;
}

async function removeMember() {
    if (!memCurrentId || !window.FAM_ID) return;
    const name = document.getElementById('memName').textContent;
    if (!confirm('Remove ' + name + ' from this family? Their entries stay in their own ledger.')) return;
    const fd = new FormData();
    fd.append('family_id', window.FAM_ID);
    fd.append('user_id', memCurrentId);
    fd.append('_token', window.APEX_TOKEN || '');
    let res;
    try {
        const r = await fetch('../api/api_remove_member.php', { method: 'POST', body: fd });
        res = await r.json();
    } catch (_) {
        showToast('Network error. Please retry.', 'error');
        return;
    }
    if (!res.success) {
        showToast(res.error || 'Could not remove member.', 'error');
        return;
    }
    showToast('Member removed.', 'success');
    setTimeout(() => window.location.reload(), 700);
}
