<?php
session_start();

// Menentukan base path proyek secara dinamis
$base_path = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
if (preg_match('/[\\/](pages|auth|api|includes|config)$/i', $base_path)) {
    $base_path = dirname($base_path);
}
$base_path = rtrim(str_replace('\\', '/', $base_path), '/');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['username'])) {
    header("Location: " . $base_path . "/auth/login.php");
    exit;
}

date_default_timezone_set('Asia/Jakarta');
