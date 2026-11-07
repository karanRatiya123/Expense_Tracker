<?php
/**
 * ApexSpend — Configuration & Database Helper
 */

if (session_status() === PHP_SESSION_NONE) {
    $sessionPath = __DIR__ . DIRECTORY_SEPARATOR . '.sessions';
    if (!is_dir($sessionPath)) {
        @mkdir($sessionPath, 0775, true);
    }
    // Session files under htdocs must never be web-readable. Apache lets the
    // folder be listed; this guard drops any direct request to the files.
    $sessionGuard = $sessionPath . DIRECTORY_SEPARATOR . '.htaccess';
    if (is_dir($sessionPath) && !is_file($sessionGuard)) {
        @file_put_contents($sessionGuard, "Require all denied\n");
    }
    @session_save_path($sessionPath);
    session_set_cookie_params([
        'httponly' => true,
        // secure=on once the site is served over HTTPS (no effect on plain HTTP)
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax',
        'path'     => '/',
    ]);
    session_start();
}

define('CSRF_FIELD', '_token');

/**
 * Standard JSON response: set the status code, print, stop.
 */
function json_out(array $data, int $status = 200): never {
    http_response_code($status);
    echo json_encode($data);
    exit;
}

/**
 * Return the per-session CSRF token, minting one if missing.
 * Forms print it via <input type="hidden" name="_token" value="<?= csrf_token() ?>">.
 * AJAX requests read it from the same field name in FormData,
 * or from the X-CSRF-Token header.
 */
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validate the CSRF token on the current request. Call at the top of every
 * state-changing POST handler (forms and api_*.php). Halts on failure.
 */
function csrf_validate(): void {
    $sent = $_POST[CSRF_FIELD] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], (string) $sent)) {
        return;
    }
    // Distinguish API handlers from plain form handlers by script name,
    // not by URL prefix (REQUEST_URI includes htdocs subdirectories).
    $script = basename($_SERVER['SCRIPT_NAME'] ?? '');
    if (str_starts_with($script, 'api_')) {
        json_out(['success' => false, 'error' => 'Invalid CSRF token'], 403);
    }
    $_SESSION['flash_error'] = 'Session expired. Please try again.';
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '../pages/login.php'));
    exit;
}

/**
 * Gate for api_*.php endpoints. Order: headers, session, (method, CSRF), DB.
 * 'write' = POST + CSRF (+DB). 'read' = session (+DB) only.
 * Returns [userArray, PDO] and exits with the proper HTTP status on failure.
 * When $dbRequired is false the caller decides how to handle null PDO.
 * @param 'write'|'read' $mode
 * @return array{0: array, 1: PDO|null}
 */
function api_gate(string $mode, bool $dbRequired = true): array {
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');

    if (!isset($_SESSION['user'])) {
        json_out(['success' => false, 'error' => 'Unauthorized'], 401);
    }
    if ($mode === 'write') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            json_out(['success' => false, 'error' => 'Invalid request'], 405);
        }
        csrf_validate();
    }
    $pdo = getDBConnection();
    if ($dbRequired && !$pdo) {
        json_out(['success' => false, 'error' => 'Database connection failed'], 503);
    }
    return [$_SESSION['user'], $pdo];
}

// ─── Database Credentials (Customize for your MySQL setup) ────────
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'apexspend_db');
define('ENABLE_RECAPS', false);

// ─── Email notifications (Gmail SMTP) ─────────────────────────────
// 1. Turn on 2-Step Verification on the Google account.
// 2. Create an App Password: https://myaccount.google.com/apppasswords
// 3. Put the address + 16-character password in mail_credentials.php
//    (untracked by git) or set APEX_MAIL_USER / APEX_MAIL_PASS.
// Until credentials are filled in, notification calls return instantly.
if (is_file(__DIR__ . '/mail_credentials.php')) {
    require_once __DIR__ . '/mail_credentials.php';
}

if (!defined('MAIL_ENABLED'))  define('MAIL_ENABLED', true);
if (!defined('MAIL_HOST'))     define('MAIL_HOST', 'smtp.gmail.com');
if (!defined('MAIL_PORT'))     define('MAIL_PORT', 587);
if (!defined('MAIL_USERNAME')) define('MAIL_USERNAME', getenv('APEX_MAIL_USER') ?: 'YOUR_EMAIL@gmail.com');
if (!defined('MAIL_PASSWORD')) define('MAIL_PASSWORD', getenv('APEX_MAIL_PASS') ?: '');
if (!defined('MAIL_FROM_NAME')) define('MAIL_FROM_NAME', 'ApexSpend');
if (!defined('MAIL_TIMEOUT'))  define('MAIL_TIMEOUT', 8); // seconds — stops a bad network hanging the UI

/**
 * Get Database Connection (PDO)
 * Returns null when MySQL is unreachable. API handlers answer 503 via
 * api_gate(); page handlers show their own flash — there is no fallback
 * store: data must not silently disappear when the DB is down.
 */
function getDBConnection() {
    // One connection per request — avoids paying for (and holding) extra
    // sockets when several helpers ask for the DB.
    static $pdo = null;
    static $attempted = false;
    if ($attempted) {
        return $pdo;
    }
    $attempted = true;

    try {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
        return $pdo;
    } catch (PDOException $e) {
        error_log('ApexSpend DB: connection failed — ' . $e->getMessage());
        return null;
    }
}

/**
 * Clamped monetary amount. Returns 0.0 when the input is not a valid
 * positive amount (caller treats 0 as invalid). Max guards DECIMAL(15,2).
 */
function money_amount(mixed $raw, float $max = 99999999.99): float {
    $value = round((float) $raw, 2);
    if ($value <= 0.0 || $value > $max) {
        return 0.0;
    }
    return $value;
}

/**
 * Collision-proof, unpredictable record id: prefix + 20 hex chars.
 * (uniqid() was microsecond-timestamp based — predictable and, on a very
 * busy second, collision-prone.)
 */
function apex_id(string $prefix): string {
    return $prefix . '_' . bin2hex(random_bytes(10));
}

/**
 * Helper to display flash messages
 */
function getFlashMessage($type) {
    if (isset($_SESSION['flash_' . $type])) {
        $msg = $_SESSION['flash_' . $type];
        unset($_SESSION['flash_' . $type]);
        return $msg;
    }
    return null;
}
?>
