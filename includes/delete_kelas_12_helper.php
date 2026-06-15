<?php
/**
 * Script Penghapusan Data Siswa Kelas 12
 * Perpustakaan BUKUKITA - SMP TAQ SADAMIYYAH
 * 
 * PENTING: Script ini hanya boleh dijalankan oleh Admin
 * File ini sebaiknya dijalankan melalui database management tool atau 
 * call function ini dari admin panel
 */

// Fungsi untuk menghapus siswa kelas 12
function deleteKelas12Students($conn) {
    try {
        // Start transaction untuk keamanan
        $conn->begin_transaction();
        
        // 1. Delete dari returns (JOIN — aman di MySQL, urutan FK)
        $delete_returns = "DELETE r FROM returns r
                          INNER JOIN students s ON r.student_id = s.id
                          WHERE s.class LIKE '12.%'";
        
        if (!$conn->query($delete_returns)) {
            throw new Exception("Error deleting returns: " . $conn->error);
        }
        $returns_deleted = $conn->affected_rows;
        
        // 2. Delete dari borrowings
        $delete_borrowings = "DELETE b FROM borrowings b
                             INNER JOIN students s ON b.student_id = s.id
                             WHERE s.class LIKE '12.%'";
        
        if (!$conn->query($delete_borrowings)) {
            throw new Exception("Error deleting borrowings: " . $conn->error);
        }
        $borrowings_deleted = $conn->affected_rows;
        
        // 3. Delete siswa kelas 12
        $delete_students = "DELETE FROM students WHERE class LIKE '12.%'";
        
        if (!$conn->query($delete_students)) {
            throw new Exception("Error deleting students: " . $conn->error);
        }
        $students_deleted = $conn->affected_rows;
        
        // Commit transaction
        $conn->commit();
        
        // Verifikasi jumlah siswa tersisa
        $verify_query = "SELECT COUNT(*) as total_siswa FROM students";
        $verify_result = $conn->query($verify_query);
        $verify_row = $verify_result->fetch_assoc();
        $total_remaining = $verify_row['total_siswa'];
        
        // Return success response
        return [
            'success' => true,
            'message' => 'Data siswa kelas 12 berhasil dihapus',
            'data' => [
                'siswa_dihapus' => $students_deleted,
                'peminjaman_dihapus' => $borrowings_deleted,
                'pengembalian_dihapus' => $returns_deleted,
                'total_siswa_tersisa' => $total_remaining,
                'timestamp' => date('Y-m-d H:i:s')
            ]
        ];
        
    } catch (Exception $e) {
        // Rollback jika ada error
        $conn->rollback();
        
        return [
            'success' => false,
            'message' => 'Error: ' . $e->getMessage(),
            'error_detail' => $e->getMessage()
        ];
    }
}

// Fungsi untuk mendapatkan statistik siswa sebelum dan sesudah
function getStudentStatistics($conn) {
    $stats = [];
    
    // Total siswa
    $total_result = $conn->query("SELECT COUNT(*) as total FROM students");
    $stats['total_siswa'] = $total_result->fetch_assoc()['total'];
    
    // Siswa per kelas
    $class_result = $conn->query("
        SELECT class, COUNT(*) as jumlah 
        FROM students 
        GROUP BY class 
        ORDER BY class
    ");
    
    $stats['siswa_per_kelas'] = [];
    while ($row = $class_result->fetch_assoc()) {
        $stats['siswa_per_kelas'][] = $row;
    }
    
    // Siswa kelas 10
    $kelas10_result = $conn->query("SELECT COUNT(*) as total FROM students WHERE class LIKE '10.%'");
    $stats['kelas_10'] = $kelas10_result->fetch_assoc()['total'];
    
    // Siswa kelas 11
    $kelas11_result = $conn->query("SELECT COUNT(*) as total FROM students WHERE class LIKE '11.%'");
    $stats['kelas_11'] = $kelas11_result->fetch_assoc()['total'];
    
    // Siswa kelas 12 (yang akan dihapus)
    $kelas12_result = $conn->query("SELECT COUNT(*) as total FROM students WHERE class LIKE '12.%'");
    $stats['kelas_12'] = $kelas12_result->fetch_assoc()['total'];
    
    // Siswa per jurusan
    $major_result = $conn->query("
        SELECT 
            SUBSTRING_INDEX(class, '.', -2) as jurusan,
            COUNT(*) as jumlah 
        FROM students 
        GROUP BY SUBSTRING_INDEX(class, '.', -2)
        ORDER BY jurusan
    ");
    
    $stats['siswa_per_jurusan'] = [];
    while ($row = $major_result->fetch_assoc()) {
        $stats['siswa_per_jurusan'][] = $row;
    }
    
    return $stats;
}

// Fungsi untuk validasi sebelum delete
function validateBeforeDelete($conn) {
    $validation = [];
    
    // Cek jumlah siswa kelas 12
    $kelas12_result = $conn->query("SELECT COUNT(*) as total FROM students WHERE class LIKE '12.%'");
    $validation['kelas_12_count'] = $kelas12_result->fetch_assoc()['total'];
    
    // Cek peminjaman aktif siswa kelas 12
    $active_borrowing = $conn->query("
        SELECT COUNT(*) as total FROM borrowings 
        WHERE status = 'active' AND student_id IN (
            SELECT id FROM students WHERE class LIKE '12.%'
        )
    ");
    $validation['active_borrowings'] = $active_borrowing->fetch_assoc()['total'];
    
    // Cek pengembalian yang belum selesai siswa kelas 12
    $pending_returns = $conn->query("
        SELECT COUNT(*) as total FROM returns 
        WHERE student_id IN (
            SELECT id FROM students WHERE class LIKE '12.%'
        )
    ");
    $validation['pending_returns'] = $pending_returns->fetch_assoc()['total'];
    
    return $validation;
}

// Jika file diakses langsung (AJAX call)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    // Verifikasi keamanan - hanya admin yang bisa akses
    session_start();
    if (!isset($_SESSION['user_id']) && !isset($_SESSION['username'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Unauthorized: Hanya admin yang dapat mengakses function ini'
        ]);
        exit;
    }
    
    // Include database connection
    require_once __DIR__ . '/../config/database.php';
    
    $action = $_POST['action'];
    
    switch ($action) {
        case 'get_statistics':
            $stats = getStudentStatistics($conn);
            echo json_encode([
                'success' => true,
                'data' => $stats
            ]);
            break;
            
        case 'validate':
            $validation = validateBeforeDelete($conn);
            echo json_encode([
                'success' => true,
                'data' => $validation
            ]);
            break;
            
        case 'delete_kelas_12':
            // Double check - request confirmation
            if (!isset($_POST['confirm']) || $_POST['confirm'] !== 'yes') {
                echo json_encode([
                    'success' => false,
                    'message' => 'Penghapusan dibatalkan - tidak ada konfirmasi'
                ]);
                break;
            }
            
            $result = deleteKelas12Students($conn);
            echo json_encode($result);
            break;
            
        default:
            echo json_encode([
                'success' => false,
                'message' => 'Invalid action'
            ]);
    }
    exit;
}
?>
