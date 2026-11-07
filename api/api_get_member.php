<?php
/**
 * ApexSpend — View one family member's stats + recent transactions
 * (auth + membership required, no CSRF: pure read, no state change).
 */
require_once __DIR__ . '/../config.php';

[$user, $pdo] = api_gate('read');

$familyId = trim($_GET['family_id'] ?? '');
$memberId = (int) ($_GET['user_id'] ?? 0);
if ($familyId === '' || $memberId <= 0) {
    json_out(['success' => false, 'error' => 'Invalid request'], 400);
}

try {
    // Only active members of this family may view each other.
    $me = $pdo->prepare("SELECT role FROM family_members WHERE family_id = ? AND user_id = ? AND status = 'active'");
    $me->execute([$familyId, $user['id']]);
    $myRow = $me->fetch();
    if (!$myRow) {
        json_out(['success' => false, 'error' => 'Not a member'], 403);
    }

    $mem = $pdo->prepare("SELECT u.id, u.name, u.email, m.role, m.joined_at FROM family_members m JOIN users u ON u.id = m.user_id WHERE m.family_id = ? AND m.user_id = ? AND m.status = 'active'");
    $mem->execute([$familyId, $memberId]);
    $member = $mem->fetch();
    if (!$member) {
        json_out(['success' => false, 'error' => 'Member not found'], 404);
    }

    $stats = $pdo->prepare("SELECT type, COUNT(*) AS cnt, COALESCE(SUM(amount), 0) AS total FROM transactions WHERE user_id = ? GROUP BY type");
    $stats->execute([$memberId]);
    $income = 0;
    $expense = 0;
    $count = 0;
    foreach ($stats->fetchAll() as $row) {
        $count += (int) $row['cnt'];
        if ($row['type'] === 'income') {
            $income = (float) $row['total'];
        } else {
            $expense = (float) $row['total'];
        }
    }

    $top = $pdo->prepare("SELECT category, SUM(amount) AS total FROM transactions WHERE user_id = ? AND type = 'expense' GROUP BY category ORDER BY total DESC LIMIT 1");
    $top->execute([$memberId]);
    $topRow = $top->fetch();

    $tx = $pdo->prepare('SELECT id, type, title, note, amount, category, date, method FROM transactions WHERE user_id = ? ORDER BY date DESC LIMIT 60');
    $tx->execute([$memberId]);
    $transactions = $tx->fetchAll();

    echo json_encode([
        'success' => true,
        'is_admin' => $myRow['role'] === 'admin',
        'member' => $member,
        'stats' => [
            'income' => $income,
            'expense' => $expense,
            'net' => $income - $expense,
            'count' => $count,
            'top_category' => $topRow ? $topRow['category'] : null,
        ],
        'transactions' => $transactions,
    ]);
} catch (PDOException $e) {
    error_log('ApexSpend get_member: ' . $e->getMessage());
    json_out(['success' => false, 'error' => 'Could not load member'], 500);
}
