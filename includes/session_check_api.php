<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
date_default_timezone_set('Asia/Jakarta');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['username'])) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Sesi login habis. Silakan login ulang.',
    ]);
    exit;
}
