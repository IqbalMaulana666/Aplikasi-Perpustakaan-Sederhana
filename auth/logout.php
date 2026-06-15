<?php
session_start();
require_once '../config/database.php';

// Menentukan base path proyek secara dinamis
$base_path = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
if (preg_match('/[\\/](pages|auth|api|includes|config)$/i', $base_path)) {
    $base_path = dirname($base_path);
}
$base_path = rtrim(str_replace('\\', '/', $base_path), '/');

if (isset($_SESSION['user_id'])) {
    $action = 'logout';
    $details = 'Admin logout: ' . $_SESSION['username'];
    $log_query = "INSERT INTO activity_logs (admin_id, action, details, created_at) VALUES (?, ?, ?, NOW())";
    $log_stmt = $conn->prepare($log_query);
    $log_stmt->bind_param("iss", $_SESSION['user_id'], $action, $details);
    $log_stmt->execute();
}

session_unset();
session_destroy();

header("Location: " . $base_path . "/auth/login.php");
exit;
?>
