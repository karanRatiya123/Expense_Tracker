<?php
/**
 * ApexSpend — Update one transaction (POST + CSRF + JSON).
 * Ownership checked first; every field validated; wrapped in try/catch.
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/mailer.php';

[$_SESSION_user, $pdo] = api_gate('write');

$id = trim($_POST['id'] ?? '');
$title = trim($_POST['title'] ?? '');
$amount = money_amount($_POST['amount'] ?? 0);
$category = trim($_POST['category'] ?? '');
$method = trim($_POST['method'] ?? '');
$type = trim($_POST['type'] ?? 'expense');
$note = trim($_POST['note'] ?? '');
$dateInput = trim($_POST['date'] ?? '');

if ($id === '' || !in_array($type, ['expense', 'income'], true)) {
    json_out(['success' => false, 'error' => 'Invalid data'], 400);
}
if ($title === '' || mb_strlen($title) > 255 || $amount <= 0 || $category === '' || mb_strlen($category) > 100 || mb_strlen($method) > 100) {
    json_out(['success' => false, 'error' => 'Invalid data'], 400);
}

$timestamp = strtotime($dateInput);
if (!$timestamp || strlen(preg_replace('/[^0-9: \-]/', '', $dateInput)) === 0) {
    json_out(['success' => false, 'error' => 'Invalid date'], 400);
}
$date = date('Y-m-d H:i:s', $timestamp);

$userId = (int) $_SESSION_user['id'];

$exists = $pdo->prepare('SELECT COUNT(*) FROM transactions WHERE id = ? AND user_id = ?');
$exists->execute([$id, $userId]);
if ((int) $exists->fetchColumn() === 0) {
    json_out(['success' => false, 'error' => 'Transaction not found'], 404);
}

try {
    $stmt = $pdo->prepare(
        'UPDATE transactions
         SET type = ?, title = ?, note = ?, amount = ?, category = ?, date = ?, method = ?
         WHERE id = ? AND user_id = ?'
    );
    $success = $stmt->execute([$type, $title, $note, $amount, $category, $date, $method, $id, $userId]);
} catch (PDOException $e) {
    error_log('ApexSpend update_transaction: ' . $e->getMessage());
    json_out(['success' => false, 'error' => 'Failed to update transaction'], 500);
}

if (!$success) {
    json_out(['success' => false, 'error' => 'Failed to update transaction'], 500);
}

echo json_encode(['success' => true]);

defer_notification_email('transaction_updated', [
    'title'    => $title,
    'amount'   => $amount,
    'category' => $category,
    'method'   => $method,
    'type'     => $type,
    'note'     => $note,
]);
