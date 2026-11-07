<?php
/**
 * ApexSpend — Delete one custom category (POST + CSRF + JSON).
 * Built-ins are protected; history is reassigned to "Other" inside a
 * transaction so nothing orphans.
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/categories.php';

[$user, $pdo] = api_gate('write');

$id = trim($_POST['id'] ?? '');
if ($id === '') {
    json_out(['success' => false, 'error' => 'Missing category id'], 400);
}

$userId = (int) $user['id'];

try {
    $stmt = $pdo->prepare('SELECT * FROM categories WHERE id = ? AND user_id = ?');
    $stmt->execute([$id, $userId]);
    $row = $stmt->fetch();
    if (!$row) {
        json_out(['success' => false, 'error' => 'Category not found.'], 404);
    }
    if ((int) $row['is_builtin'] === 1) {
        json_out(['success' => false, 'error' => 'Built-in categories cannot be deleted.'], 400);
    }

    // Guard: the user must keep at least one category.
    $cnt = $pdo->prepare('SELECT COUNT(*) FROM categories WHERE user_id = ?');
    $cnt->execute([$userId]);
    if ((int) $cnt->fetchColumn() <= 1) {
        json_out(['success' => false, 'error' => 'You need at least one category.'], 400);
    }

    $name = $row['name'];

    $pdo->beginTransaction();
    // Reassign history to "Other" — never orphan a transaction.
    $pdo->prepare('UPDATE transactions SET category = ? WHERE user_id = ? AND category = ?')
        ->execute(['Other', $userId, $name]);
    // Clean up budgets pointing at it.
    $pdo->prepare('DELETE FROM budgets WHERE user_id = ? AND category = ?')
        ->execute([$userId, $name]);
    $pdo->prepare('DELETE FROM categories WHERE id = ? AND user_id = ?')
        ->execute([$id, $userId]);
    $pdo->commit();

    echo json_encode(['success' => true, 'reassigned_to' => 'Other']);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('ApexSpend delete_category: ' . $e->getMessage());
    json_out(['success' => false, 'error' => 'Could not delete category, please retry.'], 500);
}
