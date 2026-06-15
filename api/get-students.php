<?php
/**
 * API: ambil data siswa by ID.
 *
 * BUG #3 FIX: Ditambahkan session_check_api.php agar endpoint ini
 * tidak bisa diakses tanpa login. Sebelumnya file ini hanya memanggil
 * database.php langsung, sehingga siapapun bisa mengambil data siswa
 * (NIS, nama, kelas) via URL tanpa autentikasi.
 */
require_once '../includes/session_check_api.php';
require_once '../config/database.php';

header('Content-Type: application/json');

if (isset($_GET['id'])) {
    $id    = (int) $_GET['id'];
    $query = "SELECT id, student_id, name, class FROM students WHERE id = ? LIMIT 1";
    $stmt  = $conn->prepare($query);
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Database error']);
        exit;
    }
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($result) {
        echo json_encode($result);
    } else {
        echo json_encode(['success' => false, 'message' => 'Siswa tidak ditemukan']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'ID tidak diberikan']);
}
