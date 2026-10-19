<?php
// get_student_attendance.php
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

if ($student_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid student ID']);
    exit();
}

$courses = [];

// Kunin ang mga subjects na naka-enroll ang estudyante mula sa student_enrollments at teacher_subjects
$query = "
    SELECT 
        ts.id as course_id,
        ts.subject_name as course_name,
        CONCAT(u.first_name, ' ', u.last_name) as teacher_name
    FROM student_enrollments se
    JOIN teacher_subjects ts ON se.subject_id = ts.id
    LEFT JOIN users u ON ts.teacher_id = u.id
    WHERE se.student_id = ?
";

$stmt = $conn->prepare($query);
if ($stmt) {
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $course_id = intval($row['course_id']);
        
        // Bilangin ang attendance para sa kursong ito mula sa attendance_records o attendance table
        // Sinusuri natin ang attendance_records gamit ang class_id
        $att_query = "
            SELECT 
                COUNT(*) as total_classes,
                SUM(CASE WHEN LOWER(status) = 'present' THEN 1 ELSE 0 END) as total_present,
                SUM(CASE WHEN LOWER(status) = 'late' THEN 1 ELSE 0 END) as total_late,
                SUM(CASE WHEN LOWER(status) = 'absent' THEN 1 ELSE 0 END) as total_absent
            FROM attendance_records 
            WHERE student_id = ? AND class_id = ?
        ";
        
        $stmt_att = $conn->prepare($att_query);
        $total_classes = 0;
        $total_present = 0;
        $total_late = 0;
        $total_absent = 0;

        if ($stmt_att) {
            $stmt_att->bind_param("ii", $student_id, $course_id);
            $stmt_att->execute();
            $att_res = $stmt_att->get_result()->fetch_assoc();
            if ($att_res) {
                $total_classes = intval($att_res['total_classes']);
                $total_present = intval($att_res['total_present']);
                $total_late = intval($att_res['total_late']);
                $total_absent = intval($att_res['total_absent']);
            }
            $stmt_att->close();
        }

        $courses[] = [
            'course_id' => $course_id,
            'course_name' => $row['course_name'],
            'teacher_name' => $row['teacher_name'] ?? 'Sean Rose Bellen',
            'total_classes' => $total_classes,
            'total_present' => $total_present,
            'total_late' => $total_late,
            'total_absent' => $total_absent
        ];
    }
    $stmt->close();
}

// Fallback kung sakaling walang nahanap sa student_enrollments pero meron sa teacher_subjects
if (empty($courses)) {
    $fallback_query = "
        SELECT 
            ts.id as course_id,
            ts.subject_name as course_name,
            CONCAT(u.first_name, ' ', u.last_name) as teacher_name
        FROM teacher_subjects ts
        LEFT JOIN users u ON ts.teacher_id = u.id
    ";
    $result_fb = $conn->query($fallback_query);
    if ($result_fb) {
        while ($row = $result_fb->fetch_assoc()) {
            $courses[] = [
                'course_id' => intval($row['course_id']),
                'course_name' => $row['course_name'],
                'teacher_name' => $row['teacher_name'] ?? 'Sean Rose Bellen',
                'total_classes' => 0,
                'total_present' => 0,
                'total_late' => 0,
                'total_absent' => 0
            ];
        }
    }
}

echo json_encode([
    'status' => 'success',
    'courses' => $courses
]);
?>