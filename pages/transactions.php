<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/categories.php';

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$user = $_SESSION['user'];
$initials = strtoupper(substr($user['name'], 0, 1));
$activePage = 'ledger';

// ---- Format helpers ----
function format_inr($n) {
    return number_format((float)$n, 2, '.', ',');
}
function format_long_date($iso) {
    $ts = strtotime($iso);
    if (!$ts) return '';
    return date('d M', $ts);
}
function format_time($iso) {
    $ts = strtotime($iso);
    if (!$ts) return '';
    return date('H:i', $ts);
}

$pdo = getDBConnection();
$stmt = $pdo->prepare("SELECT * FROM transactions WHERE user_id = ? ORDER BY date DESC");
$stmt->execute([$user['id']]);
$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Compute summary stats
$total_in      = 0;
$total_out     = 0;
$count         = count($transactions);
foreach ($transactions as $t) {
    if ($t['type'] === 'income') $total_in += $t['amount'];
    else                          $total_out += $t['amount'];
}
$net = $total_in - $total_out;

// Categories from the DB (auto-migrates + seeds), merged with any name
// already present in existing transactions so old rows never vanish.
apex_load_category_registry($pdo, $user['id']);
$apexCategories = apex_get_categories($pdo, $user['id']);
$categoryCount = count($apexCategories);
$categories = array_column($apexCategories, 'name');
foreach ($transactions as $t) {
    if (!in_array($t['category'], $categories, true)) {
        $categories[] = $t['category'];
    }
}
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
                    <span class="kicker-num">§ 02</span>
                    <span class="kicker-text">The Daily Ledger</span>
                </div>
                <h1>Every line item,<br><em>filed.</em></h1>
                <p class="standfirst">The complete archive of your inflows and outflows — searchable, filterable, and ordered in the moment they happened.</p>
            </div>

            <div class="masthead-figure">
                <div class="figure-label">Net position · all time</div>
                <div class="figure-value">
                    <span class="currency">₹</span><span id="totalBalance"><?php echo format_inr($net); ?></span>
                </div>
                <div class="figure-meta">
                    <span class="muted"><?php echo $count; ?> entries on file</span>
                </div>
            </div>
        </header>

        <!-- =================== KPI ROW =================== -->
        <section class="kpi" aria-label="Ledger summary">
            <div class="cell">
                <div class="cell-num">01</div>
                <div class="label">Total inflow</div>
                <div class="figure"><span class="currency">₹</span><span id="totalIncome"><?php echo format_inr($total_in); ?></span></div>
                <div class="meta"><span class="muted">Income entries</span></div>
            </div>
            <div class="cell">
                <div class="cell-num">02</div>
                <div class="label">Total outflow</div>
                <div class="figure"><span class="currency">₹</span><span id="totalExpenses"><?php echo format_inr($total_out); ?></span></div>
                <div class="meta"><span class="muted">Expense entries</span></div>
            </div>
            <div class="cell">
                <div class="cell-num">03</div>
                <div class="label">Entries on file</div>
                <div class="figure"><span id="totalCount"><?php echo $count; ?></span></div>
                <div class="meta"><span class="muted">Across <?php echo $categoryCount; ?> categories</span></div>
            </div>
        </section>

        <!-- =================== LEDGER =================== -->
        <section class="section ledger-section" aria-label="Transaction history">
            <div class="section-head">
                <h2><i class="fa-solid fa-book section-ic" aria-hidden="true"></i> The ledger.</h2>
                <div class="section-meta">
                    <span>Searchable</span>
                    <span aria-hidden="true">·</span>
                    <span><span id="visibleCount"><?php echo $count; ?></span> of <?php echo $count; ?> entries</span>
                </div>
                <button type="button" class="submit" id="addTxOpen" style="width:auto; padding:10px 18px; margin-top:0;">
                    <i class="fa-solid fa-plus" aria-hidden="true"></i>
                    <span>Add entry</span>
                </button>
            </div>

            <div class="ledger">

                <!-- Search -->
                <div class="ledger-search">
                    <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                    <input type="search" id="txSearch" placeholder="Search merchant, note, or category…" autocomplete="off">
                </div>

                <!-- Category filter chips -->
                <div class="filters" role="tablist" aria-label="Filter by category">
                    <button class="is-on" data-filter="all">All</button>
                    <button data-filter="income">Income</button>
                    <button data-filter="expense">Expense</button>
                    <?php foreach ($categories as $cat): ?>
                        <button data-filter="<?php echo htmlspecialchars($cat); ?>">
                            <?php echo htmlspecialchars($cat); ?>
                        </button>
                    <?php endforeach; ?>
                </div>

                <!-- Header row -->
                <div class="led-grid head" aria-hidden="true">
                    <span class="col-label">When</span>
                    <span class="col-label">Merchant &amp; note</span>
                    <span class="col-label">Category</span>
                    <span class="col-label col-num">Method</span>
                    <span class="col-label col-num">Amount</span>
                    <span class="col-label col-num">Actions</span>
                </div>

                <!-- Body -->
                <div id="ledgerBody">
                <?php foreach ($transactions as $t):
                    $isIncome  = $t['type'] === 'income';
                    $sign      = $isIncome ? '+' : '−';
                    $cls       = $isIncome ? 'amount pos' : 'amount';
                    $searchHay = strtolower($t['title'] . ' ' . $t['note'] . ' ' . $t['category']);
                ?>
                    <div class="led-grid"
                         data-category="<?php echo htmlspecialchars($t['category']); ?>"
                         data-type="<?php echo htmlspecialchars($t['type']); ?>"
                         data-merchant="<?php echo htmlspecialchars($t['title'] . ' ' . $t['note']); ?>"
                         data-id="<?php echo htmlspecialchars($t['id']); ?>"
                         data-title="<?php echo htmlspecialchars($t['title']); ?>"
                         data-note="<?php echo htmlspecialchars($t['note']); ?>"
                         data-amount="<?php echo htmlspecialchars($t['amount']); ?>"
                         data-method="<?php echo htmlspecialchars($t['method']); ?>"
                         data-date="<?php echo htmlspecialchars(date('Y-m-d\TH:i', strtotime($t['date']))); ?>"
                         data-hay="<?php echo htmlspecialchars($searchHay); ?>">
                        <span class="when">
                            <span class="day"><?php echo format_long_date($t['date']); ?></span>
                            <?php echo format_time($t['date']); ?>
                        </span>
                        <span class="col-merch">
                            <strong><?php echo htmlspecialchars($t['title']); ?></strong>
                            <?php echo htmlspecialchars($t['note']); ?>
                        </span>
                        <span class="cat">
                            <i class="fa-solid <?php echo apex_category_icon($t['category']); ?>" aria-hidden="true"></i>
                            <?php echo htmlspecialchars($t['category']); ?>
                        </span>
                        <span class="method"><?php echo htmlspecialchars($t['method']); ?></span>
                        <span class="amount-wrap">
                            <span class="<?php echo $cls; ?>"><?php echo $sign; ?> ₹<?php echo format_inr($t['amount']); ?></span>
                        </span>
                        <span class="row-actions">
                            <button type="button" class="icon-btn edit-tx" aria-label="Edit <?php echo htmlspecialchars($t['title']); ?>" title="Edit">
                                <i class="fa-regular fa-pen-to-square" aria-hidden="true"></i>
                            </button>
                            <button type="button" class="icon-btn danger delete-tx" aria-label="Delete <?php echo htmlspecialchars($t['title']); ?>" title="Delete">
                                <i class="fa-regular fa-trash-can" aria-hidden="true"></i>
                            </button>
                        </span>
                    </div>
                <?php endforeach; ?>
                </div>

                <div class="ledger-foot">
                    <span class="filing">Filed <?php echo date('d M Y'); ?> — Page 01 of 01</span>
                    <span class="muted" id="emptyMsg" hidden>No matching entries.</span>
                    <span class="muted" id="endMsg">End of entries.</span>
                </div>
            </div>
        </section>

    </main>
</div>

<div class="scrim" id="addTxModal" aria-hidden="true">
    <div class="sheet" role="dialog" aria-labelledby="addSheetTitle">
        <div class="sheet-head">
            <div>
                <span class="kicker-num">§ 03</span>
                <h3 id="addSheetTitle">Add entry</h3>
            </div>
            <button type="button" class="close" id="addTxClose" aria-label="Close">
                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
            </button>
        </div>

        <div class="toggle-pair" role="tablist" aria-label="Transaction type">
            <button type="button" id="addTypeExpenseBtn" class="is-on expense" data-add-type="expense">Expense</button>
            <button type="button" id="addTypeIncomeBtn" class="income" data-add-type="income">Income</button>
        </div>

        <form id="addTxForm">
            <div class="field">
                <div class="field-row"><label for="addTxTitle">Merchant or note</label></div>
                <div class="input-shell">
                    <i class="fa-regular fa-pen-to-square" aria-hidden="true"></i>
                    <input type="text" id="addTxTitle" required>
                </div>
            </div>

            <div class="field">
                <div class="field-row"><label for="addTxNote">Detail note</label></div>
                <div class="input-shell">
                    <i class="fa-regular fa-note-sticky" aria-hidden="true"></i>
                    <input type="text" id="addTxNote">
                </div>
            </div>

            <div class="grid-2">
                <div class="field">
                    <div class="field-row"><label for="addTxAmount">Amount</label></div>
                    <div class="input-shell">
                        <i class="fa-solid fa-indian-rupee-sign" aria-hidden="true"></i>
                        <input type="number" step="0.01" min="0.01" id="addTxAmount" required>
                    </div>
                </div>

                <div class="field">
                    <div class="field-row"><label for="addTxCategory">Category</label></div>
                    <div class="input-shell">
                        <i class="fa-solid fa-tag" aria-hidden="true"></i>
                        <select id="addTxCategory">
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat); ?>"><?php echo htmlspecialchars($cat); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" class="inline-add" onclick="openQuickCat('addTxCategory')" title="Add new category" aria-label="Add new category"><i class="fa-solid fa-plus" aria-hidden="true"></i></button>
                    </div>
                </div>
            </div>

            <div class="grid-2">
                <div class="field">
                    <div class="field-row"><label for="addTxMethod">Payment method</label></div>
                    <div class="input-shell">
                        <i class="fa-regular fa-credit-card" aria-hidden="true"></i>
                        <input type="text" id="addTxMethod">
                    </div>
                </div>

                <div class="field">
                    <div class="field-row"><label for="addTxDate">Date</label></div>
                    <div class="input-shell">
                        <i class="fa-regular fa-calendar" aria-hidden="true"></i>
                        <input type="datetime-local" id="addTxDate" required>
                    </div>
                </div>
            </div>

            <button type="submit" class="submit">
                <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>
                <span>File entry</span>
            </button>
        </form>
    </div>
</div>

<div class="scrim" id="editTxModal" aria-hidden="true">
    <div class="sheet" role="dialog" aria-labelledby="editSheetTitle">
        <div class="sheet-head">
            <div>
                <span class="kicker-num">Â§ 04</span>
                <h3 id="editSheetTitle">Edit entry</h3>
            </div>
            <button type="button" class="close" id="editTxClose" aria-label="Close">
                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
            </button>
        </div>

        <div class="toggle-pair" role="tablist" aria-label="Transaction type">
            <button type="button" id="editTypeExpenseBtn" class="is-on expense" data-edit-type="expense">Expense</button>
            <button type="button" id="editTypeIncomeBtn" class="income" data-edit-type="income">Income</button>
        </div>

        <form id="editTxForm">
            <input type="hidden" id="editTxId">

            <div class="field">
                <div class="field-row"><label for="editTxTitle">Merchant or note</label></div>
                <div class="input-shell">
                    <i class="fa-regular fa-pen-to-square" aria-hidden="true"></i>
                    <input type="text" id="editTxTitle" required>
                </div>
            </div>

            <div class="field">
                <div class="field-row"><label for="editTxNote">Detail note</label></div>
                <div class="input-shell">
                    <i class="fa-regular fa-note-sticky" aria-hidden="true"></i>
                    <input type="text" id="editTxNote">
                </div>
            </div>

            <div class="grid-2">
                <div class="field">
                    <div class="field-row"><label for="editTxAmount">Amount</label></div>
                    <div class="input-shell">
                        <i class="fa-solid fa-indian-rupee-sign" aria-hidden="true"></i>
                        <input type="number" step="0.01" min="0.01" id="editTxAmount" required>
                    </div>
                </div>

                <div class="field">
                    <div class="field-row"><label for="editTxCategory">Category</label></div>
                    <div class="input-shell">
                        <i class="fa-solid fa-tag" aria-hidden="true"></i>
                        <select id="editTxCategory">
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat); ?>"><?php echo htmlspecialchars($cat); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" class="inline-add" onclick="openQuickCat('editTxCategory')" title="Add new category" aria-label="Add new category"><i class="fa-solid fa-plus" aria-hidden="true"></i></button>
                    </div>
                </div>
            </div>

            <div class="grid-2">
                <div class="field">
                    <div class="field-row"><label for="editTxMethod">Payment method</label></div>
                    <div class="input-shell">
                        <i class="fa-regular fa-credit-card" aria-hidden="true"></i>
                        <input type="text" id="editTxMethod">
                    </div>
                </div>

                <div class="field">
                    <div class="field-row"><label for="editTxDate">Date</label></div>
                    <div class="input-shell">
                        <i class="fa-regular fa-calendar" aria-hidden="true"></i>
                        <input type="datetime-local" id="editTxDate" required>
                    </div>
                </div>
            </div>

            <button type="submit" class="submit">
                <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>
                <span>Save entry</span>
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
<script src="../assets/js/transactions.js?v=<?php echo time(); ?>"></script>
</body>
</html>
