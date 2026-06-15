<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
header('Content-Type: application/json');

// Authentication check
if (!isset($_SESSION['user_id']) || !isset($_SESSION['username'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Validate request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method tidak diizinkan']);
    exit;
}

if (!isset($_POST['borrowing_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'borrowing_id tidak ada']);
    exit;
}

$borrowing_id = intval($_POST['borrowing_id']);

if ($borrowing_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'borrowing_id tidak valid']);
    exit;
}

try {
    // Verify borrowing exists and has 'returned' status
    $check_stmt = $conn->prepare("SELECT id, status FROM borrowings WHERE id = ?");
    if (!$check_stmt) {
        throw new Exception("Prepare error: " . $conn->error);
    }
    
    $check_stmt->bind_param("i", $borrowing_id);
    if (!$check_stmt->execute()) {
        throw new Exception("Execute error: " . $check_stmt->error);
    }
    
    $result = $check_stmt->get_result();
    $borrowing = $result->fetch_assoc();
    $check_stmt->close();
    
    if (!$borrowing) {
        echo json_encode(['success' => false, 'message' => 'Transaksi tidak ditemukan']);
        exit;
    }
    
    if ($borrowing['status'] !== 'returned') {
        echo json_encode(['success' => false, 'message' => 'Hanya transaksi yang sudah dikembalikan yang bisa dihapus']);
        exit;
    }
    
    // Delete return records first
    $delete_return_stmt = $conn->prepare("DELETE FROM returns WHERE borrowing_id = ?");
    if (!$delete_return_stmt) {
        throw new Exception("Prepare error: " . $conn->error);
    }
    $delete_return_stmt->bind_param("i", $borrowing_id);
    if (!$delete_return_stmt->execute()) {
        throw new Exception("Gagal menghapus return record: " . $delete_return_stmt->error);
    }
    $delete_return_stmt->close();
    
    // Soft delete borrowing record (keep for stats, hide from UI)
    $delete_stmt = $conn->prepare("UPDATE borrowings SET is_deleted = 1 WHERE id = ?");
    if (!$delete_stmt) {
        throw new Exception("Prepare error: " . $conn->error);
    }
    $delete_stmt->bind_param("i", $borrowing_id);
    if (!$delete_stmt->execute()) {
        throw new Exception("Gagal mengupdate status hapus: " . $delete_stmt->error);
    }
    $delete_stmt->close();
    
    // Log activity (safely)
    try {
        logActivity('hapus_transaksi', "Hapus transaksi peminjaman ID: $borrowing_id");
    } catch (Exception $log_error) {
        // Activity logging error, but don't fail the deletion
        error_log("Activity log error: " . $log_error->getMessage());
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Transaksi berhasil dihapus'
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}

exit;
?>
