<?php
/**
 * ApexSpend — Delete one budget (POST + CSRF + JSON).
 * Reports 404 when nothing matching the id+owner was deleted.
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/mailer.php';

[$_SESSION_user, $pdo] = api_gate('write');

$userId = (int) $_SESSION_user['id'];
$id = trim($_POST['id'] ?? '');

if ($id === '') {
    json_out(['success' => false, 'error' => 'Invalid ID'], 400);
}

// Capture the category before deleting so the email can name it.
$info = $pdo->prepare('SELECT category, amount FROM budgets WHERE id = ? AND user_id = ?');
$info->execute([$id, $userId]);
$budgetData = $info->fetch();

$stmt = $pdo->prepare('DELETE FROM budgets WHERE id = ? AND user_id = ?');
$stmt->execute([$id, $userId]);

if ($stmt->rowCount() === 0) {
    json_out(['success' => false, 'error' => 'Budget not found'], 404);
}

echo json_encode(['success' => true]);

if ($budgetData) {
    defer_notification_email('budget_deleted', $budgetData);
}
