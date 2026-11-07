<?php
/**
 * Phase 4: Environment Health Check & Verification Script
 */
require_once __DIR__ . '/../config.php';

echo "=== Phase 4: Environment & Pre-requisite Verification ===\n";

// 1. PHP Engine Check
echo "[1] PHP Version: " . PHP_VERSION . " ... ";
if (version_compare(PHP_VERSION, '8.0.0', '>=')) {
    echo "OK (>= 8.0)\n";
} else {
    echo "WARNING (< 8.0)\n";
}

// 2. Required PHP Extensions Check
$required_extensions = ['pdo', 'pdo_mysql', 'openssl', 'session', 'json', 'filter'];
echo "[2] Required Extensions:\n";
foreach ($required_extensions as $ext) {
    echo "  - $ext: ";
    if (extension_loaded($ext)) {
        echo "LOADED\n";
    } else {
        echo "MISSING\n";
    }
}

// 3. Writable Directories Check
echo "[3] Directory Permissions:\n";
$sessionDir = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '.sessions';
echo "  - .sessions directory writable: " . (is_writable($sessionDir) ? "YES" : "NO") . "\n";

// 4. Configuration Check
echo "[4] Application Configuration:\n";
echo "  - DB_HOST: " . DB_HOST . "\n";
echo "  - DB_USER: " . DB_USER . "\n";
echo "  - DB_NAME: " . DB_NAME . "\n";
echo "  - CSRF Token generation: ";
$token = csrf_token();
echo (!empty($token) && strlen($token) === 64 ? "OK (64 hex chars)" : "FAIL") . "\n";

// 5. Database Connectivity Check
echo "[5] Database Connectivity:\n";
try {
    $pdo = getDBConnection();
    if ($pdo) {
        echo "  - Status: CONNECTED to MySQL database '" . DB_NAME . "'\n";
        $stmt = $pdo->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        echo "  - Tables found (" . count($tables) . "): " . implode(', ', $tables) . "\n";
    } else {
        echo "  - Status: MySQL not currently reachable. The application will refuse requests until it is back.\n";
    }
} catch (Exception $e) {
    echo "  - Error: " . $e->getMessage() . "\n";
}

echo "=== Verification Complete ===\n";
