<?php
require_once '../includes/session_check.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if (!isset($_GET['date'])) {
    echo json_encode(['success' => false, 'message' => 'Date is required']);
    exit;
}

$date = $_GET['date'];

// Validate date format (YYYY-MM-DD)
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    echo json_encode(['success' => false, 'message' => 'Invalid date format']);
    exit;
}

global $conn;

$query = "SELECT b.id, s.name, s.class, bk.title, b.borrow_date 
          FROM borrowings b
          JOIN students s ON b.student_id = s.id
          JOIN books bk ON b.book_id = bk.id
          WHERE DATE(b.borrow_date) = ? AND " . sqlStudentsOnlySMP('s') . "
          ORDER BY b.borrow_date ASC";

$stmt = $conn->prepare($query);
$stmt->bind_param("s", $date);
$stmt->execute();
$result = $stmt->get_result();
$borrowings = $result->fetch_all(MYSQLI_ASSOC);

echo json_encode([
    'success' => true,
    'date_formatted' => date('d M Y', strtotime($date)),
    'data' => $borrowings,
    'total' => count($borrowings)
]);
