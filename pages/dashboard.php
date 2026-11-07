<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/categories.php';
require_once __DIR__ . '/../includes/welcome.php';

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$user = $_SESSION['user'];
$initials = strtoupper(substr($user['name'], 0, 1));
$activePage = 'overview';

$pdo = getDBConnection();
$transactions = [];
if ($pdo) {
    $stmt = $pdo->prepare("SELECT * FROM transactions WHERE user_id = ? ORDER BY date DESC");
    $stmt->execute([$user['id']]);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$current_month = date('m');
$current_year = date('Y');
$last_month = date('m', strtotime('-1 month'));
$last_month_year = date('Y', strtotime('-1 month'));

$income_this_month = 0;
$outflows_this_month = 0;
$income_last_month = 0;
$outflows_last_month = 0;

$total_in = 0;
$total_out = 0;

$monthly_income = array_fill(0, 6, 0);
$monthly_outflows = array_fill(0, 6, 0);
$current_time = time();

foreach ($transactions as $t) {
    $t_time = strtotime($t['date']);
    $t_month = date('m', $t_time);
    $t_year = date('Y', $t_time);

    if ($t['type'] === 'income') $total_in += $t['amount'];
    else $total_out += $t['amount'];

    if ($t_month == $current_month && $t_year == $current_year) {
        if ($t['type'] === 'income') $income_this_month += $t['amount'];
        else $outflows_this_month += $t['amount'];
    }
    
    if ($t_month == $last_month && $t_year == $last_month_year) {
        if ($t['type'] === 'income') $income_last_month += $t['amount'];
        else $outflows_last_month += $t['amount'];
    }
    
    $months_ago = (date('Y', $current_time) - date('Y', $t_time)) * 12 + (date('m', $current_time) - date('m', $t_time));
    if ($months_ago >= 0 && $months_ago < 6) {
        $idx = 5 - $months_ago;
        if ($t['type'] === 'income') $monthly_income[$idx] += $t['amount'];
        else $monthly_outflows[$idx] += $t['amount'];
    }
}
$net = $total_in - $total_out;

$net_last_month = $net - ($income_this_month - $outflows_this_month);
$net_trend = $net_last_month != 0 ? (($net - $net_last_month) / abs($net_last_month)) * 100 : ($net > 0 ? 100 : 0);

$hour = (int)date('H');
if ($hour < 12) $greeting = 'Good morning';
elseif ($hour < 17) $greeting = 'Good afternoon';
else $greeting = 'Good evening';

$max_income = max($monthly_income) ?: 1;
$max_outflows = max($monthly_outflows) ?: 1;

$income_trend = $income_last_month > 0 ? (($income_this_month - $income_last_month) / $income_last_month) * 100 : ($income_this_month > 0 ? 100 : 0);
$outflows_trend = $outflows_last_month > 0 ? (($outflows_this_month - $outflows_last_month) / $outflows_last_month) * 100 : ($outflows_this_month > 0 ? 100 : 0);

// Retrieve goals from session or database
$savings_goal = 50000.00;
$run_rate_target = 1000000.00;

if (isset($_SESSION['user']['savings_goal'])) {
    $savings_goal = (float) $_SESSION['user']['savings_goal'];
}
if (isset($_SESSION['user']['run_rate_target'])) {
    $run_rate_target = (float) $_SESSION['user']['run_rate_target'];
}

if ($pdo) {
    // Ensure columns exist (safeguard)
    try {
        $pdo->query("SELECT savings_goal, run_rate_target FROM users LIMIT 1");
    } catch (PDOException $e) {
        try {
            $pdo->exec("ALTER TABLE users ADD COLUMN savings_goal DECIMAL(15,2) DEFAULT 50000.00");
            $pdo->exec("ALTER TABLE users ADD COLUMN run_rate_target DECIMAL(15,2) DEFAULT 1000000.00");
        } catch (PDOException $e2) {}
    }
    
    $stmt = $pdo->prepare("SELECT savings_goal, run_rate_target FROM users WHERE id = ?");
    $stmt->execute([$user['id']]);
    $user_goals = $stmt->fetch();
    if ($user_goals) {
        if (!is_null($user_goals['savings_goal'])) {
            $savings_goal = (float) $user_goals['savings_goal'];
            $_SESSION['user']['savings_goal'] = $savings_goal;
        }
        if (!is_null($user_goals['run_rate_target'])) {
            $run_rate_target = (float) $user_goals['run_rate_target'];
            $_SESSION['user']['run_rate_target'] = $run_rate_target;
        }
    }

    // Keep the email-notification preference in the session for the mail path.
    require_once __DIR__ . '/../includes/email_prefs.php';
    $_SESSION['user']['email_notifications'] = email_notifications_enabled($pdo, (int) $user['id']) ? 1 : 0;
}

// One-time onboarding card for brand-new accounts only.
$showWelcome = ($pdo && apex_needs_welcome($pdo, (int) $user['id']));

$saved_this_month = $income_this_month - $outflows_this_month;
$savings_progress = $savings_goal > 0 ? min(100, max(0, ($saved_this_month / $savings_goal) * 100)) : 0;

$run_rate_annual = $income_this_month * 12;
$run_rate_progress = $run_rate_target > 0 ? min(100, max(0, ($run_rate_annual / $run_rate_target) * 100)) : 0;

function format_inr($n) {
    return number_format((float)$n, 2, '.', ',');
}

// Categories come from the DB via the shared helper (auto-migrates + seeds).
apex_load_category_registry($pdo, $user['id']);
$apexCategories = apex_get_categories($pdo, $user['id']);
$category_names = array_column($apexCategories, 'name');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="dark light">
    <script>(function(){try{var m=localStorage.getItem('apex-theme')||'dark';var r=m==='system'?(window.matchMedia('(prefers-color-scheme: light)').matches?'light':'dark'):m;document.documentElement.setAttribute('data-theme',r);document.documentElement.setAttribute('data-theme-mode',m);}catch(e){document.documentElement.setAttribute('data-theme','dark');}})();</script>
    <title>ApexSpend — The Ledger</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=Geist+Mono:wght@400;500&family=Geist:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                    <span class="kicker-num">§ 01</span>
                    <span class="kicker-text">The Daily Ledger</span>
                </div>
                <h1><?php echo $greeting; ?>,<br><em><?php echo htmlspecialchars(explode(' ', $user['name'])[0]); ?></em>.</h1>
                <p class="standfirst">Six figures, two charts, and the line items in between — filed in the order they happened.</p>
            </div>

            <div class="masthead-figure">
                <div class="figure-label">Net balance · <?php echo date('F'); ?></div>
                <div class="figure-value">
                    <span class="currency">₹</span><span id="totalBalance"><?php echo format_inr($net); ?></span>
                </div>
                <div class="figure-meta">
                    <span class="trend <?php echo $net_trend >= 0 ? 'up' : 'down'; ?>"><?php echo $net_trend >= 0 ? '▲' : '▼'; ?> <?php echo number_format(abs($net_trend), 1); ?>%</span>
                    <span class="muted">vs. last month</span>
                </div>
                <div class="actions">
                    <button type="button" class="ghost" onclick="window.print()">
                        <i class="fa-regular fa-file-pdf" aria-hidden="true"></i>
                        <span>Export</span>
                    </button>
                    <button type="button" class="add" onclick="openModal()">
                        <i class="fa-solid fa-plus" aria-hidden="true"></i>
                        <span>New entry</span>
                    </button>
                </div>
            </div>
        </header>

        <!-- =================== WELCOME CARD (new accounts, shown once) =================== -->
        <?php if ($showWelcome): ?>
        <section class="welcome" id="welcomeCard" aria-labelledby="welcomeTitle">
            <div class="welcome-head">
                <div class="welcome-copy">
                    <div class="kicker">
                        <span class="kicker-num">§ 00</span>
                        <span class="kicker-text">New here</span>
                    </div>
                    <h2 id="welcomeTitle">Welcome to ApexSpend, <em><?php echo htmlspecialchars(explode(' ', $user['name'])[0]); ?></em>.</h2>
                    <p>Three things to set up before your first full month of tracking.</p>
                </div>
                <button type="button" class="welcome-dismiss" id="welcomeDismiss">
                    <i class="fa-solid fa-check" aria-hidden="true"></i>
                    <span>Got it</span>
                </button>
            </div>

            <div class="welcome-steps">
                <div class="welcome-step">
                    <span class="welcome-num">01</span>
                    <div class="welcome-step-copy">
                        <h3><i class="fa-solid fa-receipt" aria-hidden="true"></i> Add your first entry</h3>
                        <p>Record an expense or income — the ledger does the rest.</p>
                    </div>
                    <button type="button" class="welcome-action" onclick="openModal()">
                        Add entry <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                    </button>
                </div>
                <div class="welcome-step">
                    <span class="welcome-num">02</span>
                    <div class="welcome-step-copy">
                        <h3><i class="fa-solid fa-chart-pie" aria-hidden="true"></i> Set a budget</h3>
                        <p>Monthly limits with 75 / 90 / 100% alerts.</p>
                    </div>
                    <a class="welcome-action" href="budgets.php">
                        Open budgets <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                    </a>
                </div>
                <div class="welcome-step">
                    <span class="welcome-num">03</span>
                    <div class="welcome-step-copy">
                        <h3><i class="fa-solid fa-people-roof" aria-hidden="true"></i> Create a family space</h3>
                        <p>Share the ledger with family using an invite code.</p>
                    </div>
                    <a class="welcome-action" href="family.php">
                        Create space <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                    </a>
                </div>
            </div>
        </section>
        <?php endif; ?>

        <!-- =================== KPI ROW + MINI BAR CHARTS =================== -->
        <section class="kpi" aria-label="Key figures">
            <div class="cell">
                <div class="cell-num">01</div>
                <div class="label">Income, <?php echo date('M'); ?></div>
                <div class="figure"><span class="currency">₹</span><span id="totalIncome"><?php echo format_inr($income_this_month); ?></span></div>
                <div class="meta"><span class="trend <?php echo $income_trend >= 0 ? 'up' : 'down'; ?>"><?php echo $income_trend >= 0 ? '▲' : '▼'; ?> <?php echo number_format(abs($income_trend), 1); ?>%</span><span>vs. <?php echo date('M', strtotime('-1 month')); ?></span></div>
                <!-- Mini bar chart: income by month -->
                <div class="spark" aria-hidden="true">
                    <?php foreach ($monthly_income as $amt): ?>
                    <span class="bar <?php echo $amt == $max_income && $amt > 0 ? 'is-peak' : ''; ?>" style="--h: <?php echo ($amt / $max_income * 100); ?>%"></span>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="cell">
                <div class="cell-num">02</div>
                <div class="label">Outflows, <?php echo date('M'); ?></div>
                <div class="figure"><span class="currency">₹</span><span id="totalExpenses"><?php echo format_inr($outflows_this_month); ?></span></div>
                <div class="meta"><span class="trend <?php echo $outflows_trend <= 0 ? 'up' : 'down'; ?>"><?php echo $outflows_trend >= 0 ? '▲' : '▼'; ?> <?php echo number_format(abs($outflows_trend), 1); ?>%</span><span>vs. <?php echo date('M', strtotime('-1 month')); ?></span></div>
                <div class="spark" aria-hidden="true">
                    <?php foreach ($monthly_outflows as $amt): ?>
                    <span class="bar alt <?php echo $amt == $max_outflows && $amt > 0 ? 'is-peak' : ''; ?>" style="--h: <?php echo ($amt / $max_outflows * 100); ?>%"></span>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="cell">
                <div class="cell-num">03</div>
                <button type="button" class="cell-edit-btn" onclick="openGoalsModal()" aria-label="Edit savings goal">
                    <i class="fa-solid fa-gear"></i>
                </button>
                <div class="label">Saved this month</div>
                <div class="figure"><span class="currency">₹</span><span id="totalSavings"><?php echo format_inr($saved_this_month); ?></span></div>
                <div class="meta"><span class="trend <?php echo $saved_this_month >= 0 ? 'up' : 'down'; ?>"><?php echo number_format($savings_progress, 0); ?>%</span><span>of goal</span></div>
                <!-- Progress bar -->
                <div class="progress" aria-label="Progress to savings goal">
                    <div class="progress-track">
                        <div class="progress-fill" style="--w: <?php echo $savings_progress; ?>%"></div>
                    </div>
                    <span class="progress-label"><?php echo number_format($savings_progress, 0); ?>% of ₹<?php echo format_inr($savings_goal); ?> goal</span>
                </div>
            </div>
            <div class="cell">
                <div class="cell-num">04</div>
                <button type="button" class="cell-edit-btn" onclick="openGoalsModal()" aria-label="Edit annual target">
                    <i class="fa-solid fa-gear"></i>
                </button>
                <div class="label">Run-rate, annual</div>
                <div class="figure"><span class="currency">₹</span><span><?php echo format_inr($run_rate_annual); ?></span></div>
                <div class="meta"><span class="trend <?php echo $run_rate_progress >= 100 ? 'up' : 'down'; ?>"><?php echo number_format($run_rate_progress, 1); ?>%</span><span>projection</span></div>
                <div class="progress" aria-label="Progress to annual target">
                    <div class="progress-track">
                        <div class="progress-fill alt" style="--w: <?php echo $run_rate_progress; ?>%"></div>
                    </div>
                    <span class="progress-label"><?php echo number_format($run_rate_progress, 0); ?>% of ₹<?php echo format_inr($run_rate_target); ?> target</span>
                </div>
            </div>
        </section>

        <!-- =================== CONTRIBUTION HEATMAP (GITHUB-STYLE) =================== -->
        <section class="section heatmap-section" aria-label="Spending heatmap, last 12 months">
            <div class="section-head">
                <h2><i class="fa-solid fa-grip section-ic" aria-hidden="true"></i> Spending heatmap.</h2>
                <div class="section-meta">
                    <span>Last 12 months</span>
                    <span aria-hidden="true">·</span>
                    <span>53 weeks</span>
                </div>
            </div>

            <div class="heatmap">
                <div class="heatmap-graph">
                    <div class="heatmap-months" id="heatmapMonths" aria-hidden="true"></div>
                    <div class="heatmap-body">
                        <div class="heatmap-days" id="heatmapDays" aria-hidden="true">
                            <span class="hm-day" data-dow="0"></span>
                            <span class="hm-day" data-dow="1">Mon</span>
                            <span class="hm-day" data-dow="2"></span>
                            <span class="hm-day" data-dow="3">Wed</span>
                            <span class="hm-day" data-dow="4"></span>
                            <span class="hm-day" data-dow="5">Fri</span>
                            <span class="hm-day" data-dow="6"></span>
                        </div>
                        <div class="heatmap-grid" id="heatmapGrid" role="grid" aria-label="Spending by day"></div>
                    </div>
                </div>
                <div class="heatmap-foot">
                    <span class="heatmap-link">How spending intensity is counted</span>
                    <div class="heatmap-legend">
                        <span class="legend-label">Less</span>
                        <span class="legend-cell" data-level="0"></span>
                        <span class="legend-cell" data-level="1"></span>
                        <span class="legend-cell" data-level="2"></span>
                        <span class="legend-cell" data-level="3"></span>
                        <span class="legend-cell" data-level="4"></span>
                        <span class="legend-label">More</span>
                    </div>
                </div>
            </div>

            <!-- Hover tooltip (singletons, positioned via JS) -->
            <div class="heatmap-tip" id="heatmapTip" role="tooltip" aria-hidden="true">
                <div class="tip-date">—</div>
                <div class="tip-row">
                    <span class="tip-label">Outflows</span>
                    <span class="tip-value">—</span>
                </div>
                <div class="tip-row">
                    <span class="tip-label">Entries</span>
                    <span class="tip-value">—</span>
                </div>
            </div>
        </section>

        <!-- =================== ANALYTICS SECTION =================== -->
        <section class="section">
            <div class="section-head">
                <h2><i class="fa-solid fa-chart-line section-ic" aria-hidden="true"></i> Analytics.</h2>
                <div class="section-meta">
                    <span>Last 6 months</span>
                    <span aria-hidden="true">·</span>
                    <span>Live</span>
                </div>
            </div>

            <div class="two-up">
                <div class="chart-frame panel">
                    <div class="chart-head">
                        <h3>Cash flow</h3>
                        <span class="tag">Income · Expense</span>
                    </div>
                    <div class="chart-body">
                        <canvas id="incomeExpenseChart" aria-label="Income versus expenses, last six months"></canvas>
                    </div>
                </div>

                <div class="chart-frame panel">
                    <div class="chart-head">
                        <h3>Outflows</h3>
                        <span class="tag">By category</span>
                    </div>
                    <div class="chart-body">
                        <canvas id="categoryChart" aria-label="Spending by category"></canvas>
                    </div>
                    <div class="legend" id="categoryLegend"></div>
                </div>
            </div>
        </section>

        <!-- =================== INCOME VS EXPENSE — GROUPED BARS =================== -->
        <section class="section" aria-label="Income vs Expense, period toggle">
            <div class="section-head">
                <h2><i class="fa-solid fa-chart-column section-ic" aria-hidden="true"></i> Income vs Expense.</h2>
                <div class="period-toggle" role="tablist" aria-label="Period">
                    <button type="button" class="is-on" data-period="monthly" role="tab">Monthly</button>
                    <button type="button" data-period="yearly" role="tab">Yearly</button>
                </div>
            </div>

            <div class="chart-frame panel">
                <div class="chart-head">
                    <h3 id="barChartTitle">Income vs Expense · last 6 months</h3>
                    <span class="tag">Grouped</span>
                </div>
                <div class="chart-body" style="height:280px;">
                    <canvas id="barIncomeExpenseChart" aria-label="Income versus expense, grouped bars"></canvas>
                </div>
            </div>
        </section>

        <!-- =================== BUDGETS — PROGRESS BARS =================== -->
        <section class="section budgets-section" aria-label="Monthly budgets">
            <div class="section-head">
                <h2><i class="fa-solid fa-bullseye section-ic" aria-hidden="true"></i> Budgets.</h2>
                <div class="section-meta">
                    <span>July</span>
                    <span aria-hidden="true">·</span>
                    <span>Auto-rolling</span>
                    <span aria-hidden="true">·</span>
                    <a href="budgets.php" class="manage-link">Manage →</a>
                </div>
            </div>

            <div class="budgets" id="dashboardBudgetsGrid"></div>
        </section>

        <!-- =================== LEDGER — THE SIGNATURE =================== -->
        <section class="section ledger-section" aria-label="Recent transactions">
            <div class="section-head">
                <h2><i class="fa-solid fa-book section-ic" aria-hidden="true"></i> The ledger.</h2>
                <div class="section-meta">
                    <span>Real-time</span>
                    <span aria-hidden="true">·</span>
                    <span>6 entries</span>
                </div>
            </div>

            <div class="ledger">

                <div class="filters" role="tablist" aria-label="Filter by category">
                    <button class="is-on" data-filter="all">All</button>
                    <button data-filter="income">Income</button>
                    <?php foreach ($category_names as $cn): if ($cn === 'Income') continue; ?>
                        <button data-filter="<?php echo htmlspecialchars($cn); ?>"><?php echo htmlspecialchars($cn); ?></button>
                    <?php endforeach; ?>
                </div>

                <div class="led-grid head" aria-hidden="true">
                    <span class="col-label">When</span>
                    <span class="col-label">Merchant &amp; note</span>
                    <span class="col-label">Category</span>
                    <span class="col-label col-num">Method</span>
                    <span class="col-label col-num">Amount · Balance</span>
                </div>

                <div id="ledgerBody">
                <?php 
                $run_balance = $net;
                $display_transactions = array_slice($transactions, 0, 6);
                foreach ($display_transactions as $t): 
                    $isIncome = $t['type'] === 'income';
                    $sign = $isIncome ? '+' : '−';
                    $cls = $isIncome ? 'amount pos' : 'amount';
                    
                    // Simple logic for the demo run balance
                    $curr_balance = $run_balance;
                    if ($isIncome) {
                        $run_balance -= $t['amount'];
                    } else {
                        $run_balance += $t['amount'];
                    }
                ?>
                    <div class="led-grid" data-category="<?php echo htmlspecialchars($t['category']); ?>">
                        <span class="when"><span class="day"><?php echo date('d M', strtotime($t['date'])); ?></span><?php echo date('H:i', strtotime($t['date'])); ?></span>
                        <span class="col-merch"><strong><?php echo htmlspecialchars($t['title']); ?></strong> <?php echo htmlspecialchars($t['note']); ?></span>
                        <span class="cat"><i class="fa-solid <?php echo apex_category_icon($t['category']); ?>" aria-hidden="true"></i> <?php echo htmlspecialchars($t['category']); ?></span>
                        <span class="method"><?php echo htmlspecialchars($t['method']); ?></span>
                        <span class="amount-wrap">
                            <span class="<?php echo $cls; ?>"><?php echo $sign; ?> ₹<?php echo format_inr($t['amount']); ?></span>
                            <span class="balance">₹<?php echo format_inr($curr_balance); ?></span>
                        </span>
                    </div>
                <?php endforeach; ?>
                <?php if(empty($display_transactions)): ?>
                    <p style="padding: 20px; color: var(--whisper);">No transactions on file.</p>
                <?php endif; ?>
                </div>

                <div class="ledger-foot">
                    <span class="filing">Filed <?php echo date('d M Y'); ?> — Page 01 of 01</span>
                    <span class="muted">End of entries.</span>
                </div>
            </div>
        </section>

    </main>
</div>

<!-- =================== ADD TRANSACTION MODAL =================== -->
<div class="scrim" id="txModal" aria-hidden="true">
    <div class="sheet" role="dialog" aria-labelledby="sheetTitle">
        <div class="sheet-head">
            <div>
                <span class="kicker-num">§ 03</span>
                <h3 id="sheetTitle">New entry</h3>
            </div>
            <button type="button" class="close" onclick="closeModal()" aria-label="Close">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <div class="toggle-pair" role="tablist">
            <button type="button" id="typeExpenseBtn" class="is-on expense" onclick="setTxType('expense')">Expense</button>
            <button type="button" id="typeIncomeBtn" class="income" onclick="setTxType('income')">Income</button>
        </div>

        <form id="txForm" onsubmit="handleAddTransaction(event)">
            <div class="field">
                <div class="field-row"><label for="txTitle">Merchant or note</label></div>
                <div class="input-shell">
                    <i class="fa-regular fa-pen-to-square" aria-hidden="true"></i>
                    <input type="text" id="txTitle" placeholder="e.g. Trader Joe's, Rent, Salary" required>
                </div>
            </div>

            <div class="grid-2">
                <div class="field">
                    <div class="field-row"><label for="txAmount">Amount</label></div>
                    <div class="input-shell">
                        <i class="fa-solid fa-indian-rupee-sign" aria-hidden="true"></i>
                        <input type="number" step="0.01" id="txAmount" placeholder="0.00" required>
                    </div>
                </div>

                <div class="field">
                    <div class="field-row"><label for="txCategory">Category</label></div>
                    <div class="input-shell">
                        <i class="fa-solid fa-tag" aria-hidden="true"></i>
                        <select id="txCategory">
                            <?php foreach ($category_names as $cn): if ($cn === 'Income') { ?>
                                <option value="Income">Income / Salary</option>
                            <?php continue; } ?>
                                <option value="<?php echo htmlspecialchars($cn); ?>"><?php echo htmlspecialchars($cn); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" class="inline-add" onclick="openQuickCat('txCategory')" title="Add new category" aria-label="Add new category"><i class="fa-solid fa-plus" aria-hidden="true"></i></button>
                    </div>
                </div>
            </div>

            <div class="field">
                <div class="field-row"><label for="txMethod">Payment method</label></div>
                <div class="input-shell">
                    <i class="fa-regular fa-credit-card" aria-hidden="true"></i>
                    <select id="txMethod" data-apex-select>
                        <option value="UPI ··4182">UPI ··4182</option>
                        <option value="RuPay ··2207">RuPay ··2207</option>
                        <option value="NEFT ··0019">NEFT ··0019</option>
                        <option value="IMPS">IMPS</option>
                        <option value="Cash">Cash</option>
                    </select>
                </div>
            </div>

            <button type="submit" class="submit">
                <i class="fa-solid fa-feather" aria-hidden="true"></i>
                <span>File entry</span>
            </button>
        </form>
    </div>
</div>

<!-- =================== EDIT GOALS MODAL =================== -->
<div class="scrim" id="goalsModal" aria-hidden="true">
    <div class="sheet" role="dialog" aria-labelledby="goalsSheetTitle">
        <div class="sheet-head">
            <div>
                <span class="kicker-num">§ 04</span>
                <h3 id="goalsSheetTitle">Set Financial Goals</h3>
            </div>
            <button type="button" class="close" onclick="closeGoalsModal()" aria-label="Close">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form id="goalsForm" onsubmit="handleUpdateGoals(event)">
            <div class="field">
                <div class="field-row"><label for="goalSavings">Monthly Savings Goal (₹)</label></div>
                <div class="input-shell">
                    <i class="fa-solid fa-indian-rupee-sign" aria-hidden="true"></i>
                    <input type="number" step="0.01" id="goalSavings" value="<?php echo htmlspecialchars($savings_goal); ?>" required>
                </div>
            </div>

            <div class="field">
                <div class="field-row"><label for="goalRunRate">Annual Run-rate Target (₹)</label></div>
                <div class="input-shell">
                    <i class="fa-solid fa-indian-rupee-sign" aria-hidden="true"></i>
                    <input type="number" step="0.01" id="goalRunRate" value="<?php echo htmlspecialchars($run_rate_target); ?>" required>
                </div>
            </div>

            <button type="submit" class="submit">
                <i class="fa-regular fa-floppy-disk" aria-hidden="true"></i>
                <span>Save Goals</span>
            </button>
        </form>
    </div>
</div>

<div id="toastContainer" class="toast-stack" aria-live="polite"></div>
<script>
    window.APEX_TOKEN = <?php echo json_encode(csrf_token()); ?>;
</script>
<script>
    window.APEX_CATEGORIES = <?php echo json_encode($apexCategories); ?>;
</script>
<script>
    const serverTransactions = <?php echo json_encode($transactions); ?>;
</script>
<script src="../assets/js/category-helper.js?v=<?php echo time(); ?>"></script>
<script src="../assets/js/script.js?v=<?php echo time(); ?>"></script>
<script src="../assets/js/apex-select.js?v=<?php echo time(); ?>"></script>
<script src="../assets/js/quickadd.js?v=<?php echo time(); ?>"></script>
<script src="../assets/js/app.js?v=<?php echo time(); ?>"></script>
<script>
(function () {
    const card = document.getElementById('welcomeCard');
    const button = document.getElementById('welcomeDismiss');
    if (!card || !button) return;

    button.addEventListener('click', async function () {
        button.disabled = true;
        try {
            const body = new FormData();
            body.append('_token', window.APEX_TOKEN);

            const res = await fetch('../api/api_dismiss_welcome.php', {
                method: 'POST',
                body,
                headers: { 'X-CSRF-Token': window.APEX_TOKEN }
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok || !data.success) {
                throw new Error(data.error || 'Could not dismiss the welcome card.');
            }

            card.style.opacity = '0';
            card.style.transform = 'translateY(-6px)';
            setTimeout(() => card.remove(), 260);

            if (typeof showToast === 'function') {
                showToast('Welcome aboard!', 'success');
            }
        } catch (err) {
            button.disabled = false;
            if (typeof showToast === 'function') {
                showToast(err.message, 'error');
            }
        }
    });
})();
</script>
</body>
</html>
