<?php
session_start();
require_once 'db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode([]);
    exit();
}

$current_user_id = $_SESSION['user_id'];
$other_user_id = (int)($_GET['user_id'] ?? 0);

if (!$other_user_id) {
    echo json_encode([]);
    exit();
}
if (($_SESSION['role'] ?? '') !== 'admin') {
    $allowed = $conn->prepare("SELECT id FROM users WHERE id = ? AND role = 'admin' AND COALESCE(is_deleted,0)=0");
    $allowed->bind_param('i', $other_user_id); $allowed->execute(); $ok = $allowed->get_result()->fetch_assoc(); $allowed->close();
    if (!$ok) { echo json_encode([]); exit(); }
}

// Palitan ang 'messages' at mga column names ayon sa disenyo ng iyong database table
$stmt = $conn->prepare("
    SELECT * FROM messages 
    WHERE (sender_id = ? AND receiver_id = ?) 
       OR (sender_id = ? AND receiver_id = ?) 
    ORDER BY created_at ASC
");
$stmt->bind_param("iiii", $current_user_id, $other_user_id, $other_user_id, $current_user_id);
$stmt->execute();
$result = $stmt->get_result();

$messages = [];
while ($row = $result->fetch_assoc()) {
    $messages[] = [
        'is_outgoing' => ($row['sender_id'] == $current_user_id),
        'message' => $row['message'],
        'reply_to' => $row['reply_to'] ?? null,
        'created_at_formatted' => date('M j, Y, g:i a', strtotime($row['created_at']))
    ];
}

$stmt->close();
$conn->close();

echo json_encode($messages);
?>