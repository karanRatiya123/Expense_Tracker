<?php
/**
 * ApexSpend — Toggle email notifications ON/OFF for the signed-in user.
 * POST only + CSRF protected. Returns JSON. Works session-only when the
 * database is unreachable (the toggle is deliberately the last thing that
 * still functions without MySQL).
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/email_prefs.php';

[$user, $pdo] = api_gate('write', false);

$enabled = ((string) ($_POST['enabled'] ?? '0')) === '1';

// Keep the session in step so the mail path never needs a DB round-trip.
$_SESSION['user']['email_notifications'] = $enabled ? 1 : 0;

if (!$pdo) {
    // Session-only mode (no MySQL) — the value above is the source of truth.
    echo json_encode(['success' => true, 'enabled' => $enabled]);
    exit;
}

if (set_email_notifications($pdo, (int) $user['id'], $enabled)) {
    echo json_encode(['success' => true, 'enabled' => $enabled]);
} else {
    json_out(['success' => false, 'error' => 'Could not update preference'], 500);
}
