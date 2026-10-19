<?php
// get_student_attendance_dates.php
session_start();
require_once 'db_connect.php';

ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

$student_id = isset($_GET['student_id']) ? intval($_GET['student_id']) : 0;
$course_id = isset($_GET['course_id']) ? intval($_GET['course_id']) : (isset($_GET['subject_id']) ? intval($_GET['subject_id']) : 0);

if ($student_id <= 0 || $course_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid parameters']);
    exit();
}

// Kunin ang section ng estudyante para malaman ang listahan at pagkakasunod-sunod nila
$sec_query = "SELECT section FROM users WHERE id = ?";
$stmt_sec = $conn->prepare($sec_query);
$stmt_sec->bind_param("i", $student_id);
$stmt_sec->execute();
$sec_res = $stmt_sec->get_result()->fetch_assoc();
$section_name = $sec_res['section'] ?? '';
$stmt_sec->close();

// Alamin ang row number ng estudyante base sa alphabetical order ng apelyido sa kanilang section (katulad ng sa class record)
$row_number = 1;
if (!empty($section_name)) {
    $rank_query = "SELECT id FROM users WHERE role = 'student' AND section = ? ORDER BY last_name ASC";
    $stmt_rank = $conn->prepare($rank_query);
    $stmt_rank->bind_param("s", $section_name);
    $stmt_rank->execute();
    $rank_result = $stmt_rank->get_result();
    
    $counter = 1;
    while ($row = $rank_result->fetch_assoc()) {
        if (intval($row['id']) === $student_id) {
            $row_number = $counter;
            break;
        }
        $counter++;
    }
    $stmt_rank->close();
}

$dates = [];
$query = "
    SELECT date, status 
    FROM attendance_records 
    WHERE student_id = ? AND class_id = ? 
    ORDER BY date ASC
";

$stmt = $conn->prepare($query);
if ($stmt) {
    $stmt->bind_param("ii", $student_id, $course_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $dates[] = [
            'date' => date('M d, Y', strtotime($row['date'])),
            'status' => $row['status']
        ];
    }
    $stmt->close();
}

echo json_encode([
    'status' => 'success',
    'row_number' => $row_number,
    'dates' => $dates
]);
?>