<?php
session_start();
require_once 'db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode([]);
    exit();
}

$admin_id = $_SESSION['user_id'];

$stmt = $conn->prepare("
    SELECT 
        t.id AS thread_id, 
        u.id AS user_id,
        CONCAT_WS(' ', u.first_name, u.middle_name, u.last_name) AS full_name, 
        u.role, 
        u.email, 
        u.profile_pic, 
        t.last_message, 
        t.updated_at,
        t.is_read,
        t.last_sender_id
    FROM chat_threads t
    JOIN users u ON t.user_id = u.id
    WHERE t.admin_id = ?
    ORDER BY t.updated_at DESC
");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$result = $stmt->get_result();

$threads = [];
while ($row = $result->fetch_assoc()) {
    // Unread kung ang huling nag-message ay hindi ang admin at is_read ay 0
    $is_unread = ($row['last_sender_id'] != $admin_id && $row['is_read'] == 0);

    $threads[] = [
        'user_id' => $row['user_id'],
        'full_name' => trim($row['full_name']),
        'role' => $row['role'],
        'email' => $row['email'],
        'profile_pic' => $row['profile_pic'],
        'last_message' => $row['last_message'] ?? '',
        'updated_at' => date('M j, Y, g:i a', strtotime($row['updated_at'])),
        'is_unread' => $is_unread
    ];
}

$stmt->close();
$conn->close();

echo json_encode($threads);
?>