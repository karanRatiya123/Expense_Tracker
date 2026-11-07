/**
 * ApexSpend — Budgets Engine
 *   1. Reads #budgetsData (server-embedded transactions) + window.localStorage('apexspend.budgets.v1')
 *   2. Computes spent = sum(category == budget.category, type == 'expense', date in budget.period)
 *   3. Renders masthead hero, KPI strip, budget cards, 30-day heatmap strip per card
 *   4. Wires period toggle (monthly/weekly/custom) — global filter; per-card custom periods win
 *   5. Wires add/edit/delete modal (re-uses .scrim + .sheet)
 *   6. Threshold toasts: 75% / 90% / 100% — fire once per (budget, threshold) per session
 *
 * Helpers (formatINR, categoryIcon, escapeHtml, showToast) are duplicated from app.js
 * because there is no build step. KEEP IN SYNC with app.js + analytics.js.
 */
(function () { 'use strict';

  // ==========================================================================
  // Helpers (KEEP IN SYNC with app.js)
  // ==========================================================================
  function formatINR(n) {
    const num = Number(n) || 0;
    const fixed = num.toFixed(2);
    const parts = fixed.split('.');
    const intPart = parts[0];
    const decPart = parts[1];

    if (intPart.length <= 3) return intPart + '.' + decPart;

    const last3 = intPart.slice(-3);
    const rest  = intPart.slice(0, -3);
    const grouped = rest.replace(/\B(?=(\d{2})+(?!\d))/g, ',');
    return grouped + ',' + last3 + '.' + decPart;
  }

  function categoryIcon(cat) {
    // Delegates to the single source of truth (category-helper.js).
    if (window.ApexCategories) return window.ApexCategories.icon(cat);
    return 'fa-tag';
  }

  function escapeHtml(s) {
    return String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
  }

  function showToast(message, type) {
    const container = document.getElementById('toastContainer');
    if (!container) return;
    const t = (type || 'info').toLowerCase();
    const toast = document.createElement('div');
    toast.className = 'toast ' + t;
    let icon = 'fa-circle-info';
    if (t === 'success') icon = 'fa-circle-check';
    if (t === 'error')   icon = 'fa-circle-exclamation';
    toast.innerHTML = '<i class="fa-solid ' + icon + '" aria-hidden="true"></i><span>' + escapeHtml(message) + '</span>';
    container.appendChild(toast);
    setTimeout(() => {
      toast.style.opacity = '0';
      toast.style.transform = 'translateY(6px)';
      setTimeout(() => toast.remove(), 250);
    }, 3200);
  }

  // ==========================================================================
  // Date helpers
  // ==========================================================================
  function isoDate(d) {
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const dd = String(d.getDate()).padStart(2, '0');
    return y + '-' + m + '-' + dd;
  }
  function addDays(d, n) {
    const out = new Date(d.getTime());
    out.setDate(out.getDate() + n);
    return out;
  }

  // ==========================================================================
  // Data + state
  // ==========================================================================
  const DATA = JSON.parse(document.getElementById('budgetsData').textContent);
  const STORAGE_KEY = 'apexspend.budgets.v1';
  const TODAY = new Date(DATA.demoDate + 'T00:00:00');



  let budgets = [];
  let activePeriod = 'monthly';
  let customRange = { start: isoDate(addDays(TODAY, -29)), end: isoDate(TODAY) };
  let editingId = null;
  const firedToasts = new Set();

  // Tangerine tints for the heat-strip (page signature color)
  const HEAT_LEVELS = [
    'rgba(245, 158, 11, 0.04)',
    'rgba(245, 158, 11, 0.18)',
    'rgba(245, 158, 11, 0.34)',
    'rgba(245, 158, 11, 0.55)',
    'rgba(245, 158, 11, 0.85)',
  ];
  function heatLevel(amount) {
    if (amount <= 0)   return 0;
    if (amount < 200)  return 1;
    if (amount < 600)  return 2;
    if (amount < 1500) return 3;
    return 4;
  }

  // ==========================================================================
  // Storage
  // ==========================================================================
  async function fetchBudgets() {
    try {
      const res = await fetch('../api/api_get_budgets.php');
      const data = await res.json();
      if (data.success) {
        return { items: data.items };
      }
    } catch (e) {
      console.error('Error fetching budgets:', e);
    }
    return { items: [] };
  }

  async function saveBudgetToDB(b) {
    const fd = new FormData();
    fd.append('id', b.id);
    fd.append('category', b.category);
    fd.append('amount', b.amount);
    if (b.period) {
      fd.append('period_type', b.period.type);
      if (b.period.startDate) fd.append('start_date', b.period.startDate);
      if (b.period.endDate) fd.append('end_date', b.period.endDate);
    }
    fd.append('thresholds', JSON.stringify(b.thresholds || [75, 90, 100]));
    fd.append('_token', window.APEX_TOKEN || '');
    try {
      await fetch('../api/api_save_budget.php', { method: 'POST', body: fd });
    } catch (e) {
      console.error('Error saving budget:', e);
    }
  }

  async function deleteBudgetFromDB(id) {
    const fd = new FormData();
    fd.append('id', id);
    fd.append('_token', window.APEX_TOKEN || '');
    try {
      await fetch('../api/api_delete_budget.php', { method: 'POST', body: fd });
    } catch (e) {
      console.error('Error deleting budget:', e);
    }
  }

  function makeId() {
    let id = 'bg_';
    try {
      id += (crypto.randomUUID ? crypto.randomUUID() : String(Math.random())).slice(0, 8);
    } catch (e) {
      id += Math.random().toString(36).slice(2, 10);
    }
    return id;
  }

  async function addBudget(rec) {
    const newB = Object.assign({
      id: makeId(),
      thresholds: [75, 90, 100],
      createdAt: new Date().toISOString(),
    }, rec);
    budgets.items.push(newB);
    await saveBudgetToDB(newB);
  }
  async function updateBudget(id, patch) {
    const idx = budgets.items.findIndex((b) => b.id === id);
    if (idx === -1) return;
    budgets.items[idx] = Object.assign({}, budgets.items[idx], patch);
    await saveBudgetToDB(budgets.items[idx]);
    // Clear any threshold toasts for this budget so re-crossing re-fires
    for (const k of Array.from(firedToasts)) {
      if (k.indexOf(id + '_') === 0) firedToasts.delete(k);
    }
  }
  async function deleteBudget(id) {
    budgets.items = budgets.items.filter((b) => b.id !== id);
    await deleteBudgetFromDB(id);
    for (const k of Array.from(firedToasts)) {
      if (k.indexOf(id + '_') === 0) firedToasts.delete(k);
    }
  }

  // ==========================================================================
  // Period + spend
  // ==========================================================================
  function rangeForBudget(b) {
    if (b.period && b.period.type === 'custom' && b.period.startDate && b.period.endDate) {
      return {
        start: new Date(b.period.startDate + 'T00:00:00'),
        end:   new Date(b.period.endDate   + 'T23:59:59'),
      };
    }
    if (activePeriod === 'weekly') {
      return { start: addDays(TODAY, -6), end: new Date(TODAY.getFullYear(), TODAY.getMonth(), TODAY.getDate(), 23, 59, 59) };
    }
    if (activePeriod === 'custom') {
      return {
        start: new Date(customRange.start + 'T00:00:00'),
        end:   new Date(customRange.end   + 'T23:59:59'),
      };
    }
    // monthly
    return {
      start: new Date(TODAY.getFullYear(), TODAY.getMonth(), 1),
      end:   new Date(TODAY.getFullYear(), TODAY.getMonth() + 1, 0, 23, 59, 59),
    };
  }

  function computeSpent(b, range) {
    let total = 0;
    for (const t of DATA.transactions) {
      if (t.type !== 'expense') continue;
      if (t.category !== b.category) continue;
      const d = new Date(t.date.replace(' ', 'T'));
      if (d < range.start || d > range.end) continue;
      total += t.amount;
    }
    return total;
  }

  function periodLabel(b) {
    if (b.period && b.period.type === 'custom' && b.period.startDate && b.period.endDate) {
      return b.period.startDate + ' → ' + b.period.endDate;
    }
    if (b.period && b.period.type === 'weekly') return 'Weekly';
    if (b.period && b.period.type === 'monthly') return 'Monthly';
    return b.period ? b.period.type : 'Monthly';
  }

  // ==========================================================================
  // 30-day heat strip (per card)
  // ==========================================================================
  function buildHeatStrip(category) {
    const byDay = {};
    for (const t of DATA.transactions) {
      if (t.type !== 'expense' || t.category !== category) continue;
      const key = t.date.slice(0, 10);
      byDay[key] = (byDay[key] || 0) + t.amount;
    }
    const cells = [];
    for (let i = 29; i >= 0; i--) {
      const d = addDays(TODAY, -i);
      const key = isoDate(d);
      const total = byDay[key] || 0;
      cells.push({ date: key, total: total, level: heatLevel(total) });
    }
    return cells;
  }

  // ==========================================================================
  // Status helpers
  // ==========================================================================
  function statusFor(pct) {
    if (pct > 100)  return { key: 'over',      label: 'Over' };
    if (pct >= 90)  return { key: 'near',      label: 'Near limit' };
    if (pct >= 75)  return { key: 'warn-zone', label: 'Watch' };
    return { key: 'on-track', label: 'On track' };
  }

  // ==========================================================================
  // Card render
  // ==========================================================================
  function renderCard(b, spent) {
    const pct = b.amount > 0 ? (spent / b.amount) * 100 : 0;
    const displayPct = Math.min(999, pct);
    const over = spent > b.amount;
    const fillCls = over ? 'warn' : (pct >= 90 ? 'alt' : '');
    const labelCls = over ? 'warn' : '';
    const status = statusFor(pct);
    const remaining = Math.max(0, b.amount - spent);

    const cells = buildHeatStrip(b.category).map((c) => {
      const bg = HEAT_LEVELS[c.level];
      const tip = c.date + ' · ₹' + formatINR(c.total);
      return '<span class="cell" data-level="' + c.level + '" title="' + escapeHtml(tip) + '" style="background:' + bg + '"></span>';
    }).join('');

    return ''
      + '<div class="budget" data-bid="' + b.id + '">'
      +   '<div class="budget-head">'
      +     '<div class="budget-cat">'
      +       '<i class="fa-solid ' + categoryIcon(b.category) + '" aria-hidden="true"></i>'
      +       '<span>' + escapeHtml(b.category) + '</span>'
      +       '<span class="status-badge ' + status.key + '">' + status.label + '</span>'
      +     '</div>'
      +     '<button type="button" class="budget-edit-btn" onclick="openBudgetModal(\'' + b.id + '\')" aria-label="Edit budget">'
      +       '<i class="fa-solid fa-pen"></i>'
      +     '</button>'
      +   '</div>'
      +   '<div class="budget-figures">'
      +     '<span class="budget-spent">₹' + formatINR(spent) + '</span>'
      +     '<span class="budget-of">of ₹' + formatINR(b.amount) + ' · ' + escapeHtml(periodLabel(b)) + '</span>'
      +   '</div>'
      +   '<div class="progress large">'
      +     '<div class="progress-track">'
      +       '<div class="progress-fill ' + fillCls + '" style="--w: ' + Math.min(100, displayPct).toFixed(1) + '%"></div>'
      +       '<div class="budget-thresholds" aria-hidden="true">'
      +         '<span style="left:75%"></span>'
      +         '<span style="left:90%"></span>'
      +         '<span style="left:100%"></span>'
      +       '</div>'
      +     '</div>'
      +     '<span class="progress-label ' + labelCls + '">' + displayPct.toFixed(0) + '%' + (over ? ' · over budget' : ' · used') + '</span>'
      +   '</div>'
      +   '<div class="heat-strip" aria-label="Last 30 days of spend">' + cells + '</div>'
      +   '<div class="budget-foot" style="margin-top:8px;display:flex;justify-content:space-between;font-family:var(--mono);font-size:10px;letter-spacing:0.14em;text-transform:uppercase;color:var(--whisper);">'
      +     '<span>Remaining ₹' + formatINR(remaining) + '</span>'
      +     '<span>' + escapeHtml(periodLabel(b)) + '</span>'
      +   '</div>'
      + '</div>';
  }

  // ==========================================================================
  // Threshold toasts
  // ==========================================================================
  function maybeFireThresholdToasts(b, spent) {
    if (!b.amount || b.amount <= 0) return;
    const pct = (spent / b.amount) * 100;
    const thresholds = Array.isArray(b.thresholds) && b.thresholds.length ? b.thresholds : [75, 90, 100];
    for (const t of thresholds) {
      const key = b.id + '_' + t;
      if (pct >= t && !firedToasts.has(key)) {
        firedToasts.add(key);
        let word, type;
        if (t >= 100)      { word = 'is over budget';   type = 'error'; }
        else if (t >= 90)  { word = 'is near its limit'; type = 'info'; }
        else               { word = 'hit a milestone';   type = 'success'; }
        showToast(b.category + ' ' + word + ' · ' + t + '% (₹' + formatINR(spent) + ' of ₹' + formatINR(b.amount) + ').', type);
      }
    }
  }

  // ==========================================================================
  // Main render
  // ==========================================================================
  function render() {
    const grid = document.getElementById('budgetsGrid');
    const empty = document.getElementById('budgetsEmpty');
    const count = document.getElementById('budgetCount');
    const heroLabel = document.getElementById('heroPeriodLabel');
    const heroAllocated = document.getElementById('heroAllocated');
    const heroSpent = document.getElementById('heroSpent');
    const heroRemaining = document.getElementById('heroRemaining');

    if (heroLabel) heroLabel.textContent = activePeriod === 'custom' ? 'Custom' : (activePeriod.charAt(0).toUpperCase() + activePeriod.slice(1));

    const items = budgets.items;
    if (count) count.textContent = items.length + (items.length === 1 ? ' budget' : ' budgets');

    if (items.length === 0) {
      if (grid) grid.innerHTML = '';
      if (empty) empty.hidden = false;
      if (heroAllocated) heroAllocated.textContent = '—';
      if (heroSpent) heroSpent.textContent = '—';
      if (heroRemaining) heroRemaining.textContent = '—';
      setKpi(0, 0, 0, 0, 0);
      return;
    }
    if (empty) empty.hidden = true;

    let totalAllocated = 0;
    let totalSpent = 0;
    let overCount = 0;
    let onTrackCount = 0;
    const cards = [];

    for (const b of items) {
      const range = rangeForBudget(b);
      const spent = computeSpent(b, range);
      totalAllocated += Number(b.amount) || 0;
      totalSpent += spent;

      const pct = (b.amount > 0) ? (spent / b.amount) * 100 : 0;
      if (pct > 100) overCount++;
      else if (pct < 75) onTrackCount++;

      cards.push(renderCard(b, spent));
      maybeFireThresholdToasts(b, spent);
    }

    if (grid) grid.innerHTML = cards.join('');

    if (heroAllocated) heroAllocated.textContent = formatINR(totalAllocated);
    if (heroSpent) heroSpent.textContent = '₹' + formatINR(totalSpent);
    if (heroRemaining) heroRemaining.textContent = '₹' + formatINR(Math.max(0, totalAllocated - totalSpent));

    const totalPct = totalAllocated > 0 ? (totalSpent / totalAllocated) * 100 : 0;
    const remainingPct = 100 - totalPct;
    setKpi(
      totalAllocated,
      totalSpent,
      Math.max(0, totalAllocated - totalSpent),
      overCount,
      onTrackCount,
      totalPct,
      remainingPct
    );
  }

  function setKpi(budgeted, spent, remaining, over, onTrack, spentPct, remainingPct) {
    const set = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = val; };
    set('kpiBudgeted', formatINR(budgeted));
    set('kpiSpent', formatINR(spent));
    set('kpiRemaining', formatINR(remaining));
    set('kpiOver', String(over));
    set('kpiOnTrack', String(onTrack));
    set('kpiSpentPct', (typeof spentPct === 'number') ? (spentPct.toFixed(0) + '% of total budgeted') : '—');
    set('kpiRemainingPct', (typeof remainingPct === 'number') ? (remainingPct.toFixed(0) + '% headroom') : '—');
  }

  // ==========================================================================
  // Modal
  // ==========================================================================
  function populateCategorySelect() {
    const sel = document.getElementById('bCategory');
    if (!sel) return;
    // Live list from the categories table, kept current even mid-session.
    if (window.ApexCategories) {
      window.ApexCategories.get(true).then(() => {
        window.ApexCategories.populateSelect(sel, {});
      });
    } else {
      const cats = Object.keys(DATA.categories || {});
      sel.innerHTML = cats.map((c) => '<option value="' + c + '">' + c + '</option>').join('');
    }
  }

  window.openBudgetModal = function (id) {
    populateCategorySelect();
    editingId = id || null;

    const title = document.getElementById('budgetSheetTitle');
    const saveLabel = document.getElementById('bSaveLabel');
    const deleteBtn = document.getElementById('bDeleteBtn');
    const kicker = document.getElementById('budgetModalKicker');
    const customRange = document.getElementById('bCustomRange');

    document.getElementById('bAmount').value = '';
    document.getElementById('bPeriod').value = 'monthly';
    customRange.hidden = true;
    document.getElementById('bStart').value = '';
    document.getElementById('bEnd').value = '';
    document.getElementById('bT1').value = 75;
    document.getElementById('bT2').value = 90;
    document.getElementById('bT3').value = 100;

    if (id) {
      const b = budgets.items.find((x) => x.id === id);
      if (!b) return;
      title.textContent = 'Edit budget';
      saveLabel.textContent = 'Update';
      deleteBtn.hidden = false;
      kicker.textContent = '§ 04';
      document.getElementById('bCategory').value = b.category;
      document.getElementById('bAmount').value = b.amount;
      const p = b.period || { type: 'monthly' };
      document.getElementById('bPeriod').value = p.type;
      if (p.type === 'custom') {
        customRange.hidden = false;
        document.getElementById('bStart').value = p.startDate || '';
        document.getElementById('bEnd').value = p.endDate || '';
      }
      const th = (b.thresholds && b.thresholds.length) ? b.thresholds : [75, 90, 100];
      document.getElementById('bT1').value = th[0] != null ? th[0] : 75;
      document.getElementById('bT2').value = th[1] != null ? th[1] : 90;
      document.getElementById('bT3').value = th[2] != null ? th[2] : 100;
    } else {
      title.textContent = 'New budget';
      saveLabel.textContent = 'File budget';
      deleteBtn.hidden = true;
      kicker.textContent = '§ 04';
    }

    const m = document.getElementById('budgetModal');
    m.classList.add('is-on');
    m.setAttribute('aria-hidden', 'false');
    setTimeout(() => {
      const f = document.getElementById('bAmount');
      if (f) f.focus();
    }, 60);
  };

  window.closeBudgetModal = function () {
    const m = document.getElementById('budgetModal');
    if (!m) return;
    m.classList.remove('is-on');
    m.setAttribute('aria-hidden', 'true');
    editingId = null;
  };

  window.handleBudgetSave = async function (e) {
    e.preventDefault();
    const category = document.getElementById('bCategory').value;
    const amount = parseFloat(document.getElementById('bAmount').value);
    const periodType = document.getElementById('bPeriod').value;

    if (!category || !(amount > 0)) {
      showToast('Pick a category and enter a positive amount.', 'error');
      return;
    }

    let period;
    if (periodType === 'custom') {
      const s = document.getElementById('bStart').value;
      const en = document.getElementById('bEnd').value;
      if (!s || !en) {
        showToast('Custom range needs a valid start and end.', 'error');
        return;
      }
      if (new Date(s) > new Date(en)) {
        showToast('End date must be on or after start date.', 'error');
        return;
      }
      period = { type: 'custom', startDate: s, endDate: en };
    } else {
      period = { type: periodType };
    }

    const thresholds = [document.getElementById('bT1').value, document.getElementById('bT2').value, document.getElementById('bT3').value]
      .map((v) => parseFloat(v))
      .filter((n) => Number.isFinite(n) && n > 0 && n <= 100);
    if (thresholds.length === 0) thresholds.push(75, 90, 100);
    const dedup = Array.from(new Set(thresholds)).sort((a, b) => a - b);

    if (editingId) {
      await updateBudget(editingId, { category, amount, period, thresholds: dedup });
      showToast('Budget updated.', 'success');
    } else {
      await addBudget({ category, amount, period, thresholds: dedup });
      showToast('Budget saved.', 'success');
    }
    closeBudgetModal();
    render();
  };

  window.handleBudgetDelete = async function () {
    if (!editingId) return;
    if (!confirm('Delete this budget? This cannot be undone.')) return;
    await deleteBudget(editingId);
    showToast('Budget deleted.', 'info');
    closeBudgetModal();
    render();
  };

  // ==========================================================================
  // Event wiring
  // ==========================================================================
  function wireEvents() {
    // Period toggle
    document.querySelectorAll('.budgets-toolbar .period-toggle button').forEach((btn) => {
      btn.addEventListener('click', () => {
        document.querySelectorAll('.budgets-toolbar .period-toggle button').forEach((b) => b.classList.remove('is-on'));
        btn.classList.add('is-on');
        activePeriod = btn.getAttribute('data-bperiod') || 'monthly';
        render();
      });
    });

    // Modal scrim click + ESC
    const scrim = document.getElementById('budgetModal');
    if (scrim) {
      scrim.addEventListener('click', (e) => { if (e.target === scrim) closeBudgetModal(); });
    }
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') closeBudgetModal();
    });

    // Show/hide custom-range row when period type is 'custom'
    const periodSel = document.getElementById('bPeriod');
    if (periodSel) {
      periodSel.addEventListener('change', (e) => {
        document.getElementById('bCustomRange').hidden = e.target.value !== 'custom';
      });
    }
  }

  // ==========================================================================
  // Boot
  // ==========================================================================
  document.addEventListener('DOMContentLoaded', async () => {
    budgets = await fetchBudgets();
    render();
    wireEvents();
  });

})();
