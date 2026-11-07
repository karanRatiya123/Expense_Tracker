<?php
/**
 * ApexSpend — Delete one transaction (POST + CSRF + JSON).
 * Reports 404 when nothing matching the id+owner was deleted.
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/mailer.php';

[$_SESSION_user, $pdo] = api_gate('write');

$id = trim($_POST['id'] ?? '');
if ($id === '') {
    json_out(['success' => false, 'error' => 'Missing transaction id'], 400);
}

// Fetch details first — the row is gone after the DELETE, but the email needs them.
$detail = $pdo->prepare('SELECT title, amount, category, type FROM transactions WHERE id = ? AND user_id = ?');
$detail->execute([$id, $_SESSION_user['id']]);
$txData = $detail->fetch();

$stmt = $pdo->prepare('DELETE FROM transactions WHERE id = ? AND user_id = ?');
$stmt->execute([$id, $_SESSION_user['id']]);

if ($stmt->rowCount() === 0) {
    json_out(['success' => false, 'error' => 'Transaction not found'], 404);
}

echo json_encode(['success' => true]);

if ($txData) {
    defer_notification_email('transaction_deleted', $txData);
}
