<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/categories.php';

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$user = $_SESSION['user'];
$initials = strtoupper(substr($user['name'], 0, 1));
$activePage = 'categories';

$pdo = getDBConnection();
apex_categories_ready($pdo, $user['id']);
$apexCategories = apex_get_categories($pdo, $user['id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="dark light">
    <script>(function(){try{var m=localStorage.getItem('apex-theme')||'dark';var r=m==='system'?(window.matchMedia('(prefers-color-scheme: light)').matches?'light':'dark'):m;document.documentElement.setAttribute('data-theme',r);document.documentElement.setAttribute('data-theme-mode',m);}catch(e){document.documentElement.setAttribute('data-theme','dark');}})();</script>
    <title>ApexSpend — Categories</title>
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
                    <span class="label">Family</span>
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
                    <span class="kicker-num">§ 06</span>
                    <span class="kicker-text">Your Buckets</span>
                </div>
                <h1>Categories on<br><em>your terms.</em></h1>
                <p class="standfirst">Rename, recolor and add your own buckets. Built-ins stay put but can be restyled; custom ones can go.</p>
            </div>

            <div class="masthead-figure">
                <div class="figure-label">On file</div>
                <div class="figure-value">
                    <span id="catKpiCount"><?php echo count($apexCategories); ?></span>
                </div>
                <div class="figure-meta">
                    <span class="muted"><span id="catKpiCustom"><?php echo count(array_filter($apexCategories, fn($c) => (int)$c['is_builtin'] === 0)); ?></span> custom · rest are built-in</span>
                </div>
                <div class="actions">
                    <button type="button" class="add" onclick="openCatModal()">
                        <i class="fa-solid fa-plus" aria-hidden="true"></i>
                        <span>New category</span>
                    </button>
                </div>
            </div>
        </header>

        <!-- =================== CATEGORY GRID =================== -->
        <section class="section" aria-label="Categories">
            <div class="section-head">
                <h2><i class="fa-solid fa-tags section-ic" aria-hidden="true"></i> The buckets.</h2>
                <div class="section-meta">
                    <span id="liveMeta">Live</span>
                    <span aria-hidden="true">·</span>
                    <span>Per user</span>
                </div>
            </div>

            <div class="cat-grid" id="catGrid"></div>
            <div class="cat-empty" id="catEmpty" hidden>
                <i class="fa-solid fa-tags" aria-hidden="true" style="font-size:36px;color:var(--whisper);"></i>
                <h2 style="font-family:var(--serif);font-style:italic;margin:12px 0 6px;">No categories yet.</h2>
                <p>Add your first bucket to file entries against it.</p>
                <div class="actions" style="justify-content:center;margin-top:16px;">
                    <button type="button" class="add" onclick="openCatModal()">
                        <i class="fa-solid fa-plus" aria-hidden="true"></i>
                        <span>New category</span>
                    </button>
                </div>
            </div>
        </section>

        <footer class="analytics-foot">Filed under § 06 · Categories · Renames cascade to history.</footer>

    </main>
</div>

<!-- =================== CREATE / EDIT CATEGORY MODAL =================== -->
<div class="scrim" id="catModal" aria-hidden="true">
    <div class="sheet" role="dialog" aria-labelledby="catSheetTitle">
        <div class="sheet-head">
            <div>
                <span class="kicker-num" id="catModalKicker">§ 06</span>
                <h3 id="catSheetTitle">New category</h3>
            </div>
            <button type="button" class="close" onclick="closeCatModal()" aria-label="Close">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form id="catForm" onsubmit="return saveCat(event)">
            <input type="hidden" id="catId">
            <input type="hidden" id="catIconVal" value="fa-tag">
            <input type="hidden" id="catColorVal" value="#f59e0b">

            <div class="field">
                <div class="field-row"><label for="catName">Category name</label></div>
                <div class="input-shell">
                    <i class="fa-solid fa-tag" aria-hidden="true"></i>
                    <input type="text" id="catName" maxlength="100" placeholder="e.g. Gym & Fitness" required>
                </div>
                <span class="cat-hint" id="catNameHint">1–100 characters</span>
            </div>

            <div class="field">
                <div class="field-row"><label>Icon</label></div>
                <div class="cat-iconpick" id="catIconPick" aria-label="Pick an icon"></div>
            </div>

            <div class="field">
                <div class="field-row"><label>Color</label></div>
                <div class="cat-swatches" id="catSwatches" aria-label="Pick a color"></div>
            </div>

            <button type="submit" class="submit">
                <i class="fa-solid fa-feather" aria-hidden="true"></i>
                <span id="catSaveLabel">File category</span>
            </button>
        </form>
    </div>
</div>

<!-- =================== QUICK-ADD MODAL (inline + from tx/budget modals) =================== -->
<div class="scrim" id="quickCatModal" aria-hidden="true">
    <div class="sheet" role="dialog" aria-labelledby="quickCatTitle">
        <div class="sheet-head">
            <div>
                <span class="kicker-num">§ 06</span>
                <h3 id="quickCatTitle">Quick add</h3>
            </div>
            <button type="button" class="close" onclick="closeQuickCat()" aria-label="Close">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <form onsubmit="return saveQuickCat(event)">
            <div class="field">
                <div class="field-row"><label for="quickCatName">Category name</label></div>
                <div class="input-shell">
                    <i class="fa-solid fa-tag" aria-hidden="true"></i>
                    <input type="text" id="quickCatName" maxlength="100" placeholder="e.g. Gym & Fitness" required>
                </div>
            </div>
            <div class="field">
                <div class="field-row"><label>Color</label></div>
                <div class="cat-swatches" id="quickCatSwatches" aria-label="Pick a color"></div>
            </div>
            <button type="submit" class="submit">
                <i class="fa-solid fa-plus" aria-hidden="true"></i>
                <span>Create &amp; use</span>
            </button>
        </form>
    </div>
</div>

<div id="toastContainer" class="toast-stack" aria-live="polite"></div>
<script>
    window.APEX_TOKEN = <?php echo json_encode(csrf_token()); ?>;
    window.APEX_CATEGORIES = <?php echo json_encode($apexCategories); ?>;
</script>
<script src="../assets/js/script.js?v=<?php echo time(); ?>"></script>
<script src="../assets/js/categories.js?v=<?php echo time(); ?>"></script>
</body>
</html>
