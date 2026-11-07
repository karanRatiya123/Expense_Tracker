<?php
require_once __DIR__ . '/../config.php';

// Unset user session and any other data
session_unset();
session_destroy();
session_start();
$_SESSION['flash_success'] = 'You have been logged out safely.';

header('Location: dashboard.php');
exit;
?>