<?php
/**
 * ApexSpend — Create or update one budget (POST + CSRF + JSON).
 * Validates period type and thresholds; amounts clamped like transactions.
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/mailer.php';

[$_SESSION_user, $pdo] = api_gate('write');

$userId = (int) $_SESSION_user['id'];

$id = trim($_POST['id'] ?? '');
$category = trim($_POST['category'] ?? '');
$amount = money_amount($_POST['amount'] ?? 0);
$periodType = trim($_POST['period_type'] ?? 'monthly');
$startDate = trim($_POST['start_date'] ?? '');
$endDate = trim($_POST['end_date'] ?? '');
$thresholdsRaw = trim($_POST['thresholds'] ?? '');

if ($id === '' || mb_strlen($id) > 50 || $category === '' || mb_strlen($category) > 100 || $amount <= 0) {
    json_out(['success' => false, 'error' => 'Invalid data'], 400);
}
if (!in_array($periodType, ['monthly', 'weekly', 'custom'], true)) {
    json_out(['success' => false, 'error' => 'Unknown budget period'], 400);
}

// Custom periods need a well-formed start and end, start before end.
$startDate = null;
$endDate = null;
if ($periodType === 'custom') {
    $startTs = strtotime($_POST['start_date'] ?? '');
    $endTs = strtotime($_POST['end_date'] ?? '');
    if (!$startTs || !$endTs || $startTs > $endTs) {
        json_out(['success' => false, 'error' => 'Custom periods need a valid start and end date'], 400);
    }
    $startDate = date('Y-m-d', $startTs);
    $endDate = date('Y-m-d', $endTs);
}

// Thresholds must be a JSON array of integers within 1..100. Re-encoded
// canonically before storage so the client cannot stash arbitrary strings.
$thresholds = [75, 90, 100];
if ($thresholdsRaw !== '') {
    $decoded = json_decode($thresholdsRaw, true);
    if (is_array($decoded) && $decoded !== []) {
        $clean = [];
        foreach ($decoded as $t) {
            $t = (int) $t;
            if ($t >= 1 && $t <= 100) {
                $clean[] = $t;
            }
        }
        if ($clean !== []) {
            $thresholds = array_values(array_unique($clean));
            sort($thresholds);
        } else {
            json_out(['success' => false, 'error' => 'Thresholds must be numbers from 1 to 100'], 400);
        }
    }
}

try {
    $exists = $pdo->prepare('SELECT id FROM budgets WHERE id = ? AND user_id = ?');
    $exists->execute([$id, $userId]);
    if ($exists->fetch()) {
        $stmt = $pdo->prepare('UPDATE budgets SET category = ?, amount = ?, period_type = ?, start_date = ?, end_date = ?, thresholds = ? WHERE id = ? AND user_id = ?');
        $stmt->execute([$category, $amount, $periodType, $startDate, $endDate, json_encode($thresholds), $id, $userId]);
        $isNew = false;
    } else {
        $stmt = $pdo->prepare('INSERT INTO budgets (id, user_id, category, amount, period_type, start_date, end_date, thresholds) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$id, $userId, $category, $amount, $periodType, $startDate, $endDate, json_encode($thresholds)]);
        $isNew = true;
    }
} catch (PDOException $e) {
    error_log('ApexSpend save_budget: ' . $e->getMessage());
    json_out(['success' => false, 'error' => 'Failed to save budget'], 500);
}

echo json_encode(['success' => true]);

defer_notification_email('budget_saved', [
    'category'    => $category,
    'amount'      => $amount,
    'period_type' => $periodType,
    'thresholds'  => json_encode($thresholds),
    'is_new'      => $isNew,
]);
