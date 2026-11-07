<?php
/**
 * ApexSpend — Join a family via invite code (POST + CSRF + JSON).
 * Race with a parallel join is handled at the unique key (1062) level.
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/mailer.php';

[$user, $pdo] = api_gate('write');

$raw = $_POST['code'] ?? '';
$code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $raw));
if (strlen($code) < 4 || strlen($code) > 10) {
    json_out(['success' => false, 'error' => 'Enter a valid invite code'], 400);
}

$fam = $pdo->prepare('SELECT id, name FROM families WHERE invite_code = ?');
$fam->execute([$code]);
$family = $fam->fetch();
if (!$family) {
    json_out(['success' => false, 'error' => 'Incorrect code. Ask a member to re-share it.'], 404);
}

$userId = (int) $user['id'];
$existing = $pdo->prepare("SELECT id FROM family_members WHERE family_id = ? AND user_id = ? AND status = 'active'");
$existing->execute([$family['id'], $userId]);
if ($existing->fetch()) {
    json_out(['success' => true, 'already' => true, 'family_id' => $family['id'], 'name' => $family['name']]);
}

try {
    $ins = $pdo->prepare("INSERT INTO family_members (family_id, user_id, role, status) VALUES (?, ?, 'member', 'active')");
    $ins->execute([$family['id'], $userId]);

    echo json_encode(['success' => true, 'family_id' => $family['id'], 'name' => $family['name']]);

    defer_notification_email('family_joined', ['name' => $family['name']]);
} catch (PDOException $e) {
    // Race: membership appeared between check and insert (unique key).
    if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
        json_out(['success' => true, 'already' => true, 'family_id' => $family['id'], 'name' => $family['name']]);
    }
    error_log('ApexSpend join_family: ' . $e->getMessage());
    json_out(['success' => false, 'error' => 'Could not join family, please retry'], 500);
}
