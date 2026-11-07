<?php
require_once 'config.php';
header('Location: ' . (isset($_SESSION['user']) ? 'pages/dashboard.php' : 'pages/login.php'));
exit;
