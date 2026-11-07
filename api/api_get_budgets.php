<?php
/**
 * ApexSpend — List the signed-in user's budgets (auth + DB, no CSRF:
 * pure read, no state change).
 */
require_once __DIR__ . '/../config.php';

[$user, $pdo] = api_gate('read');

$stmt = $pdo->prepare('SELECT * FROM budgets WHERE user_id = ? ORDER BY created_at');
$stmt->execute([(int) $user['id']]);
$budgets = $stmt->fetchAll(PDO::FETCH_ASSOC);

$items = [];
foreach ($budgets as $b) {
    $period = ['type' => $b['period_type']];
    if ($b['period_type'] === 'custom') {
        $period['startDate'] = $b['start_date'];
        $period['endDate'] = $b['end_date'];
    }
    $thresholds = [75, 90, 100];
    if (!empty($b['thresholds'])) {
        $decoded = json_decode($b['thresholds'], true);
        if (is_array($decoded)) {
            $thresholds = $decoded;
        }
    }
    $items[] = [
        'id' => $b['id'],
        'category' => $b['category'],
        'amount' => (float) $b['amount'],
        'period' => $period,
        'thresholds' => $thresholds,
        'createdAt' => $b['created_at']
    ];
}

echo json_encode(['success' => true, 'items' => $items]);
