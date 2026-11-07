<?php
/**
 * ApexSpend — Add one transaction (POST + CSRF + JSON).
 * Validated: type enum, positive clamped amount, bounded title/category.
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/mailer.php';

[$_SESSION_user, $pdo] = api_gate('write');

$title = trim($_POST['title'] ?? '');
$amount = money_amount($_POST['amount'] ?? 0);
$category = trim($_POST['category'] ?? '');
$method = trim($_POST['method'] ?? '');
$type = trim($_POST['type'] ?? 'expense');
$note = trim($_POST['note'] ?? '');

if (!in_array($type, ['expense', 'income'], true)) {
    json_out(['success' => false, 'error' => 'Unknown transaction type'], 400);
}
if ($title === '' || mb_strlen($title) > 255 || $amount <= 0 || mb_strlen($category) > 100 || mb_strlen($method) > 100) {
    json_out(['success' => false, 'error' => 'Invalid data'], 400);
}

$userId = (int) $_SESSION_user['id'];
$txId = apex_id('tx');
$date = date('Y-m-d H:i:s');

try {
    $stmt = $pdo->prepare('INSERT INTO transactions (id, user_id, type, title, note, amount, category, date, method) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([$txId, $userId, $type, $title, $note, $amount, $category, $date, $method]);
} catch (PDOException $e) {
    error_log('ApexSpend add_transaction: ' . $e->getMessage());
    json_out(['success' => false, 'error' => 'Failed to save transaction'], 500);
}

// Budgets are managed explicitly on the Budgets page — adding an expense no
// longer silently creates a hardcoded ₹5000 budget for the category.
echo json_encode(['success' => true, 'id' => $txId]);

defer_notification_email('transaction_added', [
    'title'    => $title,
    'amount'   => $amount,
    'category' => $category,
    'method'   => $method,
    'type'     => $type,
    'note'     => $note,
]);
