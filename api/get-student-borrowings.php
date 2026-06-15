<?php
require_once '../includes/session_check_api.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
header('Content-Type: application/json');

if (isset($_GET['student_id'])) {
    $student_id = (int)$_GET['student_id'];
    
    // Get student info
    $student_query = "SELECT * FROM students WHERE id = ?";
    $student_stmt = $conn->prepare($student_query);
    $student_stmt->bind_param("i", $student_id);
    $student_stmt->execute();
    $student = $student_stmt->get_result()->fetch_assoc();
    
    if (!$student) {
        echo json_encode(['success' => false, 'message' => 'Siswa tidak ditemukan']);
        exit;
    }

    if (!isAllowedStudentClass($student['class'])) {
        echo json_encode(['success' => false, 'message' => 'Data siswa tidak tersedia untuk kelas ini']);
        exit;
    }
    
    // Get active borrowings
    $borrowings = getStudentBorrowingDetails($student_id);
    
    // Calculate fine for each borrowing
    $today = new DateTime();
    foreach ($borrowings as &$borrowing) {
        $due_date = new DateTime($borrowing['due_date']);
        $is_overdue = $today > $due_date;
        
        if ($is_overdue) {
            $interval = $due_date->diff($today);
            $days_late = $interval->days;
            $fine = $days_late * 5000;
            $borrowing['fine'] = min($fine, 100000);
        } else {
            $borrowing['fine'] = 0;
        }
    }
    
    // Get total current fine
    $total_fine_query = "SELECT COALESCE(SUM(fine), 0) as total_fine FROM returns WHERE student_id = ?";
    $total_fine_stmt = $conn->prepare($total_fine_query);
    $total_fine_stmt->bind_param("i", $student_id);
    $total_fine_stmt->execute();
    $total_fine = $total_fine_stmt->get_result()->fetch_assoc()['total_fine'];
    
    echo json_encode([
        'success' => true,
        'student' => $student,
        'borrowings' => $borrowings,
        'total_borrowings' => count($borrowings),
        'total_fine' => $total_fine
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Student ID is required']);
}
?>
