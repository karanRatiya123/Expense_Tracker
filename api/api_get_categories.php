<?php
/**
 * ApexSpend — List the signed-in user's categories (auth + DB, no CSRF:
 * pure read, no state change).
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/categories.php';

[$user, $pdo] = api_gate('read');

$items = array_map(fn($r) => [
    'id' => $r['id'],
    'name' => $r['name'],
    'icon' => $r['icon'],
    'color' => $r['color'],
    'is_builtin' => (int) $r['is_builtin'],
    'position' => (int) $r['position'],
], apex_get_categories($pdo, (int) $user['id']));

echo json_encode(['success' => true, 'items' => $items]);
