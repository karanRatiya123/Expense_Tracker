<?php
/**
 * ApexSpend — Auth handler (login + signup)
 * POST-only, MySQL-backed. Throttled against brute force (see
 * includes/login_guard.php). When the database is unreachable the request
 * fails with a clear flash — there is no in-session fallback store.
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/login_guard.php';
require_once __DIR__ . '/../includes/email_prefs.php';
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/../includes/welcome.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../pages/login.php');
    exit;
}

csrf_validate();

$action = $_POST['action'] ?? '';
$email = trim(strtolower($_POST['email'] ?? ''));
$password = $_POST['password'] ?? '';
$name = trim($_POST['name'] ?? '');
$ip = login_client_ip();

// ---- SIGNUP ----
if ($action === 'signup') {
    if ($name === '' || $email === '' || strlen($password) < 6) {
        $_SESSION['flash_error'] = 'Name, email, and a 6+ char password are required.';
        header('Location: ../pages/login.php?tab=signup');
        exit;
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['flash_error'] = 'That email looks off.';
        header('Location: ../pages/login.php?tab=signup');
        exit;
    }

    $hash = password_hash($password, PASSWORD_BCRYPT);
    $pdo = getDBConnection();

    if (!$pdo) {
        $_SESSION['flash_error'] = 'The database is unreachable right now. Please try again shortly.';
        header('Location: ../pages/login.php?tab=signup');
        exit;
    }

    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        $_SESSION['flash_error'] = 'An account with that email already exists.';
        header('Location: ../pages/login.php?tab=signup');
        exit;
    }
    // Prepare the welcome flag before inserting so the column is guaranteed
    // to exist (and existing accounts are already marked as seen).
    $welcomeReady = apex_welcome_ready($pdo);

    $ins = $welcomeReady
        ? $pdo->prepare('INSERT INTO users (name, email, password, welcome_seen) VALUES (?, ?, ?, 0)')
        : $pdo->prepare('INSERT INTO users (name, email, password) VALUES (?, ?, ?)');
    $ins->execute([$name, $email, $hash]);

    $_SESSION['flash_success'] = 'Account created. Sign in to continue.';
    header('Location: ../pages/login.php?tab=login');
    queue_welcome_email($name, $email);   // flushes the redirect, email goes out after
    exit;
}

// ---- LOGIN ----
if ($action === 'login') {
    if ($email === '' || $password === '') {
        $_SESSION['flash_error'] = 'Email and password are required.';
        header('Location: ../pages/login.php?tab=login');
        exit;
    }

    $pdo = getDBConnection();
    if (!$pdo) {
        $_SESSION['flash_error'] = 'The database is unreachable right now. Please try again shortly.';
        header('Location: ../pages/login.php?tab=login');
        exit;
    }

    if (login_throttled($pdo, $email, $ip)) {
        $_SESSION['flash_error'] = 'Too many failed attempts. Try again in 15 minutes.';
        error_log("ApexSpend login: throttled attempt for {$email} from {$ip}");
        header('Location: ../pages/login.php?tab=login');
        exit;
    }

    $user = null;
    $stmt = $pdo->prepare('SELECT id, name, email, password FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $row = $stmt->fetch();
    if ($row && password_verify($password, $row['password'])) {
        $user = ['id' => $row['id'], 'name' => $row['name'], 'email' => $row['email']];
    }

    if (!$user) {
        login_record_failure($pdo, $email, $ip);
        error_log("ApexSpend login: failed attempt for {$email} from {$ip}");
        $_SESSION['flash_error'] = 'Wrong email or password.';
        header('Location: ../pages/login.php?tab=login');
        exit;
    }

    login_clear_failures($pdo, $email, $ip);

    session_regenerate_id(true);
    $user['email_notifications'] = email_notifications_enabled($pdo, (int) $user['id']) ? 1 : 0;
    $_SESSION['user'] = $user;
    header('Location: ../pages/dashboard.php');
    exit;
}

header('Location: ../pages/login.php');
exit;
