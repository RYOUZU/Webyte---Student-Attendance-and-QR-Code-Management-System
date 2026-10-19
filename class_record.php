<?php
session_start();
require_once 'db_connect.php';

// Allow both admin and faculty roles[cite: 6]
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'faculty'])) {
    header("Location: login.php");
    exit();
}

$teacher_subject_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Fetch teacher_subjects record[cite: 6]
$stmt_subj = $conn->prepare("SELECT * FROM teacher_subjects WHERE id = ? AND COALESCE(is_deleted,0)=0");
$stmt_subj->bind_param("i", $teacher_subject_id);
$stmt_subj->execute();
$subject_result = $stmt_subj->get_result();

if ($subject_result->num_rows === 0) {
    header("Location: teacher_dashboard.php");
    exit();
}
$subject = $subject_result->fetch_assoc();
$stmt_subj->close();

$teacher_id = $subject['teacher_id'];

// If logged in as faculty, ensure they only view their own subjects[cite: 6]
if ($_SESSION['role'] === 'faculty' && $teacher_id != $_SESSION['user_id']) {
    header("Location: teacher_dashboard.php");
    exit();
}

$real_subject_id = isset($subject['subject_id']) ? $subject['subject_id'] : $teacher_subject_id;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'generate_qr') {
    while (ob_get_level()) {
        ob_end_clean();
    }
    header('Content-Type: application/json');
    
    $qr_id = (string) random_int(1000000000, 9999999999);
    $qr_code_payload = json_encode(['class_id' => $teacher_subject_id, 'session_id' => $qr_id, 'qr_id' => $qr_id], JSON_UNESCAPED_SLASHES);
    $qr_stmt = $conn->prepare("INSERT INTO teacher_qr_sessions (id, subject_id, teacher_id, qr_code, created_at) VALUES (?, ?, ?, ?, NOW())");
    if (!$qr_stmt) { echo json_encode(['success' => false, 'message' => 'QR session storage is not ready. Apply the database migration.']); exit(); }
    $qr_stmt->bind_param('siis', $qr_id, $teacher_subject_id, $_SESSION['user_id'], $qr_code_payload);
    if (!$qr_stmt->execute()) { echo json_encode(['success' => false, 'message' => 'Could not save the QR session.']); exit(); }
    $qr_stmt->close();
    echo json_encode(['success' => true, 'qr_code' => $qr_code_payload, 'qr_id' => $qr_id, 'generated_at' => date('Y-m-d H:i:s')]);
    exit();
}

$stmt_user = $conn->prepare("SELECT CONCAT(first_name, ' ', middle_name, ' ', last_name) AS full_name, id_number, email, profile_pic FROM users WHERE id = ?");
$stmt_user->bind_param("i", $teacher_id);
$stmt_user->execute();
$teacher = $stmt_user->get_result()->fetch_assoc();
$stmt_user->close();

$stmt_students = $conn->prepare("
    SELECT se.id as enrollment_id, u.id as student_id, u.first_name, u.middle_name, u.last_name, u.id_number, u.email, se.enrolled_at 
    FROM student_enrollments se 
    JOIN users u ON se.student_id = u.id 
    WHERE se.subject_id = ? AND COALESCE(se.is_deleted,0)=0 AND COALESCE(u.is_deleted,0)=0
");
$stmt_students->bind_param("i", $real_subject_id);
$stmt_students->execute();
$students_result = $stmt_students->get_result();

function format_and_sort_student_name($last_name, $first_name, $middle_name) {
    $surname = trim(mb_strtoupper($last_name, 'UTF-8'));
    $firstname = trim(mb_strtoupper($first_name, 'UTF-8'));
    $middlename = trim(mb_strtoupper($middle_name, 'UTF-8'));
    
    $result = $surname;
    if (!empty($firstname)) {
        $result .= ', ' . $firstname;
    }
    if (!empty($middlename)) {
        $result .= ', ' . $middlename;
    }
    
    return $result;
}

$db_students = [];
if ($students_result && $students_result->num_rows > 0) {
    while ($s = $students_result->fetch_assoc()) {
        $s['formatted_name'] = format_and_sort_student_name($s['last_name'], $s['first_name'], $s['middle_name']);
        $db_students[] = $s;
    }
}

usort($db_students, function($a, $b) {
    return strcmp($a['formatted_name'], $b['formatted_name']);
});

$students_array = [];
for ($i = 0; $i < 50; $i++) {
    if (isset($db_students[$i])) {
        $students_array[] = $db_students[$i];
    } else {
        $students_array[] = [
            'enrollment_id' => null,
            'student_id' => null,
            'first_name' => '',
            'middle_name' => '',
            'last_name' => '',
            'formatted_name' => '',
            'id_number' => '',
            'email' => '',
            'enrolled_at' => ''
        ];
    }
}

$stmt_dates = $conn->prepare("SELECT DISTINCT DATE(scanned_at) as scan_date FROM attendance WHERE subject_id = ? ORDER BY scan_date ASC");
$stmt_dates->bind_param("i", $real_subject_id);
$stmt_dates->execute();
$dates_result = $stmt_dates->get_result();
$db_dates = [];
while ($row = $dates_result->fetch_assoc()) {
    $db_dates[] = $row['scan_date'];
}
$stmt_dates->close();

$attendance_dates = [];
for ($i = 0; $i < 25; $i++) {
    if (isset($db_dates[$i])) {
        $attendance_dates[] = $db_dates[$i];
    } else {
        $attendance_dates[] = null;
    }
}

$stmt_all_att = $conn->prepare("SELECT student_id, DATE(scanned_at) as scan_date, status FROM attendance WHERE subject_id = ?");
$stmt_all_att->bind_param("i", $real_subject_id);
$stmt_all_att->execute();
$all_att_result = $stmt_all_att->get_result();
$attendance_matrix = [];
while ($row = $all_att_result->fetch_assoc()) {
    $attendance_matrix[$row['student_id']][$row['scan_date']] = $row['status'];
}
$stmt_all_att->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Class Masterlist - KLD Attendance</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <style>
        body {
            background: linear-gradient(135deg, #f0f7f4 0%, #e8f5e9 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            display: flex;
            min-height: 100vh;
        }

        .main-wrapper {
            flex: 1;
            padding: 20px 30px;
            display: flex;
            flex-direction: column;
            gap: 12px;
            width: 100%;
            box-sizing: border-box;
        }

        .styled-card {
            background: #ffffff;
            border-radius: 14px;
            padding: 16px 22px;
            box-shadow: 0 8px 25px rgba(0, 128, 0, 0.04);
            border: 1px solid rgba(16, 138, 0, 0.08);
            width: 100%;
            box-sizing: border-box;
        }

        .styled-card:first-child {
            margin-top: 0px;
        }

        .profile-card-layout {
            display: flex;
            align-items: center;
            gap: 18px;
            background: linear-gradient(135deg, #ffffff 0%, #f4fbf7 100%);
            border-left: 6px solid #108a00;
            position: relative;
            padding: 10px 22px;
        }

        .right-top-container {
            position: absolute;
            top: 8px;
            right: 18px;
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 6px;
        }

        .signed-in-badge {
            font-size: 11px;
            font-weight: 700;
            color: #108a00;
            background: #e8f5e9;
            padding: 2px 8px;
            border-radius: 20px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .btn-generate-qr {
            background-color: #108a00;
            color: #ffffff;
            border: none;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s ease;
            box-shadow: 0 2px 5px rgba(16, 138, 0, 0.2);
        }

        .btn-generate-qr:hover {
            background-color: #0d7000;
            transform: translateY(-1px);
        }

        .profile-content {
            display: flex;
            align-items: center;
            gap: 16px;
            width: 100%;
        }

        .user-profile-avatar {
            width: 55px;
            height: 55px;
            min-width: 55px;
            border-radius: 50%;
            background: #e8f5e9;
            color: #108a00;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            border: 2px solid #108a00;
            overflow: hidden;
            box-sizing: border-box;
        }

        .user-profile-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
        }

        .user-details h2 {
            margin: 0 0 2px 0;
            color: #1a331e;
            font-size: 19px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .user-details p {
            margin: 2px 0;
            color: #4f5d52;
            font-size: 12.5px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .user-details p i {
            color: #108a00;
            font-size: 13px;
            width: 14px;
            text-align: center;
        }

        .user-details p span {
            color: #2c3e50;
            font-weight: 600;
        }

        .class-info-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #f0f7f4;
            padding-bottom: 12px;
            margin-bottom: 14px;
        }

        .class-info-header h3 {
            margin: 0;
            font-size: 18px;
            color: #1a331e;
            font-weight: 800;
        }

        .class-meta {
            font-size: 13px;
            color: #555;
            display: flex;
            gap: 15px;
        }

        .class-meta span strong {
            color: #108a00;
        }

        .header-buttons {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .btn-view-attendance {
            background-color: #108a00;
            color: #ffffff;
            border: none;
            padding: 6px 14px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: background-color 0.2s;
        }

        .btn-view-attendance:hover {
            background-color: #0d7000;
        }

        .btn-back {
            background-color: #6c757d;
            color: #ffffff;
            border: none;
            padding: 6px 14px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: background-color 0.2s;
        }

        .btn-back:hover {
            background-color: #5a6268;
        }

        .table-container {
            width: 100%;
            overflow-x: auto;
        }

        .masterlist-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
            font-size: 13px;
        }

        .masterlist-table th {
            background-color: #f4fbf7;
            color: #1a331e;
            padding: 10px 12px;
            font-weight: 700;
            border-bottom: 2px solid #d1e7dd;
        }

        .masterlist-table td {
            padding: 10px 12px;
            border-bottom: 1px solid #eef2f0;
            color: #333;
        }

        .masterlist-table tr:hover {
            background-color: #fafdfb;
        }

        .qr-modal, .attendance-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }

        .attendance-modal.fullscreen-mode {
            background-color: #d0f0d8 !important;
            padding: 0;
        }

        .attendance-modal.fullscreen-mode .attendance-modal-content {
            max-width: 100% !important;
            width: 100% !important;
            height: 100vh !important;
            border-radius: 0 !important;
            box-shadow: none !important;
            display: flex;
            flex-direction: column;
            padding: 0px !important;
            margin: 0 !important;
            box-sizing: border-box;
            background-color: #d0f0d8 !important;
        }

        .attendance-modal.fullscreen-mode .attendance-table-scroll {
            max-height: calc(100vh - 120px) !important;
            flex: 1;
            border: none !important;
            border-radius: 0 !important;
            margin: 0 !important;
        }

        .qr-modal-content, .attendance-modal-content {
            background: #d0f0d8;
            padding: 0px;
            border-radius: 0px;
            text-align: left;
            max-width: 100%;
            width: 100%;
            height: 100vh;
            box-shadow: none;
            position: relative;
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
            margin: 0;
        }

        .qr-modal-content {
            max-width: 350px;
            height: auto;
            border-radius: 12px;
            padding: 24px;
            text-align: center;
            background: #ffffff;
            margin: auto;
        }

        .qr-modal-close, .attendance-modal-close {
            position: absolute;
            top: 10px;
            right: 15px;
            font-size: 22px;
            cursor: pointer;
            color: #333;
            background: none;
            border: none;
            z-index: 10;
        }

        #qrcode {
            display: flex;
            justify-content: center;
            margin: 15px 0;
        }

        #qrcode img {
            border: 4px solid #108a00;
            border-radius: 8px;
            padding: 5px;
        }

        .att-modal-top-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 20px;
            background: #d0f0d8;
            border-bottom: 2px solid #b5d8bd;
        }

        .att-teacher-info-box {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .att-teacher-avatar {
            width: 40px;
            height: 40px;
            min-width: 40px;
            border-radius: 50%;
            border: 2px solid #108a00;
            overflow: hidden;
            background: #e8f5e9;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #108a00;
        }

        .att-teacher-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .att-teacher-details h4 {
            margin: 0;
            font-size: 13px;
            color: #1a331e;
            font-weight: 800;
            text-transform: uppercase;
        }

        .att-teacher-details p {
            margin: 2px 0 0 0;
            font-size: 11px;
            color: #555;
            font-weight: 600;
            text-transform: uppercase;
        }

        .att-kld-logo-box {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .att-kld-text-details {
            text-align: right;
        }

        .att-kld-text-details h4 {
            margin: 0;
            font-size: 13px;
            color: #1a331e;
            font-weight: 800;
            text-transform: uppercase;
        }

        .att-kld-text-details p {
            margin: 2px 0 0 0;
            font-size: 11px;
            color: #555;
            font-weight: 600;
            text-transform: uppercase;
        }

        .att-kld-logo {
            width: 40px;
            height: 40px;
            min-width: 40px;
            object-fit: contain;
            border: 2px solid #108a00;
            border-radius: 50%;
            padding: 2px;
            box-sizing: border-box;
            background: #ffffff;
        }

        .att-section-title {
            text-align: center;
            font-size: 16px;
            font-weight: 800;
            color: #108a00;
            text-transform: uppercase;
            margin: 5px 0;
            letter-spacing: 0.5px;
            background: #d0f0d8;
        }

        .table-toolbar {
            display: flex;
            gap: 8px;
            padding: 5px 20px;
            align-items: center;
            justify-content: flex-end;
            background: #d0f0d8;
        }

        .btn-toolbar-exit {
            background: #f0f7f4;
            border: 1px solid #108a00;
            color: #108a00;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            text-decoration: none;
        }

        .btn-toolbar-exit:hover {
            background: #e2f0ea;
        }

        .attendance-table-scroll {
            width: 100%;
            overflow-x: auto;
            overflow-y: auto;
            flex: 1;
            border: none;
            border-radius: 0px;
            background: #ffffff;
            margin: 0;
        }

        .excel-table {
            border-collapse: separate;
            border-spacing: 0;
            width: 100%;
            font-size: 12px;
            background: #ffffff;
            margin: 0;
        }

        .excel-table th, .excel-table td {
            border-right: 2px solid #555555;
            border-bottom: 2px solid #555555;
            padding: 6px 8px;
            text-align: center;
        }

        .excel-table th {
            border-top: 2px solid #555555;
            background-color: #eaeaea;
            color: #1a331e;
            font-weight: 800;
            text-transform: uppercase;
            position: sticky;
            top: 0;
            z-index: 1;
            text-align: center !important;
        }

        .excel-table th:nth-child(1),
        .excel-table th:nth-child(2) {
            position: sticky;
            top: 0;
            z-index: 5;
            background-color: #eaeaea;
        }

        .excel-table th:nth-child(1), .excel-table td:nth-child(1) {
            position: sticky;
            left: 0;
            background-color: #f2f2f2;
            z-index: 3;
            text-align: center;
            width: 40px;
            min-width: 40px;
            max-width: 40px;
            border-left: none;
        }

        .excel-table th:nth-child(2),
        .excel-table td:nth-child(2) {
            position: sticky;
            left: 40px;
            background-color: #f2f2f2;
            z-index: 3;
            text-align: left;
            width: 250px;
            min-width: 100px;
            max-width: 800px;
            border-right: 3px solid #333333;
            white-space: normal !important;
            word-break: break-word;
            overflow-wrap: break-word;
            user-select: none;
        }

        .excel-table td:nth-child(1),
        .excel-table td:nth-child(2) {
            position: sticky;
            background-color: #f2f2f2;
        }

        .excel-table th.summary-col, .excel-table td.summary-col {
            background-color: #f9f9f9;
            font-weight: 700;
            width: 95px;
            min-width: 95px;
            max-width: 95px;
            font-size: 11px;
            line-height: 1.2;
            vertical-align: middle;
            white-space: normal !important;
            word-break: break-word;
        }

        .badge-status {
            padding: 2px 6px;
            border-radius: 3px;
            font-weight: 600;
            font-size: 10.5px;
            display: inline-block;
        }
        .bg-green { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .bg-yellow { background-color: #fff3cd; color: #856404; border: 1px solid #ffeeba; }
        .bg-red { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    </style>
</head>
<body>
    <nav>
        <div class="logo-container">
            <img src="kld-logo.png" alt="KLD Logo" class="sidebar-logo">
            <h3 class="brand-title">KLD</h3>
            <span class="brand-subtitle">Attendance Monitoring System</span>
        </div>
        <div class="nav-links">
            <a href="teacher_dashboard.php"><i class="fa-solid fa-house"></i> Home</a>
            <a href="about.php"><i class="fa-solid fa-circle-info"></i> About us</a>
            <a href="logout.php"><i class="fa-solid fa-right-from-bracket"></i> Log-out</a>
            <a href="teacher_dashboard.php" class="active"><i class="fa-solid fa-user"></i> My Account</a>
        </div>
    </nav>

    <main class="main-wrapper">
        <div class="styled-card profile-card-layout">
            <div class="right-top-container">
                <span class="signed-in-badge">Signed in as <?php echo ucfirst(htmlspecialchars($_SESSION['role'])); ?></span>
                <?php if ($_SESSION['role'] === 'faculty'): ?>
                <button type="button" class="btn-generate-qr" onclick="generateQRCode()">
                    <i class="fa-solid fa-qrcode"></i> Generate QR Code
                </button>
                <?php endif; ?>
            </div>
            <div class="profile-content">
                <div class="user-profile-avatar">
                    <?php if (!empty($teacher['profile_pic']) && file_exists($teacher['profile_pic'])): ?>
                        <img src="<?php echo htmlspecialchars($teacher['profile_pic']); ?>" alt="Profile Picture">
                    <?php else: ?>
                        <i class="fa-solid fa-user"></i>
                    <?php endif; ?>
                </div>
                <div class="user-details">
                    <h2><?php echo strtoupper(htmlspecialchars($teacher['full_name'] ?? 'Teacher')); ?></h2>
                    <p><i class="fa-solid fa-id-card"></i> ID Number: <span><?php echo htmlspecialchars($teacher['id_number'] ?? 'N/A'); ?></span></p>
                    <p><i class="fa-solid fa-envelope"></i> Email: <span><?php echo htmlspecialchars($teacher['email'] ?? 'N/A'); ?></span></p>
                </div>
            </div>
        </div>

        <div class="styled-card">
            <div class="class-info-header">
                <div>
                    <h3><i class="fa-solid fa-book" style="color: #108a00; margin-right: 8px;"></i><?php echo htmlspecialchars($subject['subject_name']); ?></h3>
                    <div class="class-meta" style="margin-top: 6px;">
                        <span><strong>Section:</strong> <?php echo htmlspecialchars($subject['section_name']); ?></span>
                        <span><strong>Class Code:</strong> <?php echo htmlspecialchars($subject['class_code']); ?></span>
                    </div>
                </div>
                <div class="header-buttons">
                    <button type="button" class="btn-view-attendance" onclick="openAttendanceModal()">
                        <i class="fa-solid fa-table-cells"></i> View Attendance Records
                    </button>
                    <a href="<?php echo ($_SESSION['role'] === 'admin') ? 'javascript:history.back()' : 'teacher_dashboard.php'; ?>" class="btn-back">
                        <i class="fa-solid fa-arrow-left"></i> Back
                    </a>
                </div>
            </div>

            <h4 style="margin: 10px 0 12px 0; font-size: 15px; color: #1a331e;">Enrolled Students Masterlist (50 Rows Target)</h4>

            <div class="table-container">
                <table class="masterlist-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Student ID Number</th>
                            <th>Full Name (Surname, First Name, Middle Name)</th>
                            <th>Email Address</th>
                            <th>Enrolled Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $counter = 1;
                        foreach ($students_array as $student): 
                        ?>
                            <tr>
                                <td><?php echo $counter++; ?></td>
                                <td><?php echo htmlspecialchars($student['id_number']); ?></td>
                                <td><strong><?php echo !empty($student['formatted_name']) ? htmlspecialchars($student['formatted_name']) : ''; ?></strong></td>
                                <td><?php echo htmlspecialchars($student['email']); ?></td>
                                <td><?php echo htmlspecialchars($student['enrolled_at']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- QR Code Modal -->
    <div id="qrModal" class="qr-modal">
        <div class="qr-modal-content">
            <span class="qr-modal-close" onclick="closeQRModal()">&times;</span>
            <h3 style="margin-top: 0; color: #1a331e;">Class Attendance QR Code</h3>
            <p style="font-size: 12px; color: #666;">Students can scan this code to record attendance for today.</p>
            <div id="qrcode"></div>
            <p style="font-size: 11px; color: #888;">Petsa: <strong><?php echo date('F j, Y'); ?></strong></p>
        </div>
    </div>

    <!-- Attendance Modal -->
    <div id="attendanceModal" class="attendance-modal fullscreen-mode" style="display: none;">
        <div class="attendance-modal-content" id="attendanceModalContent">
            
            <div class="att-modal-top-header">
                <div class="att-teacher-info-box">
                    <div class="att-teacher-avatar">
                        <?php if (!empty($teacher['profile_pic']) && file_exists($teacher['profile_pic'])): ?>
                            <img src="<?php echo htmlspecialchars($teacher['profile_pic']); ?>" alt="Teacher">
                        <?php else: ?>
                            <i class="fa-solid fa-user"></i>
                        <?php endif; ?>
                    </div>
                    <div class="att-teacher-details">
                        <h4><?php echo strtoupper(htmlspecialchars($teacher['full_name'] ?? 'TEACHER')); ?></h4>
                        <p><?php echo strtoupper(htmlspecialchars($subject['subject_name'])); ?></p>
                    </div>
                </div>
                <div class="att-kld-logo-box">
                    <div class="att-kld-text-details">
                        <h4>KOLEHIYO NG LUNGSOD NG DASMARIÑAS</h4>
                        <p>BUILDING THE FOUNDATION FOR THE DASMARINEÑOS</p>
                    </div>
                    <img src="kld-logo.png" alt="KLD Logo" class="att-kld-logo">
                </div>
            </div>

            <div class="att-section-title">
                SECTION: <?php echo strtoupper(htmlspecialchars($subject['section_name'])); ?>
            </div>

            <div class="table-toolbar">
                <button type="button" class="btn-toolbar-exit" onclick="closeAttendanceModal()">
                    <i class="fa-solid fa-arrow-left"></i> <span>CLOSE ATTENDANCE VIEW</span>
                </button>
            </div>
            
            <div class="attendance-table-scroll" id="attendanceTableScrollContainer">
                <table class="excel-table" id="excelAttendanceTable">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th id="studentNameTh">STUDENT NAME</th>
                            <?php 
                            foreach ($attendance_dates as $scan_date) {
                                if ($scan_date) {
                                    $date_label = strtoupper(date('m/d/Y', strtotime($scan_date)));
                                    echo '<th>' . $date_label . '</th>';
                                } else {
                                    echo '<th></th>';
                                }
                            }
                            ?>
                            <th class="summary-col">TOTAL NUMBER OF CLASS DAYS</th>
                            <th class="summary-col">TOTAL NUMBER OF DAYS PRESENT</th>
                            <th class="summary-col">TOTAL NUMBER OF DAYS LATE</th>
                            <th class="summary-col">TOTAL NUMBER OF DAYS ABSENT</th>
                        </tr>
                    </thead>
                    <tbody id="excelAttendanceBody">
                        <?php 
                        $actual_class_days_count = count(array_filter($attendance_dates));
                        $row_index = 1;
                        foreach ($students_array as $student) {
                            echo '<tr>';
                            echo '<td style="text-align: center; width: 40px; min-width: 40px; max-width: 40px;">' . $row_index++ . '</td>';
                            
                            $student_name_display = !empty($student['formatted_name']) ? '<strong>' . htmlspecialchars($student['formatted_name']) . '</strong>' : '';
                            echo '<td style="text-align: left; width: max-content; min-width: max-content; white-space: nowrap; word-break: normal;" class="name-data-cell">' . $student_name_display . '</td>';
                            
                            $present_count = 0;
                            $late_count = 0;
                            
                            foreach ($attendance_dates as $scan_date) {
                                if ($scan_date && !empty($student['student_id'])) {
                                    $s_id = $student['student_id'];
                                    $status = $attendance_matrix[$s_id][$scan_date] ?? null;
                                    $formatted_date_title = date('F j, Y', strtotime($scan_date));
                                    
                                    if ($status) {
                                        if (strtolower($status) === 'late') {
                                            echo '<td title="Date: ' . $formatted_date_title . '"><span class="badge-status bg-yellow">LATE</span></td>';
                                            $late_count++;
                                        } else {
                                            echo '<td title="Date: ' . $formatted_date_title . '"><span class="badge-status bg-green">PRESENT</span></td>';
                                            $present_count++;
                                        }
                                    } else {
                                        echo '<td title="Date: ' . $formatted_date_title . '"></td>';
                                    }
                                } else {
                                    echo '<td></td>';
                                }
                            }
                            
                            if (!empty($student['student_id'])) {
                                $absent_count = $actual_class_days_count - ($present_count + $late_count);
                                if ($absent_count < 0) $absent_count = 0;
                                
                                echo '<td class="summary-col">' . $actual_class_days_count . '</td>';
                                echo '<td class="summary-col" style="color: #155724; font-weight: bold;">' . $present_count . '</td>';
                                echo '<td class="summary-col" style="color: #856404; font-weight: bold;">' . $late_count . '</td>';
                                echo '<td class="summary-col" style="color: #721c24; font-weight: bold;">' . $absent_count . '</td>';
                            } else {
                                echo '<td class="summary-col"></td>';
                                echo '<td class="summary-col"></td>';
                                echo '<td class="summary-col"></td>';
                                echo '<td class="summary-col"></td>';
                            }
                            
                            echo '</tr>';
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const savedWidth = localStorage.getItem('kld_name_column_width');
            if (savedWidth) {
                applyNameColumnWidth(savedWidth);
            }
        });

        function applyNameColumnWidth(widthVal) {
            const table = document.getElementById('excelAttendanceTable');
            if (!table) return;
            const rows = table.rows;
            for (let i = 0; i < rows.length; i++) {
                if (rows[i].cells[1]) {
                    rows[i].cells[1].style.width = widthVal + 'px';
                    rows[i].cells[1].style.minWidth = widthVal + 'px';
                    rows[i].cells[1].style.maxWidth = widthVal + 'px';
                }
            }
        }

        function generateQRCode() {
            const formData = new FormData();
            formData.append('action', 'generate_qr');

            fetch('class_record.php?id=<?php echo $teacher_subject_id; ?>', {
                method: 'POST',
                body: formData
            })
            .then(async response => {
                const text = await response.text();
                try {
                    return JSON.parse(text);
                } catch (err) {
                    console.error('Server Output:', text);
                    throw new Error('Invalid JSON response.');
                }
            })
            .then(data => {
                if (data.success) {
                    const qrContainer = document.getElementById('qrcode');
                    qrContainer.innerHTML = '';
                    
                    new QRCode(qrContainer, {
                        text: data.qr_code,
                        width: 200,
                        height: 200,
                        colorDark: "#108a00",
                        colorLight: "#ffffff",
                        correctLevel: QRCode.CorrectLevel.H
                    });

                    document.getElementById('qrModal').style.display = 'flex';
                } else {
                    alert('Error: ' + (data.message || 'Unable to generate QR Code.'));
                }
            })
            .catch(error => {
                console.error('Fetch Error:', error);
                alert('An error occurred while generating the QR code.');
            });
        }

        function closeQRModal() {
            document.getElementById('qrModal').style.display = 'none';
        }

        function openAttendanceModal() {
            document.getElementById('attendanceModal').style.display = 'flex';
        }

        function closeAttendanceModal() {
            document.getElementById('attendanceModal').style.display = 'none';
        }

        const table = document.getElementById('excelAttendanceTable');
        
        let isResizing = false;
        let startX, startWidth;

        table.addEventListener('mousedown', function(e) {
            const targetCell = e.target.closest('th, td');
            if (!targetCell) return;
            
            const colIndex = targetCell.cellIndex;
            if (colIndex === 1) {
                const rect = targetCell.getBoundingClientRect();
                if (e.clientX >= rect.right - 8) {
                    isResizing = true;
                    startX = e.clientX;
                    startWidth = targetCell.offsetWidth;
                    e.preventDefault();
                }
            }
        });

        document.addEventListener('mousemove', function(e) {
            if (!isResizing) return;
            const diff = e.clientX - startX;
            let newWidth = startWidth + diff;
            if (newWidth < 100) newWidth = 100;
            if (newWidth > 800) newWidth = 800;

            applyNameColumnWidth(newWidth);
        });

        document.addEventListener('mouseup', function() {
            if (isResizing) {
                isResizing = false;
                const targetTh = table.rows[0].cells[1];
                if (targetTh) {
                    localStorage.setItem('kld_name_column_width', targetTh.offsetWidth);
                }
            }
        });

        window.onclick = function(event) {
            const qrModal = document.getElementById('qrModal');
            if (event.target == qrModal) {
                closeQRModal();
            }
        }
    </script>
</body>
</html>