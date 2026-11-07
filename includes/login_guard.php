<?php
/**
 * ApexSpend — Login throttle
 *
 * Purpose: stop brute-force password guessing.Counts attempts per
 * (email, IP) pair in the login_attempts table. Five failures inside a
 * 15-minute window block new logins until the window passes; a success
 * clears the counter. Every function is fail-open by design: if the table
 * is missing (old install), login keeps working — DB failure must not
 * brick the sign-in flow. Requires an active PDO (no-ops otherwise).
 */

const LOGIN_MAX_ATTEMPTS = 5;      // failures before the block
const LOGIN_WINDOW_SECONDS = 900;  // 15 minutes

/** Is this email+IP currently throttled? */
function login_throttled(?PDO $pdo, string $email, string $ip): bool {
    if (!$pdo) return false;
    try {
        $stmt = $pdo->prepare('SELECT attempts, window_start FROM login_attempts WHERE email = ? AND ip = ?');
        $stmt->execute([$email, $ip]);
        $row = $stmt->fetch();
        if (!$row) return false;
        $withinWindow = (time() - strtotime((string) $row['window_start'])) < LOGIN_WINDOW_SECONDS;
        return $withinWindow && (int) $row['attempts'] >= LOGIN_MAX_ATTEMPTS;
    } catch (PDOException $e) {
        return false; // table missing / DB hiccup — fail open
    }
}

/** Record one failure, resetting the window when it has expired. */
function login_record_failure(?PDO $pdo, string $email, string $ip): void {
    if (!$pdo) return;
    try {
        // All-positional placeholders: PDO rejects named + positional mixed
        // (SQLSTATE HY093), which silently disabled the counter before.
        $stmt = $pdo->prepare(
            'INSERT INTO login_attempts (email, ip, attempts, window_start)
             VALUES (?, ?, 1, NOW())
             ON DUPLICATE KEY UPDATE
                 attempts = IF(TIMESTAMPDIFF(SECOND, window_start, NOW()) > ?, 1, attempts + 1),
                 window_start = IF(TIMESTAMPDIFF(SECOND, window_start, NOW()) > ?, NOW(), window_start)'
        );
        $stmt->execute([$email, $ip, LOGIN_WINDOW_SECONDS, LOGIN_WINDOW_SECONDS]);
    } catch (PDOException $e) {
        error_log('ApexSpend login-guard: ' . $e->getMessage());
    }
}

/** A successful login resets the counter. */
function login_clear_failures(?PDO $pdo, string $email, string $ip): void {
    if (!$pdo) return;
    try {
        $pdo->prepare('DELETE FROM login_attempts WHERE email = ? AND ip = ?')
            ->execute([$email, $ip]);
    } catch (PDOException $e) {
        // best effort
    }
}

/** Best-effort client IP (behind nothing here, but forward-safe). */
function login_client_ip(): string {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
        $fwd = trim(explode(',', (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''))[0]);
        if (filter_var($fwd, FILTER_VALIDATE_IP)) {
            return $fwd;
        }
    }
    return (string) $ip;
}
