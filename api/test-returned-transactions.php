<?php
require_once '../includes/session_check_api.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
header('Content-Type: application/json');

// Get borrowings with 'returned' status
$query = "SELECT b.id, s.student_id, s.name, bk.title, b.status FROM borrowings b 
          JOIN students s ON b.student_id = s.id 
          JOIN books bk ON b.book_id = bk.id 
          WHERE b.status = 'returned'
          ORDER BY b.id DESC LIMIT 10";
$result = $conn->query($query);
$returned_transactions = $result->fetch_all(MYSQLI_ASSOC);

echo json_encode([
    'total_returned' => count($returned_transactions),
    'transactions' => $returned_transactions
]);
?>
