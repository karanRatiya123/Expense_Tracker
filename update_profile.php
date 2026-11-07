<?php
/**
 * ApexSpend — Profile update handler
 * POST-only. Actions: update_info, change_password, update_goals.
 * MySQL-backed — when the DB is unreachable the request fails with a clear
 * flash (there is no in-session fallback store; see config.php).
 */
require_once 'config.php';
require_once 'includes/mailer.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

csrf_validate();

$pdo = getDBConnection();
if (!$pdo) {
    $_SESSION['flash_error'] = 'The database is unreachable right now. Please try again shortly.';
    header('Location: login.php');
    exit;
}

$action = $_POST['action'] ?? '';
$userId = (int) $_SESSION['user']['id'];

/** Apply a whitelisted-column row update on users. Columns are hard-coded per action, never from input. */
function apply_user_update(PDO $pdo, int $userId, array $fields): void {
    $sets = [];
    $vals = [];
    foreach ($fields as $col => $val) {
        $sets[] = "$col = ?";
        $vals[] = $val;
    }
    $vals[] = $userId;
    $pdo->prepare('UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($vals);
}

// ---- UPDATE NAME / EMAIL ----
if ($action === 'update_info') {
    $name = trim($_POST['name'] ?? '');
    $email = trim(strtolower($_POST['email'] ?? ''));

    if ($name === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($name) > 100) {
        $_SESSION['flash_error'] = 'Name (max 100 chars) and a valid email are required.';
        header('Location: profile.php');
        exit;
    }

    // Block duplicate email (someone else's)
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id <> ?');
    $stmt->execute([$email, $userId]);
    if ($stmt->fetch()) {
        $_SESSION['flash_error'] = 'That email is already in use.';
        header('Location: profile.php');
        exit;
    }

    apply_user_update($pdo, $userId, ['name' => $name, 'email' => $email]);
    $_SESSION['user']['name'] = $name;
    $_SESSION['user']['email'] = $email;
    // Identity changed for this user: fresh session id, same session data.
    session_regenerate_id(true);
    $_SESSION['flash_success'] = 'Profile updated.';

    header('Location: profile.php');
    defer_notification_email('profile_updated', ['name' => $name, 'email' => $email]);
    exit;
}

// ---- CHANGE PASSWORD ----
if ($action === 'change_password') {
    $old = $_POST['old_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (strlen($new) < 6 || $new !== $confirm) {
        $_SESSION['flash_error'] = 'New password must be 6+ characters and match the confirmation.';
        header('Location: profile.php');
        exit;
    }

    $stmt = $pdo->prepare('SELECT password FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $hash = $stmt->fetchColumn();
    if (!$hash || !password_verify($old, (string) $hash)) {
        $_SESSION['flash_error'] = 'Current password is incorrect.';
        header('Location: profile.php');
        exit;
    }

    $newHash = password_hash($new, PASSWORD_BCRYPT);
    apply_user_update($pdo, $userId, ['password' => $newHash]);
    // A password change invalidates old credentials anywhere: refresh the
    // session id so a stolen pre-change session cookie no longer matches.
    session_regenerate_id(true);
    $_SESSION['flash_success'] = 'Password changed.';

    header('Location: profile.php');
    defer_notification_email('password_changed', []);
    exit;
}

// ---- UPDATE GOALS ----
if ($action === 'update_goals') {
    $savings_goal = money_amount($_POST['savings_goal'] ?? 0);
    $run_rate_target = money_amount($_POST['run_rate_target'] ?? 0);

    if ($savings_goal <= 0 || $run_rate_target <= 0) {
        $_SESSION['flash_error'] = 'Goals must be positive numbers (max 99,999,999.99).';
        header('Location: profile.php');
        exit;
    }

    apply_user_update($pdo, $userId, ['savings_goal' => $savings_goal, 'run_rate_target' => $run_rate_target]);
    $_SESSION['user']['savings_goal'] = $savings_goal;
    $_SESSION['user']['run_rate_target'] = $run_rate_target;

    $_SESSION['flash_success'] = 'Financial goals updated.';
    header('Location: profile.php');
    exit;
}

header('Location: profile.php');
exit;
