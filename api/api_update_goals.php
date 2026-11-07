<?php
/**
 * ApexSpend — Save the two financial goals (POST + CSRF + JSON).
 */
require_once __DIR__ . '/../config.php';

[$user, $pdo] = api_gate('write');

$savings_goal = money_amount($_POST['savings_goal'] ?? 0);
$run_rate_target = money_amount($_POST['run_rate_target'] ?? 0);

if ($savings_goal <= 0 || $run_rate_target <= 0) {
    json_out(['success' => false, 'error' => 'Goals must be positive numbers (max 99,999,999.99).'], 400);
}

try {
    $stmt = $pdo->prepare('UPDATE users SET savings_goal = ?, run_rate_target = ? WHERE id = ?');
    $stmt->execute([$savings_goal, $run_rate_target, (int) $user['id']]);
} catch (PDOException $e) {
    error_log('ApexSpend update_goals: ' . $e->getMessage());
    json_out(['success' => false, 'error' => 'Failed to save to database.'], 500);
}

// Session mirrors DB so the dashboard reads stay cheap.
$_SESSION['user']['savings_goal'] = $savings_goal;
$_SESSION['user']['run_rate_target'] = $run_rate_target;

echo json_encode(['success' => true]);
