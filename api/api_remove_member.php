<?php
/**
 * ApexSpend — Remove one member (POST + CSRF + JSON).
 * Admin of the family only; infanticide of the empty family included.
 */
require_once __DIR__ . '/../config.php';

[$user, $pdo] = api_gate('write');

$familyId = trim($_POST['family_id'] ?? '');
$memberId = (int) ($_POST['user_id'] ?? 0);
if ($familyId === '' || $memberId <= 0) {
    json_out(['success' => false, 'error' => 'Invalid request'], 400);
}

try {
    // Only an admin of this family can remove.
    $me = $pdo->prepare("SELECT role FROM family_members WHERE family_id = ? AND user_id = ? AND status = 'active'");
    $me->execute([$familyId, $user['id']]);
    $myRow = $me->fetch();
    if (!$myRow || $myRow['role'] !== 'admin') {
        json_out(['success' => false, 'error' => 'Only the admin can remove members'], 403);
    }

    if ($memberId === (int) $user['id']) {
        json_out(['success' => false, 'error' => 'Use Leave to remove yourself'], 400);
    }

    $target = $pdo->prepare("SELECT id FROM family_members WHERE family_id = ? AND user_id = ? AND status = 'active'");
    $target->execute([$familyId, $memberId]);
    if (!$target->fetch()) {
        json_out(['success' => false, 'error' => 'Member not found'], 404);
    }

    $pdo->beginTransaction();
    $pdo->prepare('DELETE FROM family_members WHERE family_id = ? AND user_id = ?')
        ->execute([$familyId, $memberId]);

    // Tidy up: remove the family itself once nobody is left.
    $left = $pdo->prepare('SELECT COUNT(*) FROM family_members WHERE family_id = ?');
    $left->execute([$familyId]);
    if ((int) $left->fetchColumn() === 0) {
        $pdo->prepare('DELETE FROM families WHERE id = ?')->execute([$familyId]);
    }
    $pdo->commit();

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('ApexSpend remove_member: ' . $e->getMessage());
    json_out(['success' => false, 'error' => 'Could not remove member, please retry'], 500);
}
