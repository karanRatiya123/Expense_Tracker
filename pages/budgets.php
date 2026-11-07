<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/categories.php';

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$user     = $_SESSION['user'];
$initials = strtoupper(substr($user['name'], 0, 1));
$activePage = 'budgets';

function format_inr($n) {
    return number_format((float)$n, 2, '.', ',');
}

$pdo = getDBConnection();
$stmt = $pdo->prepare("SELECT * FROM transactions WHERE user_id = ? ORDER BY date DESC");
$stmt->execute([$user['id']]);
$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Categories from the DB (auto-migrates + seeds) — feeds payload + modals.
apex_load_category_registry($pdo, $user['id']);
$apexCategories = apex_get_categories($pdo, $user['id']);
$catIconMap = [];
$catColorMap = [];
foreach ($apexCategories as $c) {
    $catIconMap[$c['name']] = $c['icon'];
    $catColorMap[$c['name']] = $c['color'];
}

// =========================================================================
// Build JSON payload for budgets.js
// =========================================================================
$payload = [
    'currency' => 'INR',
    'count'    => count($transactions),
    'transactions' => array_map(fn($t) => [
        'category' => $t['category'],
        'type'     => $t['type'],
        'amount'   => (float)$t['amount'],
        'date'     => $t['date'],
    ], $transactions),
    'categories' => $catIconMap,
    'categoryColors' => $catColorMap,
    'demoDate' => date('Y-m-d'),
];

$jsonPayload = json_encode(
    $payload,
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="dark light">
    <script>(function(){try{var m=localStorage.getItem('apex-theme')||'dark';var r=m==='system'?(window.matchMedia('(prefers-color-scheme: light)').matches?'light':'dark'):m;document.documentElement.setAttribute('data-theme',r);document.documentElement.setAttribute('data-theme-mode',m);}catch(e){document.documentElement.setAttribute('data-theme','dark');}})();</script>
    <title>ApexSpend — Budgets</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=Geist+Mono:wght@400;500&family=Geist:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css?v=<?php echo time(); ?>">
    <script src="../assets/js/theme.js?v=<?php echo time(); ?>"></script>
</head>
<body>

<div class="app">

    <!-- =================== SIDEBAR =================== -->
    <aside class="side">
        <div class="side-top">
            <div class="brand">
                <span class="brand-mark">ApexSpend</span>
                <span class="brand-dot" aria-hidden="true"></span>
            </div>

            <div class="filing">
                <span class="num">VOL. 04 · NO. 209</span>
                <span class="dot" aria-hidden="true"></span>
                <span><?php echo strtoupper(date('D · d M Y')); ?></span>
            </div>

            <ul class="nav" role="navigation">
                <li><a href="dashboard.php" class="<?php echo $activePage==='overview' ? 'is-active' : ''; ?>">
                    <i class="fa-solid fa-house nav-ic" aria-hidden="true"></i>
                    <span class="label">Overview</span>
                </a></li>
                <li><a href="transactions.php" class="<?php echo $activePage==='ledger' ? 'is-active' : ''; ?>">
                    <i class="fa-solid fa-receipt nav-ic" aria-hidden="true"></i>
                    <span class="label">Ledger</span>
                </a></li>
                <li><a href="analytics.php" class="<?php echo $activePage==='analytics' ? 'is-active' : ''; ?>">
                    <i class="fa-solid fa-chart-pie nav-ic" aria-hidden="true"></i>
                    <span class="label">Analytics</span>
                </a></li>
                <li><a href="budgets.php" class="<?php echo $activePage==='budgets' ? 'is-active' : ''; ?>">
                    <i class="fa-solid fa-bullseye nav-ic" aria-hidden="true"></i>
                    <span class="label">Budgets</span>
                </a></li>
                <li><a href="categories.php" class="<?php echo $activePage==='categories' ? 'is-active' : ''; ?>">
                    <i class="fa-solid fa-tags nav-ic" aria-hidden="true"></i>
                    <span class="label">Categories</span>
                </a></li>
                <li><a href="family.php" class="<?php echo $activePage==='family' ? 'is-active' : ''; ?>">
                    <i class="fa-solid fa-users nav-ic" aria-hidden="true"></i>
                    <span class="label">Family <span class="lock-tag" title="Locked"><i class="fa-solid fa-lock" aria-hidden="true"></i></span></span>
                </a></li>
                <li><a href="profile.php" class="<?php echo $activePage==='settings' || $activePage==='profile' ? 'is-active' : ''; ?>">
                    <i class="fa-solid fa-user nav-ic" aria-hidden="true"></i>
                    <span class="label">Profile</span>
                </a></li>
            </ul>
        </div>

        <div class="who">
            <a href="profile.php" class="avatar" aria-label="Open profile"><?php echo htmlspecialchars($initials); ?></a>
            <div class="who-meta">
                <strong><a href="profile.php"><?php echo htmlspecialchars($user['name']); ?></a></strong>
                <span><?php echo htmlspecialchars($user['email']); ?></span>
            </div>
            <button type="button" class="logout" onclick="window.location.href='logout.php'" aria-label="Sign out">
                <i class="fa-solid fa-arrow-right-from-bracket" aria-hidden="true"></i>
            </button>
        </div>
    </aside>

    <!-- =================== MAIN CANVAS =================== -->
    <main class="canvas">

        <!-- =================== MASTHEAD =================== -->
        <header class="masthead">
            <div class="masthead-col">
                <div class="kicker">
                    <span class="kicker-num">§ 04</span>
                    <span class="kicker-text">The Spend Ceiling</span>
                </div>
                <h1>What you allow<br><em>yourself to spend.</em></h1>
                <p class="standfirst">A ceiling, a thermometer, and a trip-wire for each category — set monthly, weekly, or any window you like.</p>
            </div>

            <div class="masthead-figure">
                <div class="figure-label">
                    Total monthly limit
                    <span class="kicker-num" style="margin-left:8px;" id="heroPeriodLabel">Monthly</span>
                </div>
                <div class="figure-value">
                    <span class="currency">₹</span><span id="heroAllocated">—</span>
                </div>
                <div class="figure-meta">
                    <span class="muted">Spent </span>
                    <span class="mono" id="heroSpent">—</span>
                    <span class="muted">· Remaining </span>
                    <span class="mono" id="heroRemaining">—</span>
                </div>
                <div class="actions">
                    <button type="button" class="add" onclick="openBudgetModal()">
                        <i class="fa-solid fa-plus" aria-hidden="true"></i>
                        <span>Set new budget</span>
                    </button>
                </div>
            </div>
        </header>

        <!-- =================== TOOLBAR =================== -->
        <section class="budgets-toolbar" aria-label="Period selector">
            <div class="toolbar-left">
                <span class="toolbar-label">Period</span>
                <div class="period-toggle" role="group" aria-label="Period">
                    <button type="button" class="is-on" data-bperiod="monthly">Monthly</button>
                    <button type="button" data-bperiod="weekly">Weekly</button>
                    <button type="button" data-bperiod="custom">Custom</button>
                </div>
                <span class="kicker-num" id="budgetCount" style="margin-left:8px;">0 budgets</span>
            </div>
            <div class="toolbar-right">
                <span class="kicker-num" id="budgetUpdated">Live</span>
            </div>
        </section>

        <!-- =================== HERO KPI STRIP (5-up) =================== -->
        <section class="kpi variant-five" id="budgetKpis" aria-label="Budget overview">
            <div class="cell">
                <div class="cell-num">01</div>
                <div class="label">Total budgeted</div>
                <div class="figure"><span class="currency">₹</span><span id="kpiBudgeted">—</span></div>
                <div class="meta"><span class="muted">across all categories</span></div>
            </div>
            <div class="cell">
                <div class="cell-num">02</div>
                <div class="label">Total spent</div>
                <div class="figure"><span class="currency">₹</span><span id="kpiSpent">—</span></div>
                <div class="meta"><span class="muted" id="kpiSpentPct">—</span></div>
            </div>
            <div class="cell">
                <div class="cell-num">03</div>
                <div class="label">Remaining</div>
                <div class="figure"><span class="currency">₹</span><span id="kpiRemaining">—</span></div>
                <div class="meta"><span class="muted" id="kpiRemainingPct">—</span></div>
            </div>
            <div class="cell">
                <div class="cell-num">04</div>
                <div class="label">Over budget</div>
                <div class="figure" id="kpiOver">0</div>
                <div class="meta"><span class="muted">categories past 100%</span></div>
            </div>
            <div class="cell">
                <div class="cell-num">05</div>
                <div class="label">On track</div>
                <div class="figure" id="kpiOnTrack">0</div>
                <div class="meta"><span class="muted">under 75% used</span></div>
            </div>
        </section>

        <!-- =================== BUDGET LEDGER =================== -->
        <section class="section">
            <div class="section-head">
                <h2><i class="fa-solid fa-bullseye section-ic" aria-hidden="true"></i> Budget ledger.</h2>
                <div class="section-meta">
                    <span id="budgetGridMeta">Live</span>
                    <span aria-hidden="true">·</span>
                    <span>Auto-rolling</span>
                </div>
            </div>
            <div class="budgets" id="budgetsGrid"><!-- populated by JS --></div>
        </section>

        <!-- =================== EMPTY STATE =================== -->
        <section class="section" id="budgetsEmpty" hidden>
            <div class="empty-state">
                <i class="fa-solid fa-bullseye" aria-hidden="true" style="font-size:36px;color:var(--whisper);"></i>
                <h2 style="font-family:var(--serif);font-style:italic;margin:12px 0 6px;">No budgets on file.</h2>
                <p>Set your first ceiling — pick a category, a limit, and a period.</p>
                <button type="button" class="add" onclick="openBudgetModal()" style="margin-top:16px;">
                    <i class="fa-solid fa-plus" aria-hidden="true"></i>
                    <span>Set new budget</span>
                </button>
            </div>
        </section>

        <!-- =================== FOOTER =================== -->
        <footer class="analytics-foot">Filed under § 04 · End of budgets.</footer>

    </main>
</div>

<!-- =================== ADD/EDIT BUDGET MODAL =================== -->
<div class="scrim" id="budgetModal" aria-hidden="true">
    <div class="sheet" role="dialog" aria-labelledby="budgetSheetTitle">
        <div class="sheet-head">
            <div>
                <span class="kicker-num" id="budgetModalKicker">§ 04</span>
                <h3 id="budgetSheetTitle">New budget</h3>
            </div>
            <button type="button" class="close" onclick="closeBudgetModal()" aria-label="Close">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form id="budgetForm" class="modal-form" onsubmit="handleBudgetSave(event)">
            <div class="field">
                <div class="field-row"><label for="bCategory">Category</label></div>
                <div class="input-shell">
                    <i class="fa-solid fa-tag" aria-hidden="true"></i>
                    <select id="bCategory" required>
                        <!-- populated by JS -->
                    </select>
                    <button type="button" class="inline-add" onclick="openQuickCat('bCategory')" title="Add new category" aria-label="Add new category"><i class="fa-solid fa-plus" aria-hidden="true"></i></button>
                </div>
            </div>

            <div class="grid-2">
                <div class="field">
                    <div class="field-row"><label for="bAmount">Monthly limit (₹)</label></div>
                    <div class="input-shell">
                        <i class="fa-solid fa-indian-rupee-sign" aria-hidden="true"></i>
                        <input type="number" id="bAmount" step="0.01" min="1" placeholder="0.00" required>
                    </div>
                </div>
                <div class="field">
                    <div class="field-row"><label for="bPeriod">Period</label></div>
                    <div class="input-shell">
                        <i class="fa-regular fa-calendar" aria-hidden="true"></i>
                        <select id="bPeriod">
                            <option value="monthly" selected>Monthly</option>
                            <option value="weekly">Weekly</option>
                            <option value="custom">Custom range</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="grid-2" id="bCustomRange" hidden>
                <div class="field">
                    <div class="field-row"><label for="bStart">Start</label></div>
                    <div class="input-shell">
                        <i class="fa-regular fa-calendar-plus" aria-hidden="true"></i>
                        <input type="date" id="bStart">
                    </div>
                </div>
                <div class="field">
                    <div class="field-row"><label for="bEnd">End</label></div>
                    <div class="input-shell">
                        <i class="fa-regular fa-calendar-xmark" aria-hidden="true"></i>
                        <input type="date" id="bEnd">
                    </div>
                </div>
            </div>

            <div class="field">
                <div class="field-row"><label>Alert thresholds (default 75% · 90% · 100%)</label></div>
                <div class="input-shell" style="gap:6px;">
                    <i class="fa-solid fa-bell" aria-hidden="true"></i>
                    <input type="number" id="bT1" min="1" max="100" value="75" style="max-width:64px;" aria-label="Threshold 1">
                    <span class="mono muted">%</span>
                    <input type="number" id="bT2" min="1" max="100" value="90" style="max-width:64px;" aria-label="Threshold 2">
                    <span class="mono muted">%</span>
                    <input type="number" id="bT3" min="1" max="100" value="100" style="max-width:64px;" aria-label="Threshold 3">
                    <span class="mono muted">%</span>
                </div>
            </div>

            <div class="form-actions">
                <button type="button" class="submit danger" id="bDeleteBtn" hidden onclick="handleBudgetDelete()">
                    <i class="fa-regular fa-trash-can" aria-hidden="true"></i>
                    <span>Delete</span>
                </button>
                <button type="submit" class="submit">
                    <i class="fa-solid fa-feather" aria-hidden="true"></i>
                    <span id="bSaveLabel">File budget</span>
                </button>
            </div>
        </form>
    </div>
</div>

<div id="toastContainer" class="toast-stack" aria-live="polite"></div>
<script id="budgetsData" type="application/json"><?php echo $jsonPayload; ?></script>
<script>
    window.APEX_TOKEN = <?php echo json_encode(csrf_token()); ?>;
    window.APEX_CATEGORIES = <?php echo json_encode($apexCategories); ?>;
</script>
<script>
    const serverTransactions = <?php echo json_encode($transactions); ?>;
</script>
<script src="../assets/js/category-helper.js?v=<?php echo time(); ?>"></script>
<script src="../assets/js/script.js?v=<?php echo time(); ?>"></script>
<script src="../assets/js/apex-select.js?v=<?php echo time(); ?>"></script>
<script src="../assets/js/quickadd.js?v=<?php echo time(); ?>"></script>
<script src="../assets/js/budgets.js?v=<?php echo time(); ?>"></script>
</body>
</html>
