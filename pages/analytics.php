<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/categories.php';

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$user     = $_SESSION['user'];
$initials = strtoupper(substr($user['name'], 0, 1));
$activePage = 'analytics';

function format_inr($n) {
    return number_format((float)$n, 2, '.', ',');
}

// ---- Seed demo transactions (KEEP IN SYNC with transactions.php) ----
$pdo = getDBConnection();
$stmt = $pdo->prepare("SELECT * FROM transactions WHERE user_id = ? ORDER BY date DESC");
$stmt->execute([$user['id']]);
$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
$hasData = !empty($transactions);

// =========================================================================
// AGGREGATIONS (only run when we have data)
// =========================================================================
$byMonth       = [];   // 'YYYY-MM' => ['income'=>x, 'expense'=>y, 'count'=>n]
$byCategory    = [];   // category   => total expense
$byMethod      = [];   // method     => ['total'=>x, 'count'=>n]
$byMerchant    = [];   // title      => ['total'=>x, 'count'=>n, 'category'=>cat]
$byDow         = array_fill(1, 7, ['total'=>0, 'count'=>0]); // 1=Mon..7=Sun
$allMonths     = [];
$dailyByMonth  = [];   // 'YYYY-MM' => ['YYYY-MM-DD' => ['income'=>x, 'expense'=>y]]
$biggestEntry  = null;
$recurring     = [];   // merchants appearing ≥2 times

if ($hasData) {
    $totalIncome = 0;
    $totalExpense = 0;
    foreach ($transactions as $t) {
        $ts = strtotime($t['date']);
        $ym = date('Y-m', $ts);
        $day = date('Y-m-d', $ts);
        $dow = ((int)date('N', $ts)); // 1..7, Mon=1

        if (!isset($byMonth[$ym])) {
            $byMonth[$ym] = ['income' => 0, 'expense' => 0, 'count' => 0];
        }
        if (!isset($dailyByMonth[$ym])) {
            $dailyByMonth[$ym] = [];
        }
        if (!isset($dailyByMonth[$ym][$day])) {
            $dailyByMonth[$ym][$day] = ['income' => 0, 'expense' => 0];
        }

        $byMonth[$ym]['count']++;
        if ($t['type'] === 'income') {
            $byMonth[$ym]['income'] += $t['amount'];
            $dailyByMonth[$ym][$day]['income'] += $t['amount'];
            $totalIncome += $t['amount'];
        } else {
            $byMonth[$ym]['expense'] += $t['amount'];
            $dailyByMonth[$ym][$day]['expense'] += $t['amount'];
            $totalExpense += $t['amount'];

            // Category / method / merchant — expense only
            $cat = $t['category'];
            $byCategory[$cat] = ($byCategory[$cat] ?? 0) + $t['amount'];

            $m = $t['method'];
            if (!isset($byMethod[$m])) $byMethod[$m] = ['total' => 0, 'count' => 0];
            $byMethod[$m]['total'] += $t['amount'];
            $byMethod[$m]['count']++;

            $title = $t['title'];
            if (!isset($byMerchant[$title])) {
                $byMerchant[$title] = ['total' => 0, 'count' => 0, 'category' => $cat];
            }
            $byMerchant[$title]['total'] += $t['amount'];
            $byMerchant[$title]['count']++;
        }

        $byDow[$dow]['total'] += $t['type'] === 'expense' ? $t['amount'] : 0;
        if ($t['type'] === 'expense') $byDow[$dow]['count']++;

        if ($t['type'] === 'expense' && ($biggestEntry === null || $t['amount'] > $biggestEntry['amount'])) {
            $biggestEntry = [
                'title'    => $t['title'],
                'amount'   => $t['amount'],
                'category' => $t['category'],
                'date'     => $t['date'],
                'method'   => $t['method'],
            ];
        }
    }

    ksort($byMonth);
    $allMonths = array_keys($byMonth);

    // Detect recurring merchants (≥2 entries, amounts within ±20% of median)
    foreach ($byMerchant as $title => $info) {
        if ($info['count'] >= 2) {
            $amounts = [];
            foreach ($transactions as $t) {
                if ($t['type'] === 'expense' && $t['title'] === $title) $amounts[] = $t['amount'];
            }
            sort($amounts);
            $median = $amounts[(int)floor(count($amounts) / 2)];
            $within = true;
            foreach ($amounts as $a) {
                if (abs($a - $median) / max($median, 1) > 0.20) { $within = false; break; }
            }
            if ($within) {
                $avg = array_sum($amounts) / count($amounts);
                $recurring[] = [
                    'title'    => $title,
                    'category' => $info['category'],
                    'avg'      => $avg,
                    'count'    => count($amounts),
                ];
            }
        }
    }
    usort($recurring, fn($a, $b) => $b['avg'] <=> $a['avg']);

    // Top merchants (by total spend)
    uasort($byMerchant, fn($a, $b) => $b['total'] <=> $a['total']);
    $topMerchants = array_slice($byMerchant, 0, 6, true);

    // Top category
    arsort($byCategory);
}

// =========================================================================
// Hero KPIs for a given month
// =========================================================================
function month_key_offsets($allMonths, $selected) {
    // Returns ['selected'=>$ym, 'prior'=>ym|null]
    $idx = array_search($selected, $allMonths, true);
    return [
        'selected' => $selected,
        'prior'    => $idx > 0 ? $allMonths[$idx - 1] : null,
    ];
}
$selectedMonth = !empty($allMonths) ? end($allMonths) : null;
$offsets = $selectedMonth ? month_key_offsets($allMonths, $selectedMonth) : ['selected'=>null, 'prior'=>null];

function month_kpis($ym, $dailyByMonth, $transactions, $totalExpense) {
    $out = ['income'=>0, 'expense'=>0, 'count'=>0, 'days'=>0, 'avg'=>0, 'topCat'=>null, 'topCatTotal'=>0, 'biggest'=>null, 'entryDays'=>0];
    if (!$ym) return $out;
    $byCatLocal = [];
    $daysWithData = [];
    foreach ($transactions as $t) {
        $ts = strtotime($t['date']);
        if (date('Y-m', $ts) !== $ym) continue;
        $out['count']++;
        $day = date('Y-m-d', $ts);
        $daysWithData[$day] = true;
        if ($t['type'] === 'income') {
            $out['income'] += $t['amount'];
        } else {
            $out['expense'] += $t['amount'];
            $byCatLocal[$t['category']] = ($byCatLocal[$t['category']] ?? 0) + $t['amount'];
            if ($out['biggest'] === null || $t['amount'] > $out['biggest']['amount']) {
                $out['biggest'] = ['title'=>$t['title'], 'amount'=>$t['amount'], 'date'=>$t['date']];
            }
        }
    }
    $out['days'] = count($daysWithData);
    $out['avg']  = $out['days'] > 0 ? $out['expense'] / $out['days'] : 0;
    if (!empty($byCatLocal)) {
        arsort($byCatLocal);
        $topName = array_key_first($byCatLocal);
        $out['topCat'] = $topName;
        $out['topCatTotal'] = $byCatLocal[$topName];
    }
    return $out;
}

$selKpis   = $selectedMonth ? month_kpis($selectedMonth, $dailyByMonth, $transactions, 0) : null;
$priorKpis = ($offsets['prior']) ? month_kpis($offsets['prior'], $dailyByMonth, $transactions, 0) : null;

$totalSavings = ($selKpis['income'] ?? 0) - ($selKpis['expense'] ?? 0);
$savingsRate  = ($selKpis && $selKpis['income'] > 0) ? $totalSavings / $selKpis['income'] : null;

// Daily series for selected month
$dailySeries = [];
if ($selectedMonth && isset($dailyByMonth[$selectedMonth])) {
    ksort($dailyByMonth[$selectedMonth]);
    foreach ($dailyByMonth[$selectedMonth] as $day => $vals) {
        $dailySeries[] = [
            'date'    => $day,
            'income'  => $vals['income'],
            'expense' => $vals['expense'],
        ];
    }
}

// =========================================================================
// Build JSON payload for JS
// =========================================================================
$payload = [
    'currency'      => 'INR',
    'hasData'       => $hasData,
    'count'         => count($transactions),
    'allMonths'     => array_values($allMonths),
    'selectedMonth' => $selectedMonth,
    'priorMonth'    => $offsets['prior'],
    'monthLabel'    => $selectedMonth ? date('M Y', strtotime($selectedMonth . '-01')) : null,
    'priorLabel'    => $offsets['prior'] ? date('M Y', strtotime($offsets['prior'] . '-01')) : null,

    // Hero KPIs
    'kpis' => $selKpis ? [
        'avgDaily'    => round($selKpis['avg'], 2),
        'topCategory' => $selKpis['topCat'],
        'topCategoryTotal' => $selKpis['topCatTotal'],
        'topCategoryShare' => $selKpis['expense'] > 0 ? round($selKpis['topCatTotal'] / $selKpis['expense'], 4) : 0,
        'biggest'     => $selKpis['biggest'],
        'savingsRate' => $savingsRate,
        'income'      => round($selKpis['income'], 2),
        'expense'     => round($selKpis['expense'], 2),
        'count'       => $selKpis['count'],
        'days'        => $selKpis['days'],
    ] : null,
    'priorKpis' => $priorKpis ? [
        'avgDaily' => round($priorKpis['avg'], 2),
        'income'   => round($priorKpis['income'], 2),
        'expense'  => round($priorKpis['expense'], 2),
        'count'    => $priorKpis['count'],
    ] : null,

    // Cash flow (daily, this month)
    'daily' => array_map(fn($d) => [
        'date'    => $d['date'],
        'label'   => date('d M', strtotime($d['date'])),
        'income'  => round($d['income'], 2),
        'expense' => round($d['expense'], 2),
    ], $dailySeries),

    // Session-wide cuts
    'byCategory' => array_map(fn($k, $v) => ['name'=>$k, 'total'=>round($v,2)], array_keys($byCategory), array_values($byCategory)),
    'byMethod'   => array_map(fn($k, $v) => ['name'=>$k, 'total'=>round($v['total'],2), 'count'=>$v['count']], array_keys($byMethod), array_values($byMethod)),
    'byDow'      => array_map(fn($i) => ['dow'=>$i, 'label'=>['Mon','Tue','Wed','Thu','Fri','Sat','Sun'][$i-1], 'total'=>round($byDow[$i]['total'],2), 'count'=>$byDow[$i]['count']], [1,2,3,4,5,6,7]),
    'topMerchants' => array_map(fn($k, $v) => [
        'title'    => $k,
        'category' => $v['category'],
        'total'    => round($v['total'],2),
        'count'    => $v['count'],
    ], array_keys($topMerchants ?? []), array_values($topMerchants ?? [])),

    'recurring' => array_map(fn($r) => [
        'title'    => $r['title'],
        'category' => $r['category'],
        'avg'      => round($r['avg'],2),
        'count'    => $r['count'],
    ], $recurring),

    'oneOffCount' => max(0, count($transactions) - count(array_unique(array_column(array_filter($transactions, fn($t)=>$t['type']==='expense'), 'title')))),
];

$jsonPayload = json_encode($payload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);

// Shared helper for ISO render in PHP-side markup (DB-backed registry)
apex_load_category_registry($pdo, $user['id']);
$apexCategories = apex_get_categories($pdo, $user['id']);

// Day-of-week row helper for the static markup
$dowLabels = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="dark light">
    <script>(function(){try{var m=localStorage.getItem('apex-theme')||'dark';var r=m==='system'?(window.matchMedia('(prefers-color-scheme: light)').matches?'light':'dark'):m;document.documentElement.setAttribute('data-theme',r);document.documentElement.setAttribute('data-theme-mode',m);}catch(e){document.documentElement.setAttribute('data-theme','dark');}})();</script>
    <title>ApexSpend — Analytics</title>
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
                    <span class="kicker-num">§ 03</span>
                    <span class="kicker-text">The Accounts</span>
                </div>
                <h1>Where the money<br><em>actually goes.</em></h1>
                <p class="standfirst">Cross-section of your behaviour — filtered, ranked, and grouped by where it went, when it happened, and how it was paid.</p>
            </div>

            <div class="masthead-figure">
                <div class="figure-label">
                    Savings rate
                    <span class="kicker-num" style="margin-left:8px;"><?php echo htmlspecialchars($payload['monthLabel'] ?? '—'); ?></span>
                </div>
                <div class="figure-value">
                    <span class="currency">₹</span>
                    <span id="heroSavings">
                        <?php echo $selKpis ? format_inr(max(0, $totalSavings)) : '—'; ?>
                    </span>
                </div>
                <div class="figure-meta">
                    <span class="trend <?php echo ($priorKpis && $totalSavings > ($priorKpis['income'] - $priorKpis['expense'])) ? 'up' : 'down'; ?>" id="heroSavingsDelta">
                        <?php
                            if (!$priorKpis) {
                                echo 'First month on file';
                            } else {
                                $priorSavings = $priorKpis['income'] - $priorKpis['expense'];
                                if ($priorSavings == 0) {
                                    echo '— vs. prior';
                                } else {
                                    $deltaPct = round((($totalSavings - $priorSavings) / abs($priorSavings)) * 100, 1);
                                    $arrow = $deltaPct >= 0 ? '▲' : '▼';
                                    echo htmlspecialchars($arrow . ' ' . number_format(abs($deltaPct), 1) . '% vs. prior');
                                }
                            }
                        ?>
                    </span>
                </div>
                <div class="actions">
                    <button type="button" class="ghost" onclick="showToast('CSV export queued. (Demo only.)', 'info')">
                        <i class="fa-solid fa-file-arrow-down" aria-hidden="true"></i>
                        <span>Export CSV</span>
                    </button>
                </div>
            </div>
        </header>

        <?php if (!$hasData): ?>
            <!-- ===== EMPTY STATE ===== -->
            <div class="empty-state">
                <i class="fa-solid fa-folder-open" aria-hidden="true" style="font-size:36px;color:var(--whisper);"></i>
                <h2 style="font-family:var(--serif);font-style:italic;margin:12px 0 6px;">No transactions on file.</h2>
                <p>File one to see analytics. Head back to the <a href="dashboard.php" style="color:var(--tangerine);">Overview</a> or <a href="transactions.php" style="color:var(--tangerine);">Ledger</a> to add an entry.</p>
            </div>
        <?php else: ?>

        <!-- =================== TOOLBAR =================== -->
        <section class="analytics-toolbar">
            <div class="toolbar-left">
                <label class="toolbar-label" for="monthPicker">Period</label>
                <select id="monthPicker" class="month-picker">
                    <!-- populated by JS from DATA.allMonths -->
                </select>
                <div class="period-toggle" role="group" aria-label="Cash flow granularity">
                    <button type="button" class="is-on" data-gran="daily">Daily</button>
                    <button type="button" data-gran="weekly">Weekly</button>
                </div>
            </div>
            <div class="toolbar-right">
                <span class="kicker-num" id="entryCount">Live · <?php echo count($transactions); ?> entries</span>
            </div>
        </section>

        <!-- =================== HERO KPI STRIP (5-up) =================== -->
        <section class="kpi variant-five" aria-label="Deep-dive figures">
            <div class="cell">
                <div class="cell-num">01</div>
                <div class="label">Avg daily spend</div>
                <div class="figure">
                    <span class="currency">₹</span><span id="kpiAvgDaily"><?php echo format_inr($selKpis['avg']); ?></span>
                </div>
                <div class="meta">
                    <?php if ($priorKpis && $priorKpis['avg'] > 0): ?>
                        <?php $d = ($selKpis['avg'] - $priorKpis['avg']) / $priorKpis['avg'] * 100; ?>
                        <span class="trend <?php echo $d <= 0 ? 'up' : 'down'; ?>"><?php echo ($d <= 0 ? '▼' : '▲') . ' ' . number_format(abs($d),1) . '%'; ?></span>
                        <span>vs. <?php echo htmlspecialchars($payload['priorLabel']); ?></span>
                    <?php else: ?>
                        <span class="muted">First month on file</span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="cell">
                <div class="cell-num">02</div>
                <div class="label">Top category</div>
                <div class="figure" style="font-family:var(--serif);font-style:italic;font-size:clamp(22px,2.4vw,30px);">
                    <?php echo htmlspecialchars($selKpis['topCat'] ?? '—'); ?>
                </div>
                <div class="meta">
                    <span class="muted">₹<?php echo format_inr($selKpis['topCatTotal']); ?> · <?php echo $selKpis['expense']>0 ? round($selKpis['topCatTotal']/$selKpis['expense']*100,1) : 0; ?>% of outflow</span>
                </div>
            </div>

            <div class="cell">
                <div class="cell-num">03</div>
                <div class="label">Biggest single entry</div>
                <div class="figure">
                    <span class="currency">₹</span><span><?php echo $selKpis['biggest'] ? format_inr($selKpis['biggest']['amount']) : '—'; ?></span>
                </div>
                <div class="meta">
                    <span class="muted"><?php echo htmlspecialchars($selKpis['biggest']['title'] ?? ''); ?><?php if (!empty($selKpis['biggest'])): ?> · <?php echo date('d M', strtotime($selKpis['biggest']['date'])); ?><?php endif; ?></span>
                </div>
            </div>

            <div class="cell">
                <div class="cell-num">04</div>
                <div class="label">Savings rate</div>
                <div class="figure">
                    <span id="kpiSavingsRate"><?php echo $savingsRate !== null ? number_format($savingsRate*100, 1) . '%' : '—'; ?></span>
                </div>
                <div class="meta">
                    <span class="muted">Goal: 30% · ₹<?php echo format_inr(max(0,$totalSavings)); ?> saved</span>
                </div>
                <?php if ($savingsRate !== null): ?>
                <div class="progress" aria-label="Savings goal">
                    <div class="progress-track">
                        <div class="progress-fill" style="--w: <?php echo min(100, round($savingsRate*100/0.30*100)); ?>%"></div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <div class="cell">
                <div class="cell-num">05</div>
                <div class="label">Entry frequency</div>
                <div class="figure">
                    <span id="kpiCount"><?php echo (int)$selKpis['count']; ?></span>
                </div>
                <div class="meta">
                    <span class="muted">
                        <?php
                            $perWeek = $selKpis['days'] > 0 ? round($selKpis['count'] / ($selKpis['days']/7), 1) : 0;
                            echo htmlspecialchars($perWeek . ' / week · ' . $selKpis['days'] . ' active days');
                        ?>
                    </span>
                </div>
            </div>
        </section>

        <!-- =================== CASH FLOW CHART =================== -->
        <section class="section">
            <div class="section-head">
                <h2><i class="fa-solid fa-arrow-trend-up section-ic" aria-hidden="true"></i> Cash flow, by <?php echo htmlspecialchars($payload['monthLabel'] ?? 'month'); ?></h2>
                <span class="meta">Income vs expense · daily</span>
            </div>
            <div class="panel chart-frame">
                <canvas id="cashFlowChart" aria-label="Daily cash flow chart"></canvas>
            </div>
        </section>

        <!-- =================== TWO-UP: DAY OF WEEK + DOUGHNUT =================== -->
        <section class="two-up">
            <div>
                <div class="section-head">
                    <h2><i class="fa-solid fa-calendar-week section-ic" aria-hidden="true"></i> Spending by day of week</h2>
                    <span class="meta">Session-wide</span>
                </div>
                <div class="panel chart-frame">
                    <canvas id="dayOfWeekChart" aria-label="Spending by day of week"></canvas>
                </div>
            </div>
            <div>
                <div class="section-head">
                    <h2><i class="fa-solid fa-chart-pie section-ic" aria-hidden="true"></i> Outflows, by category</h2>
                    <span class="meta">Session-wide</span>
                </div>
                <div class="panel chart-frame">
                    <div class="donut-wrap">
                        <canvas id="outflowsByCategoryChart" aria-label="Outflows by category doughnut"></canvas>
                        <div class="donut-center">
                            <span class="kicker-text" style="font-size:10px;letter-spacing:0.14em;">Total outflow</span>
                            <span class="figure" id="donutCenterLabel">₹<?php echo format_inr(array_sum($byCategory)); ?></span>
                        </div>
                    </div>
                    <ul class="legend" id="categoryLegend"></ul>
                </div>
            </div>
        </section>

        <!-- =================== PAYMENT METHOD =================== -->
        <section class="section">
            <div class="section-head">
                <h2><i class="fa-solid fa-credit-card section-ic" aria-hidden="true"></i> Payment method mix</h2>
                <span class="meta">UPI · RuPay · NEFT · IMPS · Cash</span>
            </div>
            <div class="panel chart-frame">
                <canvas id="paymentMethodChart" aria-label="Payment method breakdown"></canvas>
            </div>
            <div class="method-cells three-up">
                <?php
                    $methodTotal = array_sum(array_column($byMethod, 'total'));
                    $sortedMethods = $byMethod;
                    uasort($sortedMethods, fn($a,$b)=>$b['total']<=>$a['total']);
                    $topMethods = array_slice($sortedMethods, 0, 5, true);
                    foreach ($topMethods as $mName => $mInfo):
                        $share = $methodTotal > 0 ? $mInfo['total'] / $methodTotal : 0;
                ?>
                    <div class="method-cell">
                        <div class="method-cell-head">
                            <span class="method-name"><?php echo htmlspecialchars($mName); ?></span>
                            <span class="method-amount">₹<?php echo format_inr($mInfo['total']); ?></span>
                        </div>
                        <div class="method-cell-meta">
                            <span class="muted"><?php echo (int)$mInfo['count']; ?> entries · <?php echo round($share*100,1); ?>%</span>
                        </div>
                        <div class="progress">
                            <div class="progress-track">
                                <div class="progress-fill" style="--w: <?php echo round($share*100,1); ?>%"></div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- =================== TOP MERCHANTS =================== -->
        <section class="section">
            <div class="section-head">
                <h2><i class="fa-solid fa-list-check section-ic" aria-hidden="true"></i> The leading counterparties.</h2>
                <span class="meta">Session-wide · ranked</span>
            </div>
            <div class="panel merchant-list">
                <?php
                    $merchantTotal = array_sum(array_column($topMerchants, 'total'));
                    $i = 1;
                    foreach ($topMerchants as $mTitle => $mInfo):
                        $share = $merchantTotal > 0 ? $mInfo['total'] / $merchantTotal : 0;
                ?>
                    <div class="merchant-row">
                        <span class="rank"><?php echo str_pad($i, 2, '0', STR_PAD_LEFT); ?></span>
                        <span class="micon"><i class="fa-solid <?php echo apex_category_icon($mInfo['category']); ?>" style="color:var(--tangerine);" aria-hidden="true"></i></span>
                        <div class="mname">
                            <strong><?php echo htmlspecialchars($mTitle); ?></strong>
                            <span class="muted"><?php echo (int)$mInfo['count']; ?> <?php echo $mInfo['count']==1?'entry':'entries'; ?> · <?php echo htmlspecialchars($mInfo['category']); ?></span>
                        </div>
                        <div class="progress large">
                            <div class="progress-track">
                                <div class="progress-fill" style="--w: <?php echo round($share*100,1); ?>%"></div>
                            </div>
                        </div>
                        <span class="mgross">₹<?php echo format_inr($mInfo['total']); ?></span>
                    </div>
                <?php $i++; endforeach; ?>
            </div>
        </section>

        <!-- =================== DAILY HEATMAP STRIP =================== -->
        <section class="section">
            <div class="section-head">
                <h2><i class="fa-solid fa-grip section-ic" aria-hidden="true"></i> Daily trend · <?php echo htmlspecialchars($payload['monthLabel'] ?? ''); ?></h2>
                <span class="meta">Each cell is one day · darker = heavier outflow</span>
            </div>
            <div class="panel heatmap-strip" id="dailyStrip" aria-label="Daily heatmap for selected month">
                <!-- populated by JS -->
            </div>
        </section>

        <!-- =================== RECURRING vs ONE-OFF =================== -->
        <section class="section">
            <div class="section-head">
                <h2><i class="fa-solid fa-arrows-rotate section-ic" aria-hidden="true"></i> Recurring vs one-off.</h2>
                <span class="meta">Merchants with ≥2 hits and ±20% amount variance</span>
            </div>
            <div class="recurring-grid two-up">
                <div class="panel">
                    <div class="recurring-head">
                        <span class="kicker-num">RECURRING</span>
                        <span class="muted" id="recurringTotal">
                            <?php
                                $recurringSum = array_sum(array_column($recurring, 'avg'));
                                echo $recurring ? '₹' . format_inr($recurringSum) . ' / mo' : 'None detected';
                            ?>
                        </span>
                    </div>
                    <ul class="recurring-list">
                        <?php if (empty($recurring)): ?>
                            <li class="muted" style="padding:18px 4px;">No recurring outflows detected yet.</li>
                        <?php else: ?>
                            <?php foreach (array_slice($recurring, 0, 6) as $r): ?>
                                <li>
                                    <span class="rec-merchant"><?php echo htmlspecialchars($r['title']); ?></span>
                                    <span class="rec-meta muted"><?php echo htmlspecialchars($r['category']); ?> · <?php echo (int)$r['count']; ?>×</span>
                                    <span class="rec-amt">₹<?php echo format_inr($r['avg']); ?></span>
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </div>
                <div class="panel">
                    <div class="recurring-head">
                        <span class="kicker-num">ONE-OFF · THIS MONTH</span>
                        <span class="muted">₹<?php echo format_inr($selKpis['expense']); ?></span>
                    </div>
                    <ul class="recurring-list">
                        <?php
                            // One-offs: things that appear once in their category
                            $merchantSeenThisMonth = [];
                            $oneOffList = [];
                            foreach ($transactions as $t) {
                                if ($t['type'] !== 'expense') continue;
                                if (date('Y-m', strtotime($t['date'])) !== $selectedMonth) continue;
                                $title = $t['title'];
                                $merchantSeenThisMonth[$title] = ($merchantSeenThisMonth[$title] ?? 0) + 1;
                            }
                            foreach ($transactions as $t) {
                                if ($t['type'] !== 'expense') continue;
                                if (date('Y-m', strtotime($t['date'])) !== $selectedMonth) continue;
                                if (($merchantSeenThisMonth[$t['title']] ?? 0) === 1) {
                                    $oneOffList[] = $t;
                                }
                            }
                            usort($oneOffList, fn($a,$b) => $b['amount'] <=> $a['amount']);
                            $oneOffList = array_slice($oneOffList, 0, 6);
                            if (empty($oneOffList)):
                        ?>
                            <li class="muted" style="padding:18px 4px;">No one-off entries this month.</li>
                        <?php else: foreach ($oneOffList as $oo): ?>
                            <li>
                                <span class="rec-merchant"><?php echo htmlspecialchars($oo['title']); ?></span>
                                <span class="rec-meta muted"><?php echo htmlspecialchars($oo['category']); ?> · <?php echo date('d M', strtotime($oo['date'])); ?></span>
                                <span class="rec-amt">₹<?php echo format_inr($oo['amount']); ?></span>
                            </li>
                        <?php endforeach; endif; ?>
                     <!-- =================== FOOTER =================== -->
        <footer class="analytics-foot">
            Compiled <?php echo strtoupper(date('d M Y')); ?> · Filed under § 03 · End of deep analytics.
        </footer>

        <?php endif; /* hasData */ ?>
    </main>
</div>

<!-- Toast container + data blob -->
<div id="toastContainer" class="toast-stack" aria-live="polite"></div>
<script id="analyticsData" type="application/json"><?php echo $jsonPayload; ?></script>

<!-- Expose data for Chart.js -->
<script>
    window.APEX_CATEGORIES = <?php echo json_encode($apexCategories); ?>;
</script>
<script>
    const serverTransactions = <?php echo json_encode($transactions); ?>;
</script>
<script src="../assets/js/category-helper.js?v=<?php echo time(); ?>"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="../assets/js/analytics.js?v=<?php echo time(); ?>"></script>
</body>
</html>
