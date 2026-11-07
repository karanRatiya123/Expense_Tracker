<?php
/**
 * ApexSpend — Dismiss the one-time welcome card.
 * POST only + CSRF protected. Returns JSON. Session-only when MySQL is
 * unreachable (the card is DB-gated, so there is nothing to persist).
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/welcome.php';

[$user, $pdo] = api_gate('write', false);

if (!$pdo) {
    echo json_encode(['success' => true]);
    exit;
}

if (apex_mark_welcome_seen($pdo, (int) $user['id'])) {
    echo json_encode(['success' => true]);
} else {
    json_out(['success' => false, 'error' => 'Could not dismiss welcome card'], 500);
}
