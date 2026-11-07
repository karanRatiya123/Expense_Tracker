<?php
/**
 * ApexSpend — Create or rename one category (POST + CSRF + JSON).
 * Icon and color are strictly whitelisted; rename cascades into
 * transactions and budgets in one transaction.
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/categories.php';

[$user, $pdo] = api_gate('write');

$name = trim($_POST['name'] ?? '');
$icon = trim($_POST['icon'] ?? 'fa-tag');
$color = trim($_POST['color'] ?? '#f59e0b');
$id = trim($_POST['id'] ?? '');

if ($name === '' || mb_strlen($name) > 100) {
    json_out(['success' => false, 'error' => 'Name must be 1-100 characters.'], 400);
}
if (!preg_match('/^fa-[a-z0-9-]+$/', $icon) || !in_array($icon, apex_icon_whitelist(), true)) {
    json_out(['success' => false, 'error' => 'Unknown icon.'], 400);
}
if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
    json_out(['success' => false, 'error' => 'Color must be a hex value like #f59e0b.'], 400);
}

$userId = (int) $user['id'];

try {
    if ($id !== '') {
        // ---- EDIT ----
        $stmt = $pdo->prepare('SELECT * FROM categories WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);
        $row = $stmt->fetch();
        if (!$row) {
            json_out(['success' => false, 'error' => 'Category not found.'], 404);
        }

        $isBuiltin = (int) $row['is_builtin'] === 1;
        $oldName = $row['name'];
        $renamed = $name !== $oldName;

        // Built-ins are locked but recolorable: icon/color may change, name may not.
        if ($isBuiltin && $renamed) {
            json_out(['success' => false, 'error' => 'Built-in categories cannot be renamed. Restyle them instead.'], 400);
        }

        // A rename must not collide with an existing category of this user.
        if ($renamed) {
            $dup = $pdo->prepare('SELECT id FROM categories WHERE user_id = ? AND name = ? AND id <> ?');
            $dup->execute([$userId, $name, $id]);
            if ($dup->fetch()) {
                json_out(['success' => false, 'error' => 'That category already exists.'], 409);
            }
        }

        $pdo->beginTransaction();
        $upd = $pdo->prepare('UPDATE categories SET name = ?, icon = ?, color = ? WHERE id = ? AND user_id = ?');
        $upd->execute([$name, $icon, $color, $id, $userId]);

        // Rename cascade: history follows the new name, nothing orphans.
        if ($renamed) {
            $pdo->prepare('UPDATE transactions SET category = ? WHERE user_id = ? AND category = ?')
                ->execute([$name, $userId, $oldName]);
            $pdo->prepare('UPDATE budgets SET category = ? WHERE user_id = ? AND category = ?')
                ->execute([$name, $userId, $oldName]);
        }
        $pdo->commit();

        json_out(['success' => true, 'item' => [
            'id' => $id, 'name' => $name, 'icon' => $icon, 'color' => $color,
            'is_builtin' => $isBuiltin ? 1 : 0, 'position' => (int) $row['position'],
        ]]);
    }

    // ---- CREATE ----
    $catId = apex_id('cat');
    $pos = $pdo->prepare('SELECT COALESCE(MAX(position), 0) + 1 FROM categories WHERE user_id = ?');
    $pos->execute([$userId]);
    $nextPos = (int) $pos->fetchColumn();

    try {
        $ins = $pdo->prepare('INSERT INTO categories (id, user_id, name, icon, color, is_builtin, position) VALUES (?, ?, ?, ?, ?, 0, ?)');
        $ins->execute([$catId, $userId, $name, $icon, $color, $nextPos]);
    } catch (PDOException $dup) {
        if ((int) ($dup->errorInfo[1] ?? 0) === 1062) {
            json_out(['success' => false, 'error' => 'That category already exists.'], 409);
        }
        throw $dup;
    }

    json_out(['success' => true, 'item' => [
        'id' => $catId, 'name' => $name, 'icon' => $icon, 'color' => $color,
        'is_builtin' => 0, 'position' => $nextPos,
    ]]);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('ApexSpend save_category: ' . $e->getMessage());
    json_out(['success' => false, 'error' => 'Could not save category, please retry.'], 500);
}
