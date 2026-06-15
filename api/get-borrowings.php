<?php
require_once '../includes/session_check_api.php';
require_once '../config/database.php';
header('Content-Type: application/json');

if (isset($_GET['student_id'])) {
    $student_id = (int)$_GET['student_id'];
    $query = "SELECT b.*, bk.title, bk.author FROM borrowings b 
              JOIN books bk ON b.book_id = bk.id 
              WHERE b.student_id = ? AND b.status = 'active' 
              ORDER BY b.due_date ASC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    echo json_encode($result);
} else {
    echo json_encode([]);
}
?>
