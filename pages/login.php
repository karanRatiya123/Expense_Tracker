<?php
require_once __DIR__ . '/../config.php';

// Already signed in? Bounce to dashboard.
if (isset($_SESSION['user'])) {
    header('Location: dashboard.php');
    exit;
}

$loginError = getFlashMessage('error');
$signupError = getFlashMessage('error');
$signupSuccess = getFlashMessage('success');
$initialTab = $_GET['tab'] ?? 'login';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="dark light">
    <script>(function(){try{var m=localStorage.getItem('apex-theme')||'dark';var r=m==='system'?(window.matchMedia('(prefers-color-scheme: light)').matches?'light':'dark'):m;document.documentElement.setAttribute('data-theme',r);document.documentElement.setAttribute('data-theme-mode',m);}catch(e){document.documentElement.setAttribute('data-theme','dark');}})();</script>
    <title>ApexSpend — Sign in</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=Geist:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css?v=<?php echo time(); ?>">
    <script src="../assets/js/theme.js?v=<?php echo time(); ?>"></script>
    <style>
        body.auth {
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 2rem;
            background: var(--background);
        }
        .auth-card {
            width: 100%;
            max-width: 420px;
            background: var(--card);
            color: var(--card-foreground);
            border: 1px solid var(--border);
            border-radius: calc(var(--radius) * 1.6);
            padding: 2.25rem 2rem 1.75rem;
            box-shadow: 0 20px 60px -20px rgb(0 0 0 / .5);
        }
        .auth-brand {
            font-family: var(--font-serif);
            font-size: 1.75rem;
            font-style: italic;
            margin: 0 0 .25rem;
            letter-spacing: -.01em;
        }
        .auth-tag {
            color: var(--muted-foreground);
            font-size: .9rem;
            margin: 0 0 1.5rem;
        }
        .auth-tabs {
            display: flex;
            gap: .25rem;
            padding: .25rem;
            background: var(--muted);
            border-radius: var(--radius);
            margin-bottom: 1.5rem;
        }
        .auth-tab {
            flex: 1;
            text-align: center;
            padding: .55rem 0;
            font-size: .9rem;
            font-weight: 500;
            color: var(--muted-foreground);
            background: transparent;
            border: 0;
            border-radius: calc(var(--radius) * .8);
            cursor: pointer;
            transition: background .15s, color .15s;
        }
        .auth-tab[aria-selected="true"] {
            background: var(--card);
            color: var(--foreground);
            box-shadow: 0 1px 2px rgb(0 0 0 / .15);
        }
        .auth-panel { display: none; }
        .auth-panel.active { display: block; }
        .field { display: block; margin-bottom: 1rem; }
        .field label {
            display: block;
            font-size: .8rem;
            font-weight: 500;
            margin-bottom: .35rem;
            color: var(--muted-foreground);
        }
        .field input {
            width: 100%;
            padding: .65rem .8rem;
            font: inherit;
            color: var(--foreground);
            background: var(--input);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            outline: none;
            transition: border-color .15s, box-shadow .15s;
        }
        .field input:focus {
            border-color: var(--ring);
            box-shadow: 0 0 0 3px rgb(255 255 255 / .08);
        }
        .auth-submit {
            width: 100%;
            padding: .7rem;
            font: inherit;
            font-weight: 600;
            color: var(--primary-foreground);
            background: var(--primary);
            border: 0;
            border-radius: var(--radius);
            cursor: pointer;
            margin-top: .25rem;
        }
        .auth-submit:hover { opacity: .9; }
        .auth-foot {
            margin-top: 1.25rem;
            text-align: center;
            font-size: .85rem;
            color: var(--muted-foreground);
        }
        .auth-foot button {
            background: none;
            border: 0;
            color: var(--foreground);
            font-weight: 500;
            cursor: pointer;
            padding: 0;
            text-decoration: underline;
            text-underline-offset: 3px;
        }
        .auth-msg {
            padding: .55rem .75rem;
            border-radius: var(--radius);
            font-size: .85rem;
            margin-bottom: 1rem;
        }
        .auth-msg.error { background: rgb(255 99 99 / .12); color: var(--destructive); }
        .auth-msg.success { background: rgb(80 200 120 / .12); color: #4ade80; }
        .auth-hint {
            margin-top: 1rem;
            padding: .65rem .8rem;
            font-size: .8rem;
            color: var(--muted-foreground);
            background: var(--muted);
            border-radius: var(--radius);
        }
        .auth-divider {
            position: relative;
            text-align: center;
            margin: 1.5rem 0;
        }
        .auth-divider::before {
            content: "";
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            border-top: 1px solid var(--border);
            z-index: 0;
        }
        .auth-divider span {
            position: relative;
            z-index: 1;
            background: var(--card);
            padding: 0 .75rem;
            color: var(--muted-foreground);
            font-size: .8rem;
            font-weight: 500;
        }
        .social-logins {
            display: flex;
            gap: .5rem;
            margin-bottom: 1rem;
        }
        .social-btn {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: .5rem;
            padding: .65rem;
            background: var(--card);
            color: var(--foreground);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            font-size: .9rem;
            font-weight: 500;
            cursor: pointer;
            transition: background .15s, border-color .15s;
        }
        .social-btn:hover {
            background: var(--muted);
            border-color: var(--ring);
        }
    </style>
</head>
<body class="auth">
    <main class="auth-card">
        <h1 class="auth-brand">ApexSpend</h1>
        <p class="auth-tag">The ledger for the money you actually keep.</p>

        <div class="auth-tabs" role="tablist">
            <button class="auth-tab" role="tab" data-tab="login" aria-selected="<?= $initialTab === 'login' ? 'true' : 'false' ?>">Sign in</button>
            <button class="auth-tab" role="tab" data-tab="signup" aria-selected="<?= $initialTab === 'signup' ? 'true' : 'false' ?>">Create account</button>
        </div>

        <!-- LOGIN -->
        <form class="auth-panel <?= $initialTab === 'login' ? 'active' : '' ?>" data-panel="login" method="POST" action="../api/auth.php" novalidate>
            <input type="hidden" name="action" value="login">
            <input type="hidden" name="_token" value="<?= htmlspecialchars(csrf_token()) ?>">
            <?php if ($loginError && $initialTab === 'login'): ?>
                <div class="auth-msg error"><?= htmlspecialchars($loginError) ?></div>
            <?php endif; ?>
            <label class="field">
                <label>Email</label>
                <input type="email" name="email" required autocomplete="email" placeholder="you@apexspend.com">
            </label>
            <label class="field">
                <label>Password</label>
                <input type="password" name="password" required autocomplete="current-password" placeholder="••••••••">
            </label>
            <button type="submit" class="auth-submit">Sign in</button>
            <div class="auth-divider"><span>Or continue with</span></div>
            <div class="social-logins">
                <button type="button" class="social-btn" onclick="window.location.href='oauth.php?provider=google'">
                    <i class="fab fa-google"></i> Google
                </button>
                <button type="button" class="social-btn" onclick="window.location.href='oauth.php?provider=github'">
                    <i class="fab fa-github"></i> GitHub
                </button>
            </div>
            <div class="auth-hint">
                Demo: <code>demo@apexspend.com</code> / <code>password123</code>
            </div>
        </form>

        <!-- SIGNUP -->
        <form class="auth-panel <?= $initialTab === 'signup' ? 'active' : '' ?>" data-panel="signup" method="POST" action="../api/auth.php" novalidate>
            <input type="hidden" name="action" value="signup">
            <input type="hidden" name="_token" value="<?= htmlspecialchars(csrf_token()) ?>">
            <?php if ($signupError && $initialTab === 'signup'): ?>
                <div class="auth-msg error"><?= htmlspecialchars($signupError) ?></div>
            <?php elseif ($signupSuccess): ?>
                <div class="auth-msg success"><?= htmlspecialchars($signupSuccess) ?></div>
            <?php endif; ?>
            <label class="field">
                <label>Full name</label>
                <input type="text" name="name" required autocomplete="name" minlength="2" placeholder="Alex Morgan">
            </label>
            <label class="field">
                <label>Email</label>
                <input type="email" name="email" required autocomplete="email" placeholder="you@apexspend.com">
            </label>
            <label class="field">
                <label>Password</label>
                <input type="password" name="password" required autocomplete="new-password" minlength="6" placeholder="At least 6 characters">
            </label>
            <button type="submit" class="auth-submit">Create account</button>
            <div class="auth-divider"><span>Or continue with</span></div>
            <div class="social-logins">
                <button type="button" class="social-btn" onclick="window.location.href='oauth.php?provider=google'">
                    <i class="fab fa-google"></i> Google
                </button>
                <button type="button" class="social-btn" onclick="window.location.href='oauth.php?provider=github'">
                    <i class="fab fa-github"></i> GitHub
                </button>
            </div>
        </form>

        <p class="auth-foot" data-foot="login" style="<?= $initialTab === 'login' ? '' : 'display:none' ?>">
            New here? <button type="button" data-switch="signup">Create an account</button>
        </p>
        <p class="auth-foot" data-foot="signup" style="<?= $initialTab === 'signup' ? '' : 'display:none' ?>">
            Already have one? <button type="button" data-switch="login">Sign in</button>
        </p>
    </main>

    <script>
        const tabs = document.querySelectorAll('.auth-tab');
        const panels = document.querySelectorAll('.auth-panel');
        const feet = document.querySelectorAll('[data-foot]');
        function switchTab(name) {
            tabs.forEach(t => t.setAttribute('aria-selected', t.dataset.tab === name ? 'true' : 'false'));
            panels.forEach(p => p.classList.toggle('active', p.dataset.panel === name));
            feet.forEach(f => f.style.display = f.dataset.foot === name ? '' : 'none');
            history.replaceState(null, '', '?tab=' + name);
        }
        tabs.forEach(t => t.addEventListener('click', () => switchTab(t.dataset.tab)));
        document.querySelectorAll('[data-switch]').forEach(b => b.addEventListener('click', () => switchTab(b.dataset.switch)));
    </script>
</body>
</html>
