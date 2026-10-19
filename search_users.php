<?php
session_start();
require_once 'db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode([]);
    exit();
}

$query = $_GET['query'] ?? '';

if (trim($query) === '') {
    echo json_encode([]);
    exit();
}

$searchTerm = "%" . trim($query) . "%";

// Sinisigurong nahahanap sina Ashly Rose, Anjanette Jade, at iba pang registered accounts kasama ang kani-kanilang pangalan, role, email, at profile picture
$stmt = $conn->prepare("
    SELECT 
        CONCAT_WS(' ', first_name, middle_name, last_name) AS full_name, 
        role, 
        email, 
        profile_pic 
    FROM users 
    WHERE role != 'admin' AND (
        first_name LIKE ? OR 
        middle_name LIKE ? OR 
        last_name LIKE ? OR 
        CONCAT_WS(' ', first_name, middle_name, last_name) LIKE ? OR
        email LIKE ?
    )
    LIMIT 20
");

$stmt->bind_param("sssss", $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm);
$stmt->execute();
$result = $stmt->get_result();

$users = [];
while ($row = $result->fetch_assoc()) {
    $users[] = [
        'full_name' => trim($row['full_name']),
        'role' => $row['role'],
        'email' => $row['email'],
        'profile_pic' => $row['profile_pic']
    ];
}

$stmt->close();
$conn->close();

echo json_encode($users);
?>