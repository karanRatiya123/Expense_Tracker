<?php
/**
 * ApexSpend — Create a family space (POST + CSRF + JSON).
 * Invite code drawn from an unambiguous alphabet with retries; family +
 * creator's admin membership are one atomic transaction.
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/mailer.php';

[$user, $pdo] = api_gate('write');

$name = trim($_POST['name'] ?? '');
if (mb_strlen($name) < 2 || mb_strlen($name) > 40) {
    json_out(['success' => false, 'error' => 'Family name must be 2-40 characters'], 400);
}

// Unique 6-char invite code (unambiguous alphabet, retry on collision).
$alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
$code = null;
for ($i = 0; $i < 25; $i++) {
    $c = '';
    for ($j = 0; $j < 6; $j++) {
        $c .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }
    $check = $pdo->prepare('SELECT id FROM families WHERE invite_code = ?');
    $check->execute([$c]);
    if (!$check->fetch()) {
        $code = $c;
        break;
    }
}
if (!$code) {
    json_out(['success' => false, 'error' => 'Could not generate a code, please retry'], 500);
}

$famId = apex_id('fam');
try {
    $pdo->beginTransaction();
    $ins = $pdo->prepare('INSERT INTO families (id, name, invite_code, created_by) VALUES (?, ?, ?, ?)');
    $ins->execute([$famId, $name, $code, $user['id']]);
    $mem = $pdo->prepare("INSERT INTO family_members (family_id, user_id, role, status) VALUES (?, ?, 'admin', 'active')");
    $mem->execute([$famId, $user['id']]);
    $pdo->commit();

    echo json_encode(['success' => true, 'family_id' => $famId, 'name' => $name, 'invite_code' => $code]);

    defer_notification_email('family_created', [
        'name'        => $name,
        'invite_code' => $code,
    ]);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('ApexSpend create_family: ' . $e->getMessage());
    json_out(['success' => false, 'error' => 'Could not create family, please retry'], 500);
}
