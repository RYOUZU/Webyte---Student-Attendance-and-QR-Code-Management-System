<?php
session_start();
require_once 'db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

$sender_id = $_SESSION['user_id'];
$receiver_id = $_POST['user_id'] ?? 0;
$message = trim($_POST['message'] ?? '');
$reply_to = $_POST['reply_to'] ?? null;

if (!$receiver_id || empty($message) || mb_strlen($message, 'UTF-8') > 4000) {
    echo json_encode(['status' => 'error', 'message' => 'Enter a message of 1–4000 characters and select a contact.']);
    exit();
}
if (($_SESSION['role'] ?? '') !== 'admin') {
    $allowed = $conn->prepare("SELECT id FROM users WHERE id = ? AND role = 'admin' AND COALESCE(is_deleted,0)=0");
    $allowed->bind_param('i', $receiver_id); $allowed->execute(); $ok = $allowed->get_result()->fetch_assoc(); $allowed->close();
    if (!$ok) { echo json_encode(['status'=>'error','message'=>'Students and teachers can send messages only to an administrator.']); exit(); }
} else {
    $allowed = $conn->prepare('SELECT id FROM users WHERE id = ? AND COALESCE(is_deleted,0)=0');
    $allowed->bind_param('i', $receiver_id); $allowed->execute(); $ok=$allowed->get_result()->fetch_assoc(); $allowed->close();
    if (!$ok) { echo json_encode(['status'=>'error','message'=>'The selected contact is not available.']); exit(); }
}

$stmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, message, reply_to, created_at) VALUES (?, ?, ?, ?, NOW())");
$stmt->bind_param("iiss", $sender_id, $receiver_id, $message, $reply_to);

if ($stmt->execute()) {
    echo json_encode(['status' => 'success']);
} else {
    echo json_encode(['status' => 'error', 'message' => $conn->error]);
}

$stmt->close();
$conn->close();
?>