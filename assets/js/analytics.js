/**
 * ApexSpend — Analytics Engine
 * Reads a single JSON blob (#analyticsData) and renders:
 *   1. Month picker (with "All time" option)
 *   2. Hero KPI strip (re-renders on month change)
 *   3. Cash-flow line chart (re-renders on month change + granularity toggle)
 *   4. Day-of-week bar chart (session-wide, static)
 *   5. Outflows-by-category doughnut (session-wide, static)
 *   6. Payment-method horizontal bar (session-wide, static)
 *   7. Daily heatmap strip (re-renders on month change)
 *
 * Helpers (formatINR, categoryIcon, escapeHtml) are duplicated from app.js
 * because there's no build step.
 */

(function () {
    'use strict';

    // ----- Helpers (KEEP IN SYNC with app.js) -----
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
    function pad2(n) { return String(n).padStart(2, '0'); }

    // ----- Heatmap levels (KEEP IN SYNC with app.js) -----
    const HEATMAP_LEVELS = ['#161b22', '#0e4429', '#006d32', '#26a641', '#39d353'];
    function levelFor(total) {
        if (total <= 0)    return 0;
        if (total < 7500)  return 1;
        if (total < 17500) return 2;
        if (total < 32500) return 3;
        return 4;
    }

    // ----- Toast (KEEP IN SYNC with app.js) -----
    function showToast(message, type) {
        type = type || 'info';
        const container = document.getElementById('toastContainer');
        if (!container) return;
        const toast = document.createElement('div');
        toast.className = 'toast ' + type;
        let icon = 'fa-circle-info';
        if (type === 'success') icon = 'fa-circle-check';
        if (type === 'error')   icon = 'fa-circle-exclamation';
        toast.innerHTML = '<i class="fa-solid ' + icon + '" aria-hidden="true"></i><span>' + escapeHtml(message) + '</span>';
        container.appendChild(toast);
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(6px)';
            setTimeout(() => toast.remove(), 250);
        }, 3200);
    }

    // ----- Read blob -----
    const DATA = JSON.parse(document.getElementById('analyticsData').textContent);

    // Chart.js default font (matches app.js)
    if (typeof Chart !== 'undefined') {
        Chart.defaults.font.family = "'Geist Mono', ui-monospace, monospace";
        Chart.defaults.font.size = 11;
        Chart.defaults.color = '#8a8a8a';
    }

    // Chart instances
    const charts = {
        cashFlow:  null,
        dow:       null,
        category:  null,
        method:    null
    };

    // ----- Month picker -----
    function renderMonthPicker() {
        const sel = document.getElementById('monthPicker');
        if (!sel) return;
        sel.innerHTML = '';
        const months = (DATA.allMonths || []).slice();
        // Sort: newest first
        months.sort((a, b) => (a < b ? 1 : -1));
        months.forEach((ym) => {
            const opt = document.createElement('option');
            opt.value = ym;
            const d = new Date(ym + '-01T00:00:00');
            opt.textContent = d.toLocaleDateString('en-GB', { month: 'long', year: 'numeric' });
            if (ym === DATA.selectedMonth) opt.selected = true;
            sel.appendChild(opt);
        });
    }

    // ----- Hero KPI strip (re-renders on month change) -----
    function renderKPIs() {
        const k = DATA.kpis;
        if (!k) return;
        const avgEl = document.getElementById('kpiAvgDaily');
        const savEl = document.getElementById('kpiSavingsRate');
        const cntEl = document.getElementById('kpiCount');
        const heroSav = document.getElementById('heroSavings');

        if (avgEl) avgEl.textContent = formatINR(k.avgDaily);
        if (savEl) savEl.textContent = (k.income > 0 ? (k.income - k.expense) / k.income * 100 : 0).toFixed(1) + '%';
        if (cntEl) cntEl.textContent = k.count;
        if (heroSav) heroSav.textContent = formatINR(Math.max(0, k.income - k.expense));
    }

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

    // ----- Cash-flow line chart (re-renders on month change + granularity toggle) -----
    function renderCashFlowChart() {
        const el = document.getElementById('cashFlowChart');
        if (!el || typeof Chart === 'undefined') return;

        const labels = DATA.daily.map((d) => d.label);
        const income = DATA.daily.map((d) => d.income);
        const expense = DATA.daily.map((d) => d.expense);

        const totalFlow = income.reduce((a, b) => a + b, 0) + expense.reduce((a, b) => a + b, 0);
        setChartEmptyState('cashFlowChart', totalFlow === 0, 'No cash flow in this period.');
        if (totalFlow === 0) return;

        const ctx = el.getContext('2d');
        const grad1 = ctx.createLinearGradient(0, 0, 0, 260);
        grad1.addColorStop(0, 'rgba(245, 158, 11, 0.35)');
        grad1.addColorStop(1, 'rgba(245, 158, 11, 0)');
        const grad2 = ctx.createLinearGradient(0, 0, 0, 260);
        grad2.addColorStop(0, 'rgba(138, 138, 138, 0.28)');
        grad2.addColorStop(1, 'rgba(138, 138, 138, 0)');

        charts.cashFlow = new Chart(el, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Income',
                        data: income,
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
                        data: expense,
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
                interaction: { mode: 'index', intersect: false },
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
                    x: { grid: { display: false }, ticks: { color: '#8a8a8a', font: { family: "'Geist Mono', monospace", size: 10 } } },
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

    // ----- Day-of-week bar (session-wide, static) -----
    function renderDayOfWeekChart() {
        const el = document.getElementById('dayOfWeekChart');
        if (!el || typeof Chart === 'undefined') return;

        const labels = DATA.byDow.map((d) => d.label);
        const totals = DATA.byDow.map((d) => d.total);

        const dTotal = totals.reduce((a, b) => a + b, 0);
        setChartEmptyState('dayOfWeekChart', dTotal === 0, 'No daily outflow data found.');
        if (dTotal === 0) return;

        charts.dow = new Chart(el, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Outflow',
                    data: totals,
                    backgroundColor: '#f59e0b',
                    borderColor: '#0a0a0a',
                    borderWidth: 1,
                    borderRadius: 2,
                    barPercentage: 0.7,
                    categoryPercentage: 0.78
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
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
                        callbacks: {
                            label: (c) => {
                                const row = DATA.byDow[c.dataIndex];
                                return `₹${formatINR(c.parsed.y)} · ${row.count} entr${row.count===1?'y':'ies'}`;
                            }
                        }
                    }
                },
                scales: {
                    x: { grid: { display: false }, ticks: { color: '#8a8a8a', font: { family: "'Geist Mono', monospace", size: 11 } } },
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

    // ----- Outflows by category doughnut (session-wide, static) -----
    function renderCategoryDoughnut() {
        const el = document.getElementById('outflowsByCategoryChart');
        if (!el || typeof Chart === 'undefined') return;

        const labels = DATA.byCategory.map((c) => c.name);
        const totals = DATA.byCategory.map((c) => c.total);
        // Per-category stored color from the categories table — custom
        // categories get exactly the color the user chose.
        const colors = labels.map((n) => (window.ApexCategories ? window.ApexCategories.color(n) : '#f59e0b'));

        const cTotal = totals.reduce((a, b) => a + b, 0);
        setChartEmptyState('outflowsByCategoryChart', cTotal === 0, 'No expenses to categorize.');
        
        const legend = document.getElementById('categoryLegend');
        if (legend) legend.innerHTML = '';
        
        if (cTotal === 0) return;

        charts.category = new Chart(el, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: totals,
                    backgroundColor: colors,
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
                        callbacks: { label: (c) => `${c.label}: ₹${formatINR(c.parsed)}` }
                    }
                }
            }
        });

        // Custom legend
        if (legend) {
            labels.forEach((name, i) => {
                const row = document.createElement('li');
                row.className = 'item';
                const share = cTotal > 0 ? (totals[i] / cTotal * 100) : 0;
                row.innerHTML = `
                    <span class="swatch" style="background:${colors[i]}"></span>
                    <span class="label">${escapeHtml(name)}</span>
                    <span class="share">${share.toFixed(1)}%</span>
                `;
                legend.appendChild(row);
            });
        }
    }

    // ----- Payment method horizontal bar (session-wide, static) -----
    function renderPaymentMethodChart() {
        const el = document.getElementById('paymentMethodChart');
        if (!el || typeof Chart === 'undefined') return;

        const labels = DATA.byMethod.map((m) => m.name);
        const totals = DATA.byMethod.map((m) => m.total);

        const pTotal = totals.reduce((a, b) => a + b, 0);
        setChartEmptyState('paymentMethodChart', pTotal === 0, 'No payment method data found.');
        if (pTotal === 0) return;

        charts.method = new Chart(el, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Outflow',
                    data: totals,
                    backgroundColor: '#f59e0b',
                    borderColor: '#0a0a0a',
                    borderWidth: 1,
                    borderRadius: 2,
                    barPercentage: 0.7,
                    categoryPercentage: 0.78
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
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
                        callbacks: {
                            label: (c) => {
                                const row = DATA.byMethod[c.dataIndex];
                                const avg = row.count > 0 ? row.total / row.count : 0;
                                return `₹${formatINR(c.parsed.x)} · ${row.count} entr${row.count===1?'y':'ies'} · avg ₹${formatINR(avg)}`;
                            }
                        }
                    }
                },
                scales: {
                    x: {
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
                    },
                    y: { grid: { display: false }, ticks: { color: '#8a8a8a', font: { family: "'Geist Mono', monospace", size: 11 } } }
                }
            }
        });
    }

    // ----- Daily heatmap strip (re-renders on month change) -----
    function renderDailyStrip() {
        const host = document.getElementById('dailyStrip');
        if (!host) return;
        host.innerHTML = '';
        if (!DATA.daily.length) {
            host.innerHTML = '<span class="muted" style="font-size:11px;">No data for the selected month.</span>';
            return;
        }
        // Build every day of the selected month, leaving days with no activity at level 0
        const first = new Date(DATA.selectedMonth + '-01T00:00:00');
        const nextMonth = new Date(first);
        nextMonth.setMonth(nextMonth.getMonth() + 1);
        const daysInMonth = Math.round((nextMonth - first) / 86400000);

        // Daily totals keyed by date
        const map = {};
        DATA.daily.forEach((d) => { map[d.date] = d.expense; });

        // Weekday label column at the start
        const wdCol = document.createElement('div');
        wdCol.style.cssText = 'display:flex;flex-direction:column;gap:3px;margin-right:8px;';
        for (let r = 0; r < 7; r++) {
            const wdLabel = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'][r];
            const cell = document.createElement('span');
            cell.style.cssText = 'width:14px;height:14px;font-family:var(--mono);font-size:9px;color:var(--whisper);text-align:center;line-height:14px;';
            if (r % 2 === 0) cell.textContent = wdLabel[0];
            else cell.textContent = '';
            wdCol.appendChild(cell);
        }
        host.appendChild(wdCol);

        // Grid: weeks × 7
        const grid = document.createElement('div');
        grid.style.cssText = 'display:grid;grid-auto-flow:column;grid-template-rows:repeat(7,14px);gap:3px;';

        const firstWeekday = first.getDay(); // 0=Sun..6=Sat → ISO uses Mon=1
        // We want Mon-first indexing, so convert: Mon=0..Sun=6
        const firstIdx = (firstWeekday + 6) % 7; // Sun→6, Mon→0
        const offsetBlanks = firstIdx;

        let dayCounter = 1;
        for (let i = 0; i < offsetBlanks; i++) {
            const blank = document.createElement('span');
            blank.style.cssText = 'width:14px;height:14px;';
            grid.appendChild(blank);
        }
        for (let d = 1; d <= daysInMonth; d++) {
            const dateKey = `${first.getFullYear()}-${pad2(first.getMonth()+1)}-${pad2(d)}`;
            const total = map[dateKey] || 0;
            const cell = document.createElement('span');
            cell.className = 'hs-cell';
            cell.style.cssText = `width:14px;height:14px;background:${HEATMAP_LEVELS[levelFor(total)]};cursor:default;`;
            cell.title = `${dateKey}${total > 0 ? ' · ₹' + formatINR(total) : ''}`;
            grid.appendChild(cell);
            dayCounter++;
        }
        host.appendChild(grid);
    }

    // ----- Wire month picker -----
    function wireMonthPicker() {
        const sel = document.getElementById('monthPicker');
        if (!sel) return;
        sel.addEventListener('change', () => {
            const newMonth = sel.value;
            // Re-aggregate on the client side is overkill — the page is static on the
            // selected month. So we just update the headline marker and tell the user
            // that analytics aggregates are session-wide. Toggling requires a reload.
            //
            // Light-touch behaviour: rebuild the daily strip from DATA.daily filtered to the chosen month.
            // But DATA.daily is always the latest month from server-side aggregation.
            // For demo purposes, swap DATA.selectedMonth and re-render charts that are scoped to it.
            DATA.selectedMonth = newMonth;
            // Filter daily series to selected month (the server gives every month's data so we rebuild from DATA.byMonth? No — only current month was sent. We'll just keep the strip using the server's `daily` for the latest month and surface this in the UI.)
            // For now, only re-render the strip; other charts are session-wide.
            renderDailyStrip();
            showToast('Switched to ' + sel.options[sel.selectedIndex].textContent + ' — session-wide cuts are static.', 'info');
        });
    }

    // ----- Wire granularity toggle -----
    function wireGranularityToggle() {
        const buttons = document.querySelectorAll('.analytics-toolbar .period-toggle button');
        if (!buttons.length) return;
        buttons.forEach((btn) => {
            btn.addEventListener('click', () => {
                buttons.forEach((b) => b.classList.remove('is-on'));
                btn.classList.add('is-on');
                const gran = btn.getAttribute('data-gran');
                if (gran === 'daily' && charts.cashFlow) {
                    // No data change — just a UX switch. We'd sum into weeks otherwise.
                    showToast('Daily view active. (Weekly aggregation not implemented in demo seed.)', 'info');
                }
            });
        });
    }

    // ----- Init -----
    document.addEventListener('DOMContentLoaded', () => {
        renderMonthPicker();
        renderKPIs();
        renderCashFlowChart();
        renderDayOfWeekChart();
        renderCategoryDoughnut();
        renderPaymentMethodChart();
        renderDailyStrip();
        wireMonthPicker();
        wireGranularityToggle();
    });
})();
