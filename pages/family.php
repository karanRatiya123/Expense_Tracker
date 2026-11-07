<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/categories.php';

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$user = $_SESSION['user'];
$initials = strtoupper(substr($user['name'], 0, 1));
$activePage = 'family';
$first_name = htmlspecialchars(explode(' ', $user['name'])[0]);

function format_inr($n) {
    return number_format((float)$n, 2, '.', ',');
}

// Current family = most recent active membership (simple: one family at a time).
$family = null;
$members = [];
$shared = [];
$pdo = getDBConnection();
$apexCategories = [];
if ($pdo) {
    apex_load_category_registry($pdo, $user['id']);
    $apexCategories = apex_get_categories($pdo, $user['id']);
    try {
        $st = $pdo->prepare("SELECT f.* FROM families f JOIN family_members m ON m.family_id = f.id WHERE m.user_id = ? AND m.status = 'active' ORDER BY m.joined_at DESC LIMIT 1");
        $st->execute([$user['id']]);
        $family = $st->fetch() ?: null;
        if ($family) {
            $ms = $pdo->prepare("SELECT u.id, u.name, u.email, m.role, m.joined_at FROM family_members m JOIN users u ON u.id = m.user_id WHERE m.family_id = ? AND m.status = 'active' ORDER BY (m.role = 'admin') DESC, m.joined_at ASC");
            $ms->execute([$family['id']]);
            $members = $ms->fetchAll();
            $ids = array_column($members, 'id');
            if ($ids) {
                $ph = implode(',', array_fill(0, count($ids), '?'));
                $tx = $pdo->prepare("SELECT t.*, u.name AS member_name FROM transactions t JOIN users u ON u.id = t.user_id WHERE t.user_id IN ($ph) ORDER BY t.date DESC LIMIT 60");
                $tx->execute($ids);
                $shared = $tx->fetchAll();
            }
        }
    } catch (PDOException $e) {
        $family = null;
        $members = [];
        $shared = [];
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
    <title>ApexSpend — Family Space</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=Geist+Mono:wght@400;500&family=Geist:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css?v=<?php echo time(); ?>">
    <script src="../assets/js/theme.js?v=<?php echo time(); ?>"></script>
</head>
<body>

<?php if (!$family): ?>
<!-- =================== FULLSCREEN LOCK (default locked) =================== -->
<div class="fam-lock" id="famLockScreen" role="dialog" aria-modal="true" aria-label="Family space locked">
    <div class="fam-lock-inner">
        <div class="kicker" style="justify-content:center;">
            <span class="kicker-num">§ 05</span>
            <span class="kicker-text">Family Space</span>
        </div>
        <span class="fam-veil-ic"><i class="fa-solid fa-lock" aria-hidden="true"></i></span>
        <h2>Locked.</h2>
        <p>Nothing is visible here. Join a family<br>or create one to unlock.</p>
        <div class="fam-veil-actions">
            <button type="button" class="ghost" onclick="openFamilyModal('join')">
                <i class="fa-solid fa-key" aria-hidden="true"></i>
                <span>Join family</span>
            </button>
            <button type="button" class="add" onclick="openFamilyModal('create')">
                <i class="fa-solid fa-plus" aria-hidden="true"></i>
                <span>Create family</span>
            </button>
        </div>
        <button type="button" class="fam-lock-peek" onclick="closeFamLock()">Peek behind · UI only</button>
    </div>
</div>
<?php endif; ?>

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
                    <span class="label">Family <?php if (!$family): ?><span class="lock-tag" title="Locked"><i class="fa-solid fa-lock" aria-hidden="true"></i></span><?php endif; ?></span>
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

<?php if ($family): ?>
        <!-- =================== UNLOCKED: FAMILY HOME =================== -->
        <header class="masthead">
            <div class="masthead-col">
                <div class="kicker">
                    <span class="kicker-num">§ 05</span>
                    <span class="kicker-text">Family Space · Unlocked</span>
                </div>
                <h1><?php echo htmlspecialchars($family['name']); ?><br><em>shared ledger.</em></h1>
                <p class="standfirst">Every member's entries in one place — each line shows who spent it.</p>
            </div>

            <div class="masthead-figure">
                <div class="figure-label">Invite code · <?php echo count($members); ?> member<?php echo count($members) === 1 ? '' : 's'; ?></div>
                <div class="figure-value fam-code-value">
                    <span id="famInviteCode"><?php echo htmlspecialchars($family['invite_code']); ?></span>
                </div>
                <div class="figure-meta">
                    <span class="muted">Share it to add members</span>
                </div>
                <div class="actions">
                    <button type="button" class="ghost" onclick="copyInviteCode()">
                        <i class="fa-regular fa-copy" aria-hidden="true"></i>
                        <span>Copy code</span>
                    </button>
                    <button type="button" class="ghost danger-ghost" onclick="leaveFamily()">
                        <i class="fa-solid fa-arrow-right-from-bracket" aria-hidden="true"></i>
                        <span>Leave</span>
                    </button>
                </div>
            </div>
        </header>

        <!-- =================== MEMBERS =================== -->
        <section class="section" aria-label="Family members">
            <div class="section-head">
                <h2><i class="fa-solid fa-users section-ic" aria-hidden="true"></i> Members.</h2>
                <div class="section-meta">
                    <span><?php echo count($members); ?> on file</span>
                </div>
            </div>
            <div class="fam-members">
                <?php foreach ($members as $m):
                    $isYou = (int)$m['id'] === (int)$user['id'];
                    $mi = strtoupper(substr($m['name'], 0, 1));
                ?>
                <div class="fam-member is-clickable" role="button" tabindex="0"
                     title="View <?php echo htmlspecialchars($m['name']); ?>"
                     onclick="openMemberModal(<?php echo (int)$m['id']; ?>)"
                     onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();openMemberModal(<?php echo (int)$m['id']; ?>);}">
                    <span class="fam-avatar"><?php echo htmlspecialchars($mi); ?></span>
                    <div class="fam-member-meta">
                        <strong><?php echo htmlspecialchars($m['name']); ?><?php if ($isYou): ?> <span class="you-tag">you</span><?php endif; ?></strong>
                        <span><?php echo htmlspecialchars($m['email']); ?></span>
                    </div>
                    <?php if ($m['role'] === 'admin'): ?>
                        <span class="role-pill admin"><i class="fa-solid fa-crown" aria-hidden="true"></i> Admin</span>
                    <?php else: ?>
                        <span class="role-pill">Member</span>
                    <?php endif; ?>
                    <i class="fa-solid fa-chevron-right fam-view" aria-hidden="true"></i>
                </div>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- =================== SHARED HEATMAP =================== -->
        <section class="section heatmap-section" aria-label="Shared spending heatmap">
            <div class="section-head">
                <h2><i class="fa-solid fa-grip section-ic" aria-hidden="true"></i> Spending heatmap.</h2>
                <div class="section-meta">
                    <span>Last 12 months</span>
                    <span aria-hidden="true">·</span>
                    <span id="heatDayCount">— active days</span>
                </div>
            </div>

            <div class="fam-heat-filters" role="tablist" aria-label="Heatmap by member">
                <button type="button" class="is-on" data-member="all">All</button>
                <?php foreach ($members as $m): ?>
                    <button type="button" data-member="<?php echo (int)$m['id']; ?>"><?php echo htmlspecialchars(explode(' ', $m['name'])[0]); ?></button>
                <?php endforeach; ?>
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
                        <div class="heatmap-grid" id="heatmapGrid" role="grid" aria-label="Shared spending by day"></div>
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

        <!-- =================== SHARED LEDGER =================== -->
        <section class="section" aria-label="Shared transactions">
            <div class="section-head">
                <h2><i class="fa-solid fa-book section-ic" aria-hidden="true"></i> Shared ledger.</h2>
                <div class="section-meta">
                    <span><?php echo count($shared); ?> entries</span>
                    <span aria-hidden="true">·</span>
                    <span>Latest 60</span>
                </div>
            </div>

            <div class="ledger">
                <div class="led-grid shared head" aria-hidden="true">
                    <span class="col-label">When</span>
                    <span class="col-label">Entry &amp; who</span>
                    <span class="col-label">Category</span>
                    <span class="col-label col-num">Method</span>
                    <span class="col-label col-num">Amount</span>
                </div>
                <div id="ledgerBody">
                <?php foreach ($shared as $t):
                    $isIncome = $t['type'] === 'income';
                    $sign = $isIncome ? '+' : '−';
                    $cls = $isIncome ? 'amount pos' : 'amount';
                ?>
                    <div class="led-grid shared">
                        <span class="when"><span class="day"><?php echo date('d M', strtotime($t['date'])); ?></span><?php echo date('H:i', strtotime($t['date'])); ?></span>
                        <span class="col-merch"><strong><?php echo htmlspecialchars($t['title']); ?></strong><?php echo htmlspecialchars($t['member_name']); ?> · <?php echo htmlspecialchars($t['note'] ?? ''); ?></span>
                        <span class="cat"><i class="fa-solid <?php echo apex_category_icon($t['category']); ?>" aria-hidden="true"></i> <?php echo htmlspecialchars($t['category']); ?></span>
                        <span class="method"><?php echo htmlspecialchars($t['method'] ?? '—'); ?></span>
                        <span class="amount-wrap"><span class="<?php echo $cls; ?>"><?php echo $sign; ?> ₹<?php echo format_inr($t['amount']); ?></span></span>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($shared)): ?>
                    <p style="padding:20px;color:var(--whisper);">No shared entries yet. Members' entries appear here.</p>
                <?php endif; ?>
                </div>
                <div class="ledger-foot">
                    <span class="filing">Filed <?php echo date('d M Y'); ?> — <?php echo htmlspecialchars($family['name']); ?></span>
                    <span class="muted">End of entries.</span>
                </div>
            </div>
        </section>

        <footer class="analytics-foot">Filed under § 05 · <?php echo htmlspecialchars($family['name']); ?> · <?php echo count($members); ?> members.</footer>

<?php else: ?>
        <!-- =================== LOCKED =================== -->
        <header class="masthead">
            <div class="masthead-col">
                <div class="kicker">
                    <span class="kicker-num">§ 05</span>
                    <span class="kicker-text">Family Space</span>
                </div>
                <h1>Shared money,<br><em>still private.</em></h1>
                <p class="standfirst">One ledger for the household. This space stays locked until you join a family or create one.</p>
            </div>

            <div class="masthead-figure">
                <div class="figure-label">Space status</div>
                <div class="figure-value fam-locked-value">
                    <i class="fa-solid fa-lock" aria-hidden="true"></i><span>Locked</span>
                </div>
                <div class="figure-meta">
                    <span class="muted"><?php echo $first_name; ?> · no family on file</span>
                </div>
                <div class="actions">
                    <button type="button" class="ghost" onclick="document.getElementById('famJoinCode').focus()">
                        <i class="fa-solid fa-key" aria-hidden="true"></i>
                        <span>Enter code</span>
                    </button>
                    <button type="button" class="add" onclick="openFamilyModal('create')">
                        <i class="fa-solid fa-plus" aria-hidden="true"></i>
                        <span>New family</span>
                    </button>
                </div>
            </div>
        </header>

        <div class="fam-notice" role="status">
            <span class="fam-notice-ic"><i class="fa-solid fa-lock" aria-hidden="true"></i></span>
            <div class="fam-notice-body">
                <strong>This space is locked by default.</strong>
                <span>Join with an invite code, or create a new family.</span>
            </div>
            <span class="kicker-num">LOCKED</span>
        </div>

        <section class="section" aria-label="Join or create a family">
            <div class="section-head">
                <h2><i class="fa-solid fa-users section-ic" aria-hidden="true"></i> Two doors in.</h2>
                <div class="section-meta">
                    <span>Pick one</span>
                    <span aria-hidden="true">·</span>
                    <span>Locked</span>
                </div>
            </div>

            <div class="fam-grid">
                <article class="fam-card" aria-labelledby="famJoinTitle">
                    <div class="fam-card-top">
                        <span class="cell-num">01</span>
                        <span class="fam-pill"><i class="fa-solid fa-key" aria-hidden="true"></i> Invite code</span>
                    </div>
                    <div class="fam-card-ic"><i class="fa-solid fa-arrow-right-to-bracket" aria-hidden="true"></i></div>
                    <h3 id="famJoinTitle">Join family</h3>
                    <p>Got a code from a member? Enter the 6-character code to join their shared ledger.</p>
                    <form onsubmit="return familyCardJoin(event)">
                        <div class="field">
                            <div class="field-row"><label for="famJoinCode">Invite code</label></div>
                            <div class="input-shell fam-code-shell">
                                <i class="fa-solid fa-ticket" aria-hidden="true"></i>
                                <input type="text" id="famJoinCode" maxlength="6" placeholder="e.g. K7X2QD" autocomplete="off" spellcheck="false">
                            </div>
                            <span class="fam-hint">6 characters · letters and numbers</span>
                        </div>
                        <button type="submit" class="submit fam-submit">
                            <i class="fa-solid fa-arrow-right-to-bracket" aria-hidden="true"></i>
                            <span>Join family</span>
                        </button>
                    </form>
                    <div class="fam-card-foot">
                        <span><i class="fa-solid fa-lock" aria-hidden="true"></i> Wrong code? Ask the member to re-share</span>
                    </div>
                </article>

                <article class="fam-card is-accent" aria-labelledby="famCreateTitle">
                    <div class="fam-card-top">
                        <span class="cell-num">02</span>
                        <span class="fam-pill solid"><i class="fa-solid fa-plus" aria-hidden="true"></i> New space</span>
                    </div>
                    <div class="fam-card-ic"><i class="fa-solid fa-house-chimney-user" aria-hidden="true"></i></div>
                    <h3 id="famCreateTitle">Create family</h3>
                    <p>Start a new household ledger. You become the admin and get an invite code to share.</p>
                    <form onsubmit="return familyCardCreate(event)">
                        <div class="field">
                            <div class="field-row"><label for="famCreateName">Family name</label></div>
                            <div class="input-shell">
                                <i class="fa-solid fa-users" aria-hidden="true"></i>
                                <input type="text" id="famCreateName" maxlength="40" placeholder="e.g. Sharma Household" autocomplete="off">
                            </div>
                            <span class="fam-hint">2–40 characters · you can rename later</span>
                        </div>
                        <button type="submit" class="submit fam-submit">
                            <i class="fa-solid fa-plus" aria-hidden="true"></i>
                            <span>Create family</span>
                        </button>
                    </form>
                    <div class="fam-card-foot">
                        <span><i class="fa-solid fa-crown" aria-hidden="true"></i> You become admin</span>
                    </div>
                </article>
            </div>
        </section>

        <section class="section" aria-label="Locked preview">
            <div class="section-head">
                <h2><i class="fa-solid fa-eye-slash section-ic" aria-hidden="true"></i> Behind the lock.</h2>
                <div class="section-meta">
                    <span>Preview</span>
                    <span aria-hidden="true">·</span>
                    <span>Blurred</span>
                </div>
            </div>

            <div class="fam-vault is-fullscreen">
                <div class="fam-vault-rows" aria-hidden="true">
                    <div class="fam-row"><span class="fam-dot"></span><span class="fam-line w-40"></span><span class="fam-line w-24"></span><span class="fam-line w-16"></span></div>
                    <div class="fam-row"><span class="fam-dot"></span><span class="fam-line w-32"></span><span class="fam-line w-20"></span><span class="fam-line w-16"></span></div>
                    <div class="fam-row"><span class="fam-dot"></span><span class="fam-line w-44"></span><span class="fam-line w-16"></span><span class="fam-line w-20"></span></div>
                    <div class="fam-row"><span class="fam-dot"></span><span class="fam-line w-24"></span><span class="fam-line w-40"></span><span class="fam-line w-20"></span></div>
                    <div class="fam-row"><span class="fam-dot"></span><span class="fam-line w-32"></span><span class="fam-line w-16"></span><span class="fam-line w-24"></span></div>
                    <div class="fam-row"><span class="fam-dot"></span><span class="fam-line w-44"></span><span class="fam-line w-20"></span><span class="fam-line w-16"></span></div>
                </div>
                <div class="fam-vault-veil">
                    <span class="fam-veil-ic"><i class="fa-solid fa-lock" aria-hidden="true"></i></span>
                    <strong>Members, shared budgets and ledger live here.</strong>
                    <span>Unlock by joining or creating a family.</span>
                    <div class="fam-veil-actions">
                        <button type="button" class="ghost" onclick="gotoFamilyJoin()">
                            <i class="fa-solid fa-key" aria-hidden="true"></i>
                            <span>Join family</span>
                        </button>
                        <button type="button" class="add" onclick="openFamilyModal('create')">
                            <i class="fa-solid fa-plus" aria-hidden="true"></i>
                            <span>Create family</span>
                        </button>
                    </div>
                </div>
            </div>
        </section>

        <section class="kpi variant-three fam-steps" aria-label="How family space works">
            <div class="cell">
                <div class="cell-num">01</div>
                <div class="label">Create or join</div>
                <div class="fam-step">One code per family.</div>
                <div class="meta"><span class="muted">Admin creates, others join</span></div>
            </div>
            <div class="cell">
                <div class="cell-num">02</div>
                <div class="label">Invite members</div>
                <div class="fam-step">Share the 6-char code.</div>
                <div class="meta"><span class="muted">Wrong code can't join</span></div>
            </div>
            <div class="cell">
                <div class="cell-num">03</div>
                <div class="label">Share the ledger</div>
                <div class="fam-step">Entries unlock together.</div>
                <div class="meta"><span class="muted">Locked until then</span></div>
            </div>
        </section>

        <footer class="analytics-foot">Filed under § 05 · Family Space · Locked by default.</footer>
<?php endif; ?>

    </main>
</div>

<!-- =================== JOIN / CREATE MODAL =================== -->
<div class="scrim" id="familyModal" aria-hidden="true">
    <div class="sheet" role="dialog" aria-labelledby="familySheetTitle">
        <div class="sheet-head">
            <div>
                <span class="kicker-num" id="familyModalKicker">§ 05 · Create</span>
                <h3 id="familySheetTitle">Name your family</h3>
            </div>
            <button type="button" class="close" onclick="closeFamilyModal()" aria-label="Close">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <form onsubmit="return familyModalSubmit(event)">
            <div id="famStep1">
                <div class="field" id="famModalNameWrap">
                    <div class="field-row"><label for="famModalName">Family name</label></div>
                    <div class="input-shell">
                        <i class="fa-solid fa-users" aria-hidden="true"></i>
                        <input type="text" id="famModalName" maxlength="40" placeholder="e.g. Sharma Household">
                    </div>
                </div>
                <div class="field" id="famModalCodeWrap" hidden>
                    <div class="field-row"><label for="famModalCodeInput">Invite code</label></div>
                    <div class="input-shell fam-code-shell">
                        <i class="fa-solid fa-ticket" aria-hidden="true"></i>
                        <input type="text" id="famModalCodeInput" maxlength="6" placeholder="e.g. K7X2QD" autocomplete="off" spellcheck="false">
                    </div>
                    <span class="fam-hint">6 characters · letters and numbers</span>
                </div>
                <button type="submit" class="submit" id="famModalSubmit">
                    <i class="fa-solid fa-plus" aria-hidden="true"></i>
                    <span id="famModalSubmitLabel">Create family</span>
                </button>
            </div>
            <div id="famStep2" hidden>
                <div class="fam-modal-code" id="famStep2CodeBlock">
                    <span class="fam-modal-code-label" id="famStep2Label">Your invite code — share it</span>
                    <div class="fam-modal-code-row">
                        <strong id="famModalCode">——————</strong>
                        <button type="button" class="icon-btn" onclick="copyFamilyCode()" aria-label="Copy code">
                            <i class="fa-regular fa-copy" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>
                <p class="fam-step2-text" id="famStep2Text"></p>
                <button type="button" class="submit" onclick="window.location.reload()">
                    <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                    <span>Continue</span>
                </button>
            </div>
            <p class="fam-modal-note" id="famModalNote">You become admin · members join with your code.</p>
        </form>
    </div>
</div>

<!-- =================== MEMBER DETAIL MODAL =================== -->
<div class="scrim" id="memberModal" aria-hidden="true">
    <div class="sheet mem-sheet" role="dialog" aria-labelledby="memName">
        <div class="sheet-head">
            <div class="mem-head">
                <span class="fam-avatar mem-avatar" id="memAvatar">?</span>
                <div>
                    <span class="kicker-num" id="memRole">Member</span>
                    <h3 id="memName">—</h3>
                    <span class="mem-email" id="memEmail">—</span>
                </div>
            </div>
            <button type="button" class="close" onclick="closeMemberModal()" aria-label="Close">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="mem-stats" id="memStats" aria-label="Member totals"></div>
        <div class="mem-txhead"><span>Entries</span><span id="memTxCount" class="mono muted"></span></div>
        <div class="mem-txlist" id="memTxList"></div>
        <div class="form-actions">
            <button type="button" class="submit danger" id="memRemoveBtn" hidden onclick="removeMember()">
                <i class="fa-regular fa-trash-can" aria-hidden="true"></i>
                <span>Remove member</span>
            </button>
            <button type="button" class="submit" onclick="closeMemberModal()">
                <span>Done</span>
            </button>
        </div>
    </div>
</div>

<div id="toastContainer" class="toast-stack" aria-live="polite"></div>
<script>
    window.APEX_TOKEN = <?php echo json_encode(csrf_token()); ?>;
    window.APEX_CATEGORIES = <?php echo json_encode($apexCategories); ?>;
</script>
<?php if ($family):
    $myRole = 'member';
    foreach ($members as $mm) {
        if ((int)$mm['id'] === (int)$user['id']) { $myRole = $mm['role']; break; }
    }
?>
<script>
    window.FAM_ID = <?php echo json_encode($family['id']); ?>;
    window.FAM_USER_ID = <?php echo (int)$user['id']; ?>;
    window.FAM_IS_ADMIN = <?php echo $myRole === 'admin' ? 'true' : 'false'; ?>;
</script>
<?php endif; ?>
<?php if ($family): ?>
<script>
    // Shared entries feed the heatmap — rebuilt from server on every load,
    // so new transactions always show up. Relative scaling spreads colors
    // across this family's actual spending range.
    window.heatmapRelative = true;
    window.heatmapMemberFilter = 'all';
    const serverTransactions = <?php echo json_encode($shared); ?>;
</script>
<?php endif; ?>
<script src="../assets/js/category-helper.js?v=<?php echo time(); ?>"></script>
<script src="../assets/js/script.js?v=<?php echo time(); ?>"></script>
<?php if ($family): ?>
<script src="../assets/js/app.js?v=<?php echo time(); ?>"></script>
<?php endif; ?>
<script src="../assets/js/family.js?v=<?php echo time(); ?>"></script>
</body>
</html>
