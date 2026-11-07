/**
 * ApexSpend — Dashboard Engine (Dark Editorial)
 * Handles new transaction modal, ledger calculations, filter chips,
 * Chart.js initialization, and the GitHub-style spending heatmap.
 */

let currentType = 'expense';
let balance = 12845.50;
let income  = 8500.00;
let expense = 3214.50;

// ----- Modal Controls -----
function openModal() {
    const m = document.getElementById('txModal');
    if (!m) return;
    m.classList.add('is-on');
    m.setAttribute('aria-hidden', 'false');
    setTimeout(() => {
        const f = document.getElementById('txTitle');
        if (f) f.focus();
    }, 60);
}

function closeModal() {
    const m = document.getElementById('txModal');
    if (!m) return;
    m.classList.remove('is-on');
    m.setAttribute('aria-hidden', 'true');
}

function openGoalsModal() {
    const m = document.getElementById('goalsModal');
    if (!m) return;
    m.classList.add('is-on');
    m.setAttribute('aria-hidden', 'false');
    setTimeout(() => {
        const f = document.getElementById('goalSavings');
        if (f) f.focus();
    }, 60);
}

function closeGoalsModal() {
    const m = document.getElementById('goalsModal');
    if (!m) return;
    m.classList.remove('is-on');
    m.setAttribute('aria-hidden', 'true');
}

function setTxType(type) {
    currentType = type;
    const exp = document.getElementById('typeExpenseBtn');
    const inc = document.getElementById('typeIncomeBtn');
    if (!exp || !inc) return;
    if (type === 'expense') {
        exp.className = 'is-on expense';
        inc.className = 'income';
    } else {
        inc.className = 'is-on income';
        exp.className = 'expense';
    }
}

// ----- Toast Notification Helper -----
// Defined globally in script.js (loaded before app.js on every page) as
// window.ApexToast.show and window.showToast. No need to redefine here.

// ----- Add Transaction Handler -----
async function handleAddTransaction(e) {
    e.preventDefault();
    const title    = document.getElementById('txTitle').value.trim();
    const amount   = parseFloat(document.getElementById('txAmount').value);
    const category = document.getElementById('txCategory').value;
    const method   = document.getElementById('txMethod').value;
    const type     = currentType;
    const isExp    = currentType === 'expense';

    if (!title || isNaN(amount) || amount <= 0) {
        showToast('Enter a valid title and a positive amount.', 'error');
        return;
    }

    // Prepare data
    const formData = new FormData();
    formData.append('title', title);
    formData.append('amount', amount);
    formData.append('category', category);
    formData.append('method', method);
    formData.append('type', type);
    formData.append('_token', window.APEX_TOKEN || '');

    try {
        const response = await fetch('../api/api_add_transaction.php', {
            method: 'POST',
            body: formData
        });
        const result = await response.json();
        
        if (result.success) {
            showToast(`Filed “${title}” successfully. Reloading...`, 'success');
            setTimeout(() => location.reload(), 800);
        } else {
            showToast(`Failed: ${result.error}`, 'error');
        }
    } catch (err) {
        showToast('Error saving transaction.', 'error');
    }
}

async function handleUpdateGoals(e) {
    e.preventDefault();
    const savingsGoal = parseFloat(document.getElementById('goalSavings').value);
    const runRateTarget = parseFloat(document.getElementById('goalRunRate').value);

    if (isNaN(savingsGoal) || savingsGoal <= 0 || isNaN(runRateTarget) || runRateTarget <= 0) {
        showToast('Goals must be positive numbers.', 'error');
        return;
    }

    const formData = new FormData();
    formData.append('savings_goal', savingsGoal);
    formData.append('run_rate_target', runRateTarget);
    formData.append('_token', window.APEX_TOKEN || '');

    try {
        const response = await fetch('../api/api_update_goals.php', {
            method: 'POST',
            body: formData
        });
        const result = await response.json();
        
        if (result.success) {
            showToast('Goals updated successfully. Reloading...', 'success');
            setTimeout(() => location.reload(), 800);
        } else {
            showToast(`Failed to update goals: ${result.error}`, 'error');
        }
    } catch (err) {
        showToast('Error updating goals.', 'error');
    }
}

function categoryIcon(cat) {
    // Delegates to the single source of truth (category-helper.js).
    if (window.ApexCategories) return window.ApexCategories.icon(cat);
    return 'fa-tag';
}

function escapeHtml(s) {
    return String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
}

// ----- Indian Rupee formatter -----
// 1234567.50 → "12,34,567.50"  (lakh/crore grouping)
function formatINR(n) {
    const num = Number(n) || 0;
    const fixed = num.toFixed(2);
    const parts = fixed.split('.');
    const intPart = parts[0];
    const decPart = parts[1];

    if (intPart.length <= 3) return intPart + '.' + decPart;

    // First group = last 3 digits, then chunks of 2 working leftward.
    const last3 = intPart.slice(-3);
    const rest  = intPart.slice(0, -3);
    const grouped = rest.replace(/\B(?=(\d{2})+(?!\d))/g, ',');
    return grouped + ',' + last3 + '.' + decPart;
}

// ----- Wire Category Filters -----
function wireFilters() {
    const buttons = document.querySelectorAll('.filters button');
    buttons.forEach((btn) => {
        btn.addEventListener('click', () => {
            buttons.forEach((b) => b.classList.remove('is-on'));
            btn.classList.add('is-on');
            const filter = btn.getAttribute('data-filter');
            const rows = document.querySelectorAll('#ledgerBody .led-grid');
            rows.forEach((r) => {
                const cat = r.getAttribute('data-category') || '';
                if (filter === 'all' || cat === filter) {
                    r.style.display = '';
                } else {
                    r.style.display = 'none';
                }
            });
        });
    });
}

// ============================================================
// SPENDING HEATMAP — GitHub-style 12-month contribution grid
// ============================================================

// GitHub contribution greens — dark + light palettes (light = GitHub default greens)
const HEATMAP_LEVELS_DARK = ['#161b22', '#0e4429', '#006d32', '#26a641', '#39d353'];
const HEATMAP_LEVELS_LIGHT = ['#ebedf0', '#9be9a8', '#40c463', '#30a14e', '#216e39'];
function heatmapPalette() {
    return (document.documentElement.getAttribute('data-theme') === 'light')
        ? HEATMAP_LEVELS_LIGHT : HEATMAP_LEVELS_DARK;
}
const HEATMAP_LEVELS = HEATMAP_LEVELS_DARK;
const MONTH_NAMES = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

// In-memory day store: { 'YYYY-MM-DD': { total, count } }
const heatmapData = {};

// Generate the last ~12 months of plausible data so the heatmap looks lived-in.
function seedHeatmapData() {
    const memberFilter = (typeof window !== 'undefined' && window.heatmapMemberFilter) || 'all';
    if (typeof serverTransactions !== 'undefined' && Array.isArray(serverTransactions)) {
        serverTransactions.forEach(t => {
            if (t.type === 'expense') {
                if (memberFilter !== 'all' && String(t.user_id ?? '') !== String(memberFilter)) return;
                const d = new Date(t.date.replace(' ', 'T'));
                if (isNaN(d.getTime())) return;
                
                const yyyy = d.getFullYear();
                const mm   = String(d.getMonth() + 1).padStart(2, '0');
                const dd   = String(d.getDate()).padStart(2, '0');
                const key  = `${yyyy}-${mm}-${dd}`;
                
                if (!heatmapData[key]) heatmapData[key] = { total: 0, count: 0 };
                heatmapData[key].total += parseFloat(t.amount);
                heatmapData[key].count += 1;
            }
        });
    }
}

function isoDate(d) {
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${day}`;
}

function levelFor(total) {
    if (total <= 0)     return 0;
    if (total < 7500)   return 1;   // < ₹7,500
    if (total < 17500)  return 2;   // < ₹17,500
    if (total < 32500)  return 3;   // < ₹32,500
    return 4;                       // ₹32,500+
}

// Relative scaling (family page): levels spread across the actual max day,
// so small household amounts still show visible variation.
function levelForTotal(total) {
    if (total <= 0) return 0;
    if (typeof window !== 'undefined' && window.heatmapRelative) {
        let max = 0;
        for (const k in heatmapData) {
            if (heatmapData[k].total > max) max = heatmapData[k].total;
        }
        if (max <= 0) return 0;
        const r = total / max;
        if (r < 0.25) return 1;
        if (r < 0.50) return 2;
        if (r < 0.75) return 3;
        return 4;
    }
    return levelFor(total);
}

// Build the heatmap DOM — GitHub contribution graph layout:
//   rows = Sun→Sat, columns = weeks, left labels = Mon / Wed / Fri only
function buildHeatmap() {
    // Reset so member-filter switches and reloads never stack stale days.
    for (const k in heatmapData) delete heatmapData[k];
    seedHeatmapData();

    const grid   = document.getElementById('heatmapGrid');
    const months = document.getElementById('heatmapMonths');
    if (!grid || !months) return;

    grid.innerHTML = '';
    months.innerHTML = '';

    const end = new Date(); // Use actual current date
    const totalDays = 371;
    const start = new Date(end);
    start.setDate(start.getDate() - (totalDays - 1));

    // Align first column to Sunday (GitHub default week start)
    const firstColumnDate = new Date(start);
    firstColumnDate.setDate(firstColumnDate.getDate() - firstColumnDate.getDay());

    const diffDays = Math.floor((end - firstColumnDate) / 86400000) + 1;
    const cols = Math.ceil(diffDays / 7);
    const today = isoDate(end);

    const tipDateEl  = document.querySelector('#heatmapTip .tip-date');
    const tipOutEl   = document.querySelector('#heatmapTip .tip-row:nth-child(2) .tip-value');
    const tipCountEl = document.querySelector('#heatmapTip .tip-row:nth-child(3) .tip-value');

    // Track first column index for each month (for top labels)
    const monthAtCol = [];

    for (let c = 0; c < cols; c++) {
        const col = document.createElement('div');
        col.className = 'heatmap-col';
        col.setAttribute('role', 'rowgroup');

        let monthForCol = -1;

        for (let r = 0; r < 7; r++) {
            const cellDate = new Date(firstColumnDate);
            cellDate.setDate(firstColumnDate.getDate() + (c * 7) + r);

            // Out-of-range padding cells (still occupy the slot)
            if (cellDate < start || cellDate > end) {
                const blank = document.createElement('span');
                blank.className = 'hm-cell hm-blank';
                blank.setAttribute('aria-hidden', 'true');
                col.appendChild(blank);
                continue;
            }

            if (monthForCol < 0) monthForCol = cellDate.getMonth();

            const key   = isoDate(cellDate);
            const entry = heatmapData[key] || { total: 0, count: 0 };
            const lvl   = levelForTotal(entry.total);

            const cell = document.createElement('a');
            cell.className = 'hm-cell';
            cell.href = 'analytics.php?date=' + encodeURIComponent(key);
            cell.style.background = heatmapPalette()[lvl];
            cell.dataset.date  = key;
            cell.dataset.total = entry.total;
            cell.dataset.count = entry.count;
            cell.setAttribute('role', 'gridcell');

            if (key === today) cell.classList.add('is-today');

            cell.addEventListener('mouseenter', (ev) => showHeatmapTip(ev.currentTarget, tipDateEl, tipOutEl, tipCountEl));
            cell.addEventListener('mouseleave', hideHeatmapTip);
            cell.addEventListener('focus',      (ev) => showHeatmapTip(ev.currentTarget, tipDateEl, tipOutEl, tipCountEl));
            cell.addEventListener('blur',       hideHeatmapTip);
            cell.setAttribute(
                'aria-label',
                `${formatLongDate(cellDate)}, ${entry.total === 0 ? 'no outflows' : '₹' + formatINR(entry.total) + ' across ' + entry.count + (entry.count === 1 ? ' entry' : ' entries')}`
            );

            col.appendChild(cell);
        }

        monthAtCol[c] = monthForCol;
        grid.appendChild(col);
    }

    // Month labels: place over the first week that contains day 1 of that month
    // (or the first visible day of a month if the window starts mid-month)
    const monthLabelCols = [];
    let prevMonth = -1;
    for (let c = 0; c < cols; c++) {
        let labelMonth = -1;
        for (let r = 0; r < 7; r++) {
            const d = new Date(firstColumnDate);
            d.setDate(firstColumnDate.getDate() + (c * 7) + r);
            if (d < start || d > end) continue;
            if (d.getDate() === 1) {
                labelMonth = d.getMonth();
                break;
            }
        }
        // First column of the range: show its month even if not the 1st
        if (labelMonth < 0 && c === 0 && monthAtCol[0] >= 0) {
            labelMonth = monthAtCol[0];
        }
        if (labelMonth < 0 || labelMonth === prevMonth) continue;
        prevMonth = labelMonth;
        monthLabelCols.push({ col: c, month: labelMonth });
    }

    monthLabelCols.forEach(({ col, month }) => {
        const m = document.createElement('span');
        m.className = 'heatmap-month';
        m.dataset.col = String(col);
        m.textContent = MONTH_NAMES[month];
        months.appendChild(m);
    });

    // Position labels from measured column widths (full-width flex grid)
    positionHeatmapMonths();
    if (!window.__heatmapMonthResizeBound) {
        window.__heatmapMonthResizeBound = true;
        window.addEventListener('resize', () => {
            clearTimeout(window.__heatmapMonthResizeT);
            window.__heatmapMonthResizeT = setTimeout(positionHeatmapMonths, 80);
        });
    }
}

// Align month labels to week columns after layout / resize
function positionHeatmapMonths() {
    const grid   = document.getElementById('heatmapGrid');
    const months = document.getElementById('heatmapMonths');
    if (!grid || !months) return;

    const weekCols = grid.querySelectorAll('.heatmap-col');
    if (!weekCols.length) return;

    const gridLeft = grid.getBoundingClientRect().left;
    months.querySelectorAll('.heatmap-month').forEach((label) => {
        const idx = parseInt(label.dataset.col || '0', 10);
        const col = weekCols[idx];
        if (!col) return;
        const left = col.getBoundingClientRect().left - gridLeft;
        label.style.left = Math.max(0, left) + 'px';
    });
}

function formatLongDate(d) {
    return d.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' });
}

function showHeatmapTip(cell, tipDateEl, tipOutEl, tipCountEl) {
    const tip = document.getElementById('heatmapTip');
    if (!tip) return;

    const total = parseFloat(cell.dataset.total || '0');
    const count = parseInt(cell.dataset.count || '0', 10);
    const key   = cell.dataset.date;

    const date = new Date(key + 'T00:00:00');
    tipDateEl.textContent  = formatLongDate(date);
    tipOutEl.textContent   = total === 0 ? '—' : '− ₹' + formatINR(total);
    tipCountEl.textContent = count + (count === 1 ? ' entry' : ' entries');

    // Color the outflow number
    tipOutEl.style.color = total === 0 ? 'var(--whisper)' : 'var(--gain)';

    tip.setAttribute('aria-hidden', 'false');
    tip.style.opacity = '1';

    // Position above the cell (fixed coords = viewport)
    const rect = cell.getBoundingClientRect();
    const tipRect = tip.getBoundingClientRect();
    const left = rect.left + (rect.width / 2) - (tipRect.width / 2);
    const top  = rect.top - tipRect.height - 10;

    tip.style.left = Math.max(8, Math.min(left, window.innerWidth - tipRect.width - 8)) + 'px';
    tip.style.top  = Math.max(8, top) + 'px';
}

function hideHeatmapTip() {
    const tip = document.getElementById('heatmapTip');
    if (!tip) return;
    tip.style.opacity = '0';
    tip.setAttribute('aria-hidden', 'true');
}

// Called when a new transaction is filed — update today's cell.
function updateHeatmapDay(dateObj, addedAmount) {
    const key = isoDate(dateObj);
    const cell = document.querySelector(`.hm-cell[data-date="${key}"]`);
    if (!cell) return;
    const prevTotal = parseFloat(cell.dataset.total || '0');
    const prevCount = parseInt(cell.dataset.count || '0', 10);
    const newTotal  = prevTotal + addedAmount;
    const newCount  = prevCount + 1;
    cell.dataset.total = newTotal;
    cell.dataset.count = newCount;
    cell.style.background = heatmapPalette()[newTotal === 0 ? 0 : levelFor(newTotal)];
    heatmapData[key] = { total: newTotal, count: newCount };
}

// ----- Charts Initialization -----

function getCategoryData() {
    // Live category set: the user's expense categories (built-ins first), plus
    // any distinct expense category found in transactions that isn't in the list.
    const order = (window.ApexCategories ? window.ApexCategories.names(true) : []);
    const live = [];
    if (typeof serverTransactions !== 'undefined' && Array.isArray(serverTransactions)) {
        serverTransactions.forEach((t) => {
            if (t.type === 'expense' && t.category && !order.includes(t.category) && !live.includes(t.category)) {
                live.push(t.category);
            }
        });
    }
    let cats = order.concat(live);
    if (!cats.length) cats = ['Food & Dining', 'Housing & Utilities', 'Shopping & Retail', 'Transportation', 'Entertainment', 'Other'];

    const totals = cats.map(() => 0);
    let otherTotal = 0;

    if (typeof serverTransactions !== 'undefined' && Array.isArray(serverTransactions)) {
        serverTransactions.forEach((t) => {
            if (t.type !== 'expense') return;
            const idx = cats.indexOf(t.category);
            if (idx === -1) otherTotal += parseFloat(t.amount);
            else totals[idx] += parseFloat(t.amount);
        });
    }

    if (otherTotal > 0) {
        cats.push('Other');
        totals.push(otherTotal);
    }
    return { labels: cats, totals: totals };
}

function categoryColor(name) {
    if (window.ApexCategories) return window.ApexCategories.color(name);
    return '#f59e0b';
}

function getMonthlyData() {
    const labels = [];
    const income = [0, 0, 0, 0, 0, 0];
    const expense = [0, 0, 0, 0, 0, 0];
    const now = new Date();
    for (let i = 5; i >= 0; i--) {
        const d = new Date(now.getFullYear(), now.getMonth() - i, 1);
        labels.push(d.toLocaleString('default', { month: 'short' }));
    }
    if (typeof serverTransactions !== 'undefined' && Array.isArray(serverTransactions)) {
        serverTransactions.forEach(t => {
            const d = new Date(t.date.replace(' ', 'T'));
            if (isNaN(d.getTime())) return;
            const monthsDiff = (now.getFullYear() - d.getFullYear()) * 12 + (now.getMonth() - d.getMonth());
            if (monthsDiff >= 0 && monthsDiff < 6) {
                const index = 5 - monthsDiff;
                const amt = parseFloat(t.amount);
                if (t.type === 'income') income[index] += amt;
                else expense[index] += amt;
            }
        });
    }
    return { labels, income, expense };
}

function getYearlyData() {
    const labels = [];
    const income = [0, 0, 0, 0, 0];
    const expense = [0, 0, 0, 0, 0];
    const currentYear = new Date().getFullYear();
    for (let i = 4; i >= 0; i--) {
        labels.push(String(currentYear - i));
    }
    if (typeof serverTransactions !== 'undefined' && Array.isArray(serverTransactions)) {
        serverTransactions.forEach(t => {
            const d = new Date(t.date.replace(' ', 'T'));
            if (isNaN(d.getTime())) return;
            const yearDiff = currentYear - d.getFullYear();
            if (yearDiff >= 0 && yearDiff < 5) {
                const index = 4 - yearDiff;
                const amt = parseFloat(t.amount);
                if (t.type === 'income') income[index] += amt;
                else expense[index] += amt;
            }
        });
    }
    return { labels, income, expense };
}

function initCharts() {
    if (typeof Chart === 'undefined') return;

    function setChartEmptyState(canvasId, isEmpty, msg) {
        const canvas = document.getElementById(canvasId);
        if (!canvas) return;
        const parent = canvas.parentElement;
        if (isEmpty) {
            canvas.style.display = 'none';
            let emptyDiv = parent.querySelector('.chart-empty-state');
            if (!emptyDiv) {
                emptyDiv = document.createElement('div');
                emptyDiv.className = 'chart-empty-state';
                emptyDiv.style.cssText = 'display:flex;align-items:center;justify-content:center;height:100%;color:var(--whisper);text-align:center;padding:20px;font-family:var(--font-mono);font-size:0.9rem;position:absolute;top:0;left:0;right:0;bottom:0;';
                parent.style.position = 'relative';
                parent.appendChild(emptyDiv);
            }
            emptyDiv.textContent = msg;
            emptyDiv.style.display = 'flex';
        } else {
            canvas.style.display = 'block';
            const emptyDiv = parent.querySelector('.chart-empty-state');
            if (emptyDiv) emptyDiv.style.display = 'none';
        }
    }

    Chart.defaults.font.family = "'Geist Mono', ui-monospace, monospace";
    Chart.defaults.font.size = 11;
    Chart.defaults.color = '#8a8a8a';

    const monthlyData = getMonthlyData();
    const mTotal = monthlyData.income.reduce((a, b) => a + b, 0) + monthlyData.expense.reduce((a, b) => a + b, 0);

    const lineCtx = document.getElementById('incomeExpenseChart');
    if (lineCtx) {
        setChartEmptyState('incomeExpenseChart', mTotal === 0, 'No activity in the last 6 months.');
        if (mTotal > 0) {
            const ctx = lineCtx.getContext('2d');
    
            const grad1 = ctx.createLinearGradient(0, 0, 0, 260);
            grad1.addColorStop(0, 'rgba(245, 158, 11, 0.35)');
            grad1.addColorStop(1, 'rgba(245, 158, 11, 0)');
    
            const grad2 = ctx.createLinearGradient(0, 0, 0, 260);
            grad2.addColorStop(0, 'rgba(138, 138, 138, 0.25)');
            grad2.addColorStop(1, 'rgba(138, 138, 138, 0)');
    
            new Chart(lineCtx, {
            type: 'line',
            data: {
                labels: monthlyData.labels,
                datasets: [
                    {
                        label: 'Income',
                        data: monthlyData.income,
                        borderColor: '#f59e0b',
                        backgroundColor: grad1,
                        borderWidth: 2,
                        pointRadius: 3,
                        pointBackgroundColor: '#f59e0b',
                        pointBorderColor: '#0a0a0a',
                        pointBorderWidth: 2,
                        fill: true,
                        tension: 0.32
                    },
                    {
                        label: 'Expense',
                        data: monthlyData.expense,
                        borderColor: '#8a8a8a',
                        backgroundColor: grad2,
                        borderWidth: 2,
                        pointRadius: 3,
                        pointBackgroundColor: '#8a8a8a',
                        pointBorderColor: '#0a0a0a',
                        pointBorderWidth: 2,
                        borderDash: [4, 4],
                        fill: true,
                        tension: 0.32
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            boxWidth: 8,
                            boxHeight: 8,
                            usePointStyle: true,
                            font: { family: "'Geist Mono', monospace", size: 11 },
                            color: '#8a8a8a'
                        }
                    },
                    tooltip: {
                        backgroundColor: '#1a1a1a',
                        titleFont: { family: "'Geist Mono', monospace", size: 10 },
                        bodyFont: { family: "'Geist Mono', monospace", size: 12 },
                        padding: 10,
                        borderColor: '#2a2a2a',
                        borderWidth: 1,
                        displayColors: false,
                        callbacks: { label: (ctx) => `${ctx.dataset.label}: ₹${formatINR(ctx.parsed.y)}` }
                    }
                },
                scales: {
                    x: { grid: { display: false }, ticks: { color: '#8a8a8a', font: { family: "'Geist Mono', monospace", size: 10 } } },
                    y: {
                        grid: { color: 'rgba(255, 255, 255, 0.04)', drawTicks: false },
                        ticks: {
                            color: '#8a8a8a',
                            font: { family: "'Geist Mono', monospace", size: 10 },
                            callback: (v) => {
                                if (v >= 100000) return '₹' + Math.round(v / 100000) + 'L';
                                if (v >= 1000)    return '₹' + Math.round(v / 1000)    + 'k';
                                return '₹' + v;
                            }
                        }
                    }
                }
            }
        });
        }
    }

    const donutCtx = document.getElementById('categoryChart');
    if (donutCtx) {
        const cd = getCategoryData();
        const cData = cd.totals;
        const cLabels = cd.labels;
        const cTotal = cData.reduce((a, b) => a + b, 0);
        setChartEmptyState('categoryChart', cTotal === 0, 'No expense data to categorize.');

        if (cTotal > 0) {
            new Chart(donutCtx, {
                type: 'doughnut',
                data: {
                    labels: cLabels,
                    datasets: [{
                        data: cData,
                    backgroundColor: cLabels.map((n) => categoryColor(n)),
                    borderColor: '#0a0a0a',
                    borderWidth: 2,
                    hoverOffset: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '68%',
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#1a1a1a',
                        titleFont: { family: "'Geist Mono', monospace", size: 10 },
                        bodyFont: { family: "'Geist Mono', monospace", size: 12 },
                        padding: 10,
                        borderColor: '#2a2a2a',
                        borderWidth: 1,
                        displayColors: false,
                        callbacks: { label: (ctx) => `${ctx.label}: ₹${formatINR(ctx.parsed)}` }
                    }
                }
            }
        });

        const legend = document.getElementById('categoryLegend');
        if (legend) {
            legend.innerHTML = '';
            cLabels.forEach((c, i) => {
                const item = document.createElement('span');
                item.className = 'item';
                item.innerHTML = `<span class="swatch" style="background:${categoryColor(c)}"></span>${escapeHtml(c)}`;
                legend.appendChild(item);
            });
        }
        }
    }
}

// ============================================================
// GROUPED BAR CHART — Income vs Expense with Monthly/Yearly toggle
// ============================================================

const monthlyData = getMonthlyData();
const yearlyData = getYearlyData();

const BAR_DATA = {
    monthly: {
        labels: monthlyData.labels,
        income:  monthlyData.income,
        expense: monthlyData.expense
    },
    yearly: {
        labels: yearlyData.labels,
        income:  yearlyData.income,
        expense: yearlyData.expense
    }
};

let barChart = null;

function initBarChart() {
    const el = document.getElementById('barIncomeExpenseChart');
    if (!el || typeof Chart === 'undefined') return;

    function setChartEmptyState(canvasId, isEmpty, msg) {
        const canvas = document.getElementById(canvasId);
        if (!canvas) return;
        const parent = canvas.parentElement;
        if (isEmpty) {
            canvas.style.display = 'none';
            let emptyDiv = parent.querySelector('.chart-empty-state');
            if (!emptyDiv) {
                emptyDiv = document.createElement('div');
                emptyDiv.className = 'chart-empty-state';
                emptyDiv.style.cssText = 'display:flex;align-items:center;justify-content:center;height:100%;color:var(--whisper);text-align:center;padding:20px;font-family:var(--font-mono);font-size:0.9rem;position:absolute;top:0;left:0;right:0;bottom:0;';
                parent.style.position = 'relative';
                parent.appendChild(emptyDiv);
            }
            emptyDiv.textContent = msg;
            emptyDiv.style.display = 'flex';
        } else {
            canvas.style.display = 'block';
            const emptyDiv = parent.querySelector('.chart-empty-state');
            if (emptyDiv) emptyDiv.style.display = 'none';
        }
    }

    const d = BAR_DATA.monthly;
    const mTotal = d.income.reduce((a,b)=>a+b, 0) + d.expense.reduce((a,b)=>a+b, 0);
    setChartEmptyState('barIncomeExpenseChart', mTotal === 0, 'No income or expense data found.');

    if (mTotal === 0) return;

    barChart = new Chart(el, {
        type: 'bar',
        data: {
            labels: BAR_DATA.monthly.labels.slice(),
            datasets: [
                {
                    label: 'Income',
                    data: BAR_DATA.monthly.income.slice(),
                    backgroundColor: '#f59e0b',
                    borderColor: '#0a0a0a',
                    borderWidth: 1,
                    borderRadius: 2,
                    barPercentage: 0.78,
                    categoryPercentage: 0.72
                },
                {
                    label: 'Expense',
                    data: BAR_DATA.monthly.expense.slice(),
                    backgroundColor: '#8a8a8a',
                    borderColor: '#0a0a0a',
                    borderWidth: 1,
                    borderRadius: 2,
                    barPercentage: 0.78,
                    categoryPercentage: 0.72
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        boxWidth: 8,
                        boxHeight: 8,
                        usePointStyle: true,
                        font: { family: "'Geist Mono', monospace", size: 11 },
                        color: '#8a8a8a'
                    }
                },
                tooltip: {
                    backgroundColor: '#1a1a1a',
                    titleFont: { family: "'Geist Mono', monospace", size: 10 },
                    bodyFont: { family: "'Geist Mono', monospace", size: 12 },
                    padding: 10,
                    borderColor: '#2a2a2a',
                    borderWidth: 1,
                    displayColors: false,
                    callbacks: { label: (c) => `${c.dataset.label}: ₹${formatINR(c.parsed.y)}` }
                }
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: { color: '#8a8a8a', font: { family: "'Geist Mono', monospace", size: 10 } }
                },
                y: {
                    grid: { color: 'rgba(255, 255, 255, 0.04)', drawTicks: false },
                    ticks: {
                        color: '#8a8a8a',
                        font: { family: "'Geist Mono', monospace", size: 10 },
                        callback: (v) => {
                            if (v >= 10000000) return '₹' + (v / 10000000).toFixed(1) + 'Cr';
                            if (v >= 100000)   return '₹' + Math.round(v / 100000) + 'L';
                            if (v >= 1000)      return '₹' + Math.round(v / 1000)    + 'k';
                            return '₹' + v;
                        }
                    }
                }
            }
        }
    });
}

function setBarPeriod(period) {
    if (!barChart || !BAR_DATA[period]) return;
    const d = BAR_DATA[period];
    barChart.data.labels = d.labels.slice();
    barChart.data.datasets[0].data = d.income.slice();
    barChart.data.datasets[1].data = d.expense.slice();
    barChart.update();

    const title = document.getElementById('barChartTitle');
    if (title) {
        title.textContent = period === 'monthly'
            ? 'Income vs Expense · last 6 months'
            : 'Income vs Expense · last 5 years';
    }
}

function wireBarToggle() {
    const buttons = document.querySelectorAll('.period-toggle button');
    buttons.forEach((btn) => {
        btn.addEventListener('click', () => {
            buttons.forEach((b) => b.classList.remove('is-on'));
            btn.classList.add('is-on');
            setBarPeriod(btn.getAttribute('data-period'));
        });
    });
}

async function renderDashboardBudgets() {
    const grid = document.getElementById('dashboardBudgetsGrid');
    if (!grid) return;

    let store = null;
    try {
        const response = await fetch('../api/api_get_budgets.php');
        const data = await response.json();
        if (data.success) {
            store = { items: data.items };
        }
    } catch (e) {
        console.error('Failed to fetch budgets:', e);
    }
    
    if (!store || !store.items || store.items.length === 0) {
        grid.innerHTML = '<p style="padding:20px;color:var(--whisper);">No budgets set. <a href="budgets.php" style="color:var(--foreground);">Create one →</a></p>';
        return;
    }

    const now = new Date();
    const currentMonth = now.getMonth();
    const currentYear = now.getFullYear();
    const cards = [];

    const displayBudgets = store.items.slice(0, 5);

    displayBudgets.forEach(b => {
        let spent = 0;
        if (typeof serverTransactions !== 'undefined') {
            serverTransactions.forEach(t => {
                if (t.type === 'expense' && t.category === b.category) {
                    const d = new Date(t.date.replace(' ', 'T'));
                    if (!isNaN(d.getTime()) && d.getMonth() === currentMonth && d.getFullYear() === currentYear) {
                        spent += parseFloat(t.amount);
                    }
                }
            });
        }

        const pct = b.amount > 0 ? (spent / b.amount) * 100 : 0;
        const displayPct = Math.min(100, pct);
        const over = spent > b.amount;
        const fillCls = over ? 'warn' : '';
        const labelCls = over ? 'warn' : '';
        const icon = categoryIcon(b.category);

        cards.push(`
            <div class="budget">
                <div class="budget-head">
                    <div class="budget-cat">
                        <i class="fa-solid ${icon}" aria-hidden="true"></i>
                        <span>${escapeHtml(b.category)}</span>
                    </div>
                    <div class="budget-figures">
                        <span class="budget-spent">₹${formatINR(spent)}</span>
                        <span class="budget-of">of ₹${formatINR(b.amount)}</span>
                    </div>
                </div>
                <div class="progress large">
                    <div class="progress-track">
                        <div class="progress-fill ${fillCls}" style="--w: ${displayPct.toFixed(1)}%"></div>
                    </div>
                    <span class="progress-label ${labelCls}">${pct.toFixed(0)}% ${over ? '· over budget' : 'used'}</span>
                </div>
            </div>
        `);
    });

    grid.innerHTML = cards.join('');
}

document.addEventListener('DOMContentLoaded', () => {
    wireFilters();
    buildHeatmap();
    initCharts();
    initBarChart();
    wireBarToggle();
    renderDashboardBudgets();

    // Repaint heatmap cells when the theme changes (dark <-> light).
    window.addEventListener('apex-theme-change', () => {
        const pal = heatmapPalette();
        document.querySelectorAll('.hm-cell[data-date]').forEach((cell) => {
            const total = parseFloat(cell.dataset.total || '0');
            cell.style.background = pal[total === 0 ? 0 : levelFor(total)];
        });
    });

    const scrim = document.getElementById('txModal');
    if (scrim) {
        scrim.addEventListener('click', (e) => {
            if (e.target === scrim) closeModal();
        });
    }

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') closeModal();
    });
});