<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/email_prefs.php';

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$pdo = getDBConnection();
if (!$pdo) {
    // Single storage mode: no MySQL, no app. Fail loudly instead of
    // pretending the data is what it is not.
    http_response_code(503);
    $_SESSION['flash_error'] = 'The database is unreachable right now. Please try again shortly.';
    header('Location: login.php');
    exit;
}
$userId = (int) $_SESSION['user']['id'];
$user = $_SESSION['user'];
$activePage = 'profile';

// Hydrate the full record (DB carries created_at, avatar_path, goals).
$stmt = $pdo->prepare('SELECT id, name, email, avatar_path, savings_goal, run_rate_target, created_at FROM users WHERE id = ?');
$stmt->execute([$userId]);
if ($row = $stmt->fetch()) $user = array_merge($user, $row);

$txStmt = $pdo->prepare('SELECT COUNT(*) FROM transactions WHERE user_id = ?');
$txStmt->execute([$userId]);
$txCount = (int) $txStmt->fetchColumn();
$bgStmt = $pdo->prepare('SELECT COUNT(*) FROM budgets WHERE user_id = ?');
$bgStmt->execute([$userId]);
$budgetCount = (int) $bgStmt->fetchColumn();

$savings_goal = isset($user['savings_goal']) ? (float)$user['savings_goal'] : 50000.00;
$run_rate_target = isset($user['run_rate_target']) ? (float)$user['run_rate_target'] : 1000000.00;

$emailNotif = email_notifications_enabled($pdo, $userId) ? 1 : 0;
$_SESSION['user']['email_notifications'] = $emailNotif;

$initials = strtoupper(substr($user['name'], 0, 1));
$createdAt = $user['created_at'] ?? date('Y-m-d H:i:s');
$memberSince = date('F Y', strtotime($createdAt));
$accountAgeDays = max(1, (int) floor((time() - strtotime($createdAt)) / 86400));

$flashError = getFlashMessage('error');
$flashSuccess = getFlashMessage('success');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="dark light">
    <meta name="csrf-token" content="<?= htmlspecialchars(csrf_token()) ?>">
    <script>(function(){try{var m=localStorage.getItem('apex-theme')||'dark';var r=m==='system'?(window.matchMedia('(prefers-color-scheme: light)').matches?'light':'dark'):m;document.documentElement.setAttribute('data-theme',r);document.documentElement.setAttribute('data-theme-mode',m);}catch(e){document.documentElement.setAttribute('data-theme','dark');}})();</script>
    <title>ApexSpend — Profile</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=Geist+Mono:wght@400;500&family=Geist:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css?v=<?php echo time(); ?>">
    <script src="../assets/js/theme.js?v=<?php echo time(); ?>"></script>
    <style>
        /* Compact profile layout — no long scroll */
        .canvas .masthead { margin-bottom: 1.5rem; padding-bottom: 1.25rem; }
        .profile-grid {
            display: grid;
            grid-template-columns: 300px minmax(0, 1fr);
            gap: 1rem;
            align-items: start;
        }
        .profile-right {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            align-items: start;
            min-width: 0;
        }
        @media (max-width: 1150px) { .profile-right { grid-template-columns: 1fr; } }
        @media (max-width: 880px) { .profile-grid { grid-template-columns: 1fr; } }

        .profile-card {
            padding: 1.15rem 1.2rem;
            background: var(--paper-card);
            border: var(--hairline);
            border-radius: 2px;
            min-width: 0;
        }
        .profile-card.is-sticky { position: sticky; top: 1rem; }
        @media (max-width: 880px) { .profile-card.is-sticky { position: static; } }
        .avatar-xl {
            width: 68px; height: 68px;
            border-radius: 50%;
            display: grid; place-items: center;
            font-family: var(--serif);
            font-size: 1.7rem;
            font-style: italic;
            color: #000;
            background: var(--tangerine);
            margin-bottom: .7rem;
        }
        .profile-name { margin: 0 0 .15rem; font-size: 1.25rem; line-height: 1.2; overflow-wrap: anywhere; }
        .profile-email { margin: 0 0 .75rem; color: var(--muted-text); font-size: .85rem; overflow-wrap: anywhere; }
        .meta-list { list-style: none; padding: 0; margin: .75rem 0 0; }
        .meta-list li {
            display: flex; justify-content: space-between; align-items: baseline; gap: 1rem;
            padding: .5rem 0; border-top: 1px dashed var(--line);
            font-size: .85rem;
        }
        .meta-list li:first-child { border-top: 0; }
        .meta-list .k { color: var(--muted-text); white-space: nowrap; }
        .meta-list .v { font-family: var(--mono); text-align: right; }

        .stat-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: .5rem; margin: .75rem 0 0; }
        .stat {
            background: var(--mist);
            border: var(--hairline);
            padding: .6rem .4rem;
            text-align: center;
        }
        .stat .n { font-family: var(--mono); font-weight: 600; font-size: 1.25rem; line-height: 1; }
        .stat .l { font-size: .65rem; color: var(--muted-text); text-transform: uppercase; letter-spacing: .06em; margin-top: .25rem; }

        .section-title { font-family: var(--serif); font-style: italic; font-weight: 400; font-size: 1.25rem; margin: 0 0 .7rem; padding-bottom: .55rem; border-bottom: var(--hairline); }
        form.profile-form { display: grid; gap: .6rem; }
        .pf-field { display: grid; gap: .3rem; min-width: 0; }
        .pf-field label { font-family: var(--mono); font-size: .68rem; text-transform: uppercase; letter-spacing: .1em; color: var(--muted-text); }
        .pf-field input {
            width: 100%; padding: .55rem .65rem; font-size: .9rem; color: var(--ink);
            background: transparent; border: var(--hairline-strong);
            border-radius: 2px; outline: none;
        }
        .pf-field input:focus { border-color: var(--tangerine); }
        .pf-field input[readonly] { opacity: .6; cursor: default; }
        .pf-actions { display: flex; gap: .5rem; flex-wrap: wrap; margin-top: .15rem; }
        .btn {
            font-family: var(--mono); font-size: .7rem; font-weight: 600; letter-spacing: .08em; text-transform: uppercase;
            cursor: pointer; padding: .6rem 1rem; border-radius: 999px; border: var(--hairline-strong);
            background: transparent; color: var(--ink);
        }
        .btn-primary { background: var(--tangerine); color: #000; border-color: var(--tangerine); }
        .btn-ghost { background: transparent; }

        .flash {
            padding: .6rem .85rem; border: var(--hairline);
            font-size: .85rem; margin-bottom: 1rem;
        }
        .flash.error { background: rgb(255 99 99 / .12); color: var(--loss); }
        .flash.success { background: rgb(80 200 120 / .12); color: var(--gain); }

        .pref-row { display: flex; justify-content: space-between; align-items: center; gap: 1rem; padding: .5rem 0; border-top: 1px dashed var(--line); }
        .pref-row:first-of-type { border-top: 0; }
        .pref-row .k { font-size: .85rem; }
        .pref-row .v { color: var(--muted-text); font-size: .8rem; font-family: var(--mono); text-align: right; }
        .theme-hint { margin: .45rem 0 0; font-size: .72rem; color: var(--muted-text); font-family: var(--mono); }
    </style>
</head>
<body>
<div class="app">

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
                <li><a href="dashboard.php" class="<?= $activePage==='overview' ? 'is-active' : '' ?>"><i class="fa-solid fa-house nav-ic"></i><span class="label">Overview</span></a></li>
                <li><a href="transactions.php" class="<?= $activePage==='ledger' ? 'is-active' : '' ?>"><i class="fa-solid fa-receipt nav-ic"></i><span class="label">Ledger</span></a></li>
                <li><a href="analytics.php" class="<?= $activePage==='analytics' ? 'is-active' : '' ?>"><i class="fa-solid fa-chart-pie nav-ic"></i><span class="label">Analytics</span></a></li>
                <li><a href="budgets.php" class="<?= $activePage==='budgets' ? 'is-active' : '' ?>"><i class="fa-solid fa-bullseye nav-ic"></i><span class="label">Budgets</span></a></li>
                <li><a href="categories.php" class="<?= $activePage==='categories' ? 'is-active' : '' ?>"><i class="fa-solid fa-tags nav-ic"></i><span class="label">Categories</span></a></li>
                <li><a href="family.php" class="<?= $activePage==='family' ? 'is-active' : '' ?>"><i class="fa-solid fa-users nav-ic"></i><span class="label">Family <span class="lock-tag" title="Locked"><i class="fa-solid fa-lock"></i></span></span></a></li>
                <li><a href="profile.php" class="<?= $activePage==='profile' ? 'is-active' : '' ?>"><i class="fa-solid fa-user nav-ic"></i><span class="label">Profile</span></a></li>
            </ul>
        </div>
        <div class="who">
            <a href="profile.php" class="avatar"><?= htmlspecialchars($initials) ?></a>
            <div class="who-meta">
                <strong><?= htmlspecialchars($user['name']) ?></strong>
                <span><?= htmlspecialchars($user['email']) ?></span>
            </div>
            <button type="button" class="logout" onclick="window.location.href='logout.php'" aria-label="Sign out">
                <i class="fa-solid fa-arrow-right-from-bracket"></i>
            </button>
        </div>
    </aside>

    <main class="canvas">

        <header class="masthead">
            <div class="masthead-col">
                <div class="kicker">
                    <span class="kicker-num">§ 05</span>
                    <span class="kicker-text">The Reader's Card</span>
                </div>
                <h1>Your <em>profile</em>.</h1>
                <p class="standfirst">Name, email, password, and the rest of the filing — all in one place.</p>
            </div>
            <div class="masthead-figure">
                <div class="figure-label">Member since</div>
                <div class="figure-value" style="font-size:1.8rem;"><?= htmlspecialchars($memberSince) ?></div>
                <div class="figure-meta"><span class="muted"><?= $accountAgeDays ?> days on the books</span></div>
            </div>
        </header>

        <?php if ($flashError): ?><div class="flash error"><?= htmlspecialchars($flashError) ?></div><?php endif; ?>
        <?php if ($flashSuccess): ?><div class="flash success"><?= htmlspecialchars($flashSuccess) ?></div><?php endif; ?>

        <div class="profile-grid">

            <!-- LEFT: identity card -->
            <section class="profile-card is-sticky">
                <div class="avatar-xl"><?= htmlspecialchars($initials) ?></div>
                <h2 class="profile-name"><?= htmlspecialchars($user['name']) ?></h2>
                <p class="profile-email"><?= htmlspecialchars($user['email']) ?></p>

                <ul class="meta-list">
                    <li><span class="k">Member since</span><span class="v"><?= htmlspecialchars($memberSince) ?></span></li>
                    <li><span class="k">Account age</span><span class="v"><?= $accountAgeDays ?> days</span></li>
                    <li><span class="k">User ID</span><span class="v">#<?= (int) $user['id'] ?></span></li>
                </ul>

                <div class="stat-row">
                    <div class="stat"><div class="n"><?= $txCount ?></div><div class="l">Transactions</div></div>
                    <div class="stat"><div class="n"><?= $budgetCount ?></div><div class="l">Budgets</div></div>
                    <div class="stat"><div class="n"><?= $accountAgeDays ?></div><div class="l">Days</div></div>
                </div>
            </section>

            <!-- RIGHT: forms + prefs — 2-up so the page fits without long scroll -->
            <div class="profile-right">

                <section class="profile-card">
                    <h3 class="section-title">Basic information</h3>
                    <form class="profile-form" method="POST" action="../api/update_profile.php" id="infoForm">
                        <input type="hidden" name="action" value="update_info">
                        <input type="hidden" name="_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                        <div class="pf-field">
                            <label for="name">Full name</label>
                            <input id="name" name="name" type="text" value="<?= htmlspecialchars($user['name']) ?>" required minlength="2">
                        </div>
                        <div class="pf-field">
                            <label for="email">Email</label>
                            <input id="email" name="email" type="email" value="<?= htmlspecialchars($user['email']) ?>" required>
                        </div>
                        <div class="pf-field">
                            <label>Member since</label>
                            <input value="<?= htmlspecialchars($memberSince) ?>" readonly>
                        </div>
                        <div class="pf-actions">
                            <button type="submit" class="btn btn-primary"><i class="fa-regular fa-floppy-disk"></i>&nbsp; Save changes</button>
                            <button type="reset" class="btn btn-ghost">Reset</button>
                        </div>
                    </form>
                </section>

                <section class="profile-card">
                    <h3 class="section-title">Security</h3>
                    <form class="profile-form" method="POST" action="../api/update_profile.php" id="pwForm">
                        <input type="hidden" name="action" value="change_password">
                        <input type="hidden" name="_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                        <div class="pf-field">
                            <label for="old_password">Current password</label>
                            <input id="old_password" name="old_password" type="password" required autocomplete="current-password">
                        </div>
                        <div class="pf-field">
                            <label for="new_password">New password</label>
                            <input id="new_password" name="new_password" type="password" required minlength="6" autocomplete="new-password">
                        </div>
                        <div class="pf-field">
                            <label for="confirm_password">Confirm new password</label>
                            <input id="confirm_password" name="confirm_password" type="password" required minlength="6" autocomplete="new-password">
                        </div>
                        <div class="pf-actions">
                            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-shield-halved"></i>&nbsp; Update password</button>
                        </div>
                    </form>
                </section>

                <section class="profile-card">
                    <h3 class="section-title">Financial Goals</h3>
                    <form class="profile-form" method="POST" action="../api/update_profile.php" id="goalsForm">
                        <input type="hidden" name="action" value="update_goals">
                        <input type="hidden" name="_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                        <div class="pf-field">
                            <label for="savings_goal">Monthly Savings Goal (₹)</label>
                            <input id="savings_goal" name="savings_goal" type="number" step="0.01" value="<?= htmlspecialchars($savings_goal) ?>" required>
                        </div>
                        <div class="pf-field">
                            <label for="run_rate_target">Annual Run-rate Target (₹)</label>
                            <input id="run_rate_target" name="run_rate_target" type="number" step="0.01" value="<?= htmlspecialchars($run_rate_target) ?>" required>
                        </div>
                        <div class="pf-actions">
                            <button type="submit" class="btn btn-primary"><i class="fa-regular fa-floppy-disk"></i>&nbsp; Save goals</button>
                        </div>
                    </form>
                </section>

                <section class="profile-card">
                    <h3 class="section-title">Preferences</h3>

                    <div class="pref-row">
                        <span class="k">Email notifications
                            <span class="pref-sub">Receipts for transactions, budgets, family and account changes</span>
                        </span>
                        <label class="switch" title="Email notifications">
                            <input type="checkbox" id="emailToggle" <?= $emailNotif ? 'checked' : '' ?>>
                            <span class="switch-track" aria-hidden="true"></span>
                            <span class="sr-only">Email notifications</span>
                        </label>
                    </div>

                    <div class="pref-row"><span class="k">Currency</span><span class="v">₹ · INR (locked)</span></div>
                    <div class="pref-row"><span class="k">Theme</span><span class="v" id="themeStatus">Dark</span></div>
                    <div class="theme-switch" role="radiogroup" aria-label="Color theme">
                        <button type="button" data-theme-option="dark" role="radio" aria-checked="false"><i class="fa-solid fa-moon"></i>Dark</button>
                        <button type="button" data-theme-option="light" role="radio" aria-checked="false"><i class="fa-solid fa-sun"></i>Light</button>
                        <button type="button" data-theme-option="system" role="radio" aria-checked="false"><i class="fa-solid fa-desktop"></i>System</button>
                    </div>
                    <p class="theme-hint">System follows your OS setting and updates automatically.</p>
                    <div class="pref-row"><span class="k">Date format</span><span class="v">DD MMM YYYY</span></div>
                </section>

            </div>
        </div>

    </main>
</div>

<script src="../assets/js/profile.js?v=<?php echo time(); ?>"></script>
</body>
</html>
