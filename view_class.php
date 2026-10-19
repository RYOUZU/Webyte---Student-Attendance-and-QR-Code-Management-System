<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$section_id = isset($_GET['section_id']) ? intval($_GET['section_id']) : 0;

if ($section_id <= 0) {
    header("Location: manage_program.php");
    exit();
}

$stmt_sec = $conn->prepare("SELECT s.*, p.program_name FROM sections s JOIN programs p ON s.program_id = p.id WHERE s.id = ?");
$stmt_sec->bind_param("i", $section_id);
$stmt_sec->execute();
$current_section = $stmt_sec->get_result()->fetch_assoc();
$stmt_sec->close();

if (!$current_section) {
    header("Location: manage_program.php");
    exit();
}

$section_name = $current_section['section_name'];

// Handle Student Transfer Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['transfer_student_id'], $_POST['new_section_name'])) {
    $transfer_student_id = intval($_POST['transfer_student_id']);
    $new_section_name = trim($_POST['new_section_name']);
    
    $stmt_chk_sec = $conn->prepare("SELECT id FROM sections WHERE section_name = ?");
    $stmt_chk_sec->bind_param("s", $new_section_name);
    $stmt_chk_sec->execute();
    if ($stmt_chk_sec->get_result()->num_rows > 0) {
        // Update user's section
        $stmt_upd = $conn->prepare("UPDATE users SET section = ? WHERE id = ? AND role = 'student'");
        $stmt_upd->bind_param("si", $new_section_name, $transfer_student_id);
        $stmt_upd->execute();
        $stmt_upd->close();
        
        // Clean up old attendance / class mapping records so they no longer appear under previous teachers' active class rosters
        $stmt_del_att = $conn->prepare("DELETE FROM attendance WHERE student_id = ?");
        if ($stmt_del_att) {
            $stmt_del_att->bind_param("i", $transfer_student_id);
            $stmt_del_att->execute();
            $stmt_del_att->close();
        }
    }
    $stmt_chk_sec->close();
    
    header("Location: view_class.php?section_id=" . $section_id);
    exit();
}

if (isset($_GET['delete_student'])) {
    $del_student_id = intval($_GET['delete_student']);
    
    // Delete student user record
    $enroll_soft = $conn->prepare("UPDATE student_enrollments SET is_deleted=1, deleted_at=NOW() WHERE student_id=? AND COALESCE(is_deleted,0)=0");
    if ($enroll_soft) { $enroll_soft->bind_param("i", $del_student_id); $enroll_soft->execute(); $enroll_soft->close(); }
    $stmt_del = $conn->prepare("UPDATE users SET is_deleted = 1, deleted_at = NOW() WHERE id = ? AND role = 'student'");
    $stmt_del->bind_param("i", $del_student_id);
    $stmt_del->execute();
    $stmt_del->close();
    
    header("Location: view_class.php?section_id=" . $section_id);
    exit();
}

// Fetch all programs and sections for the transfer feature
$programs_query = $conn->query("SELECT * FROM programs ORDER BY program_name ASC");
$all_programs = [];
while ($p = $programs_query->fetch_assoc()) {
    $prog_id = $p['id'];
    $prog_name = $p['program_name'];
    $sec_stmt = $conn->prepare("SELECT id, section_name, year_level FROM sections WHERE program_id = ? ORDER BY year_level, section_name ASC");
    $sec_stmt->bind_param("i", $prog_id);
    $sec_stmt->execute();
    $sec_res = $sec_stmt->get_result();
    $sections = [];
    while ($s = $sec_res->fetch_assoc()) {
        $sections[] = $s;
    }
    $sec_stmt->close();
    
    $all_programs[] = [
        'id' => $prog_id,
        'program_name' => $prog_name,
        'sections' => $sections
    ];
}
$programs_json = json_encode($all_programs);

$stmt_students = $conn->prepare("SELECT *, CONCAT(first_name, ' ', last_name) AS name FROM users WHERE role = 'student' AND COALESCE(is_deleted,0)=0 AND section = ? ORDER BY last_name ASC");
$stmt_students->bind_param("s", $section_name);
$stmt_students->execute();
$students_result = $stmt_students->get_result();
$enrolled_count = $students_result->num_rows;
$stmt_students->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Class Record: <?php echo htmlspecialchars($section_name); ?> - KLD Attendance</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
    <style>
        body { 
            background: linear-gradient(135deg, #f4fbf7 0%, #e8f5ec 100%); 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            margin: 0; 
            display: flex; 
            min-height: 100vh; 
        }
        
        .main-wrapper { 
            flex: 1; 
            padding: 25px 35px; 
            display: flex; 
            flex-direction: column; 
            gap: 20px; 
            width: 100%; 
            box-sizing: border-box; 
        }

        .styled-card { 
            background: rgba(255, 255, 255, 0.95); 
            backdrop-filter: blur(10px);
            border-radius: 24px; 
            padding: 30px 35px; 
            box-shadow: 0 12px 40px rgba(16, 138, 0, 0.08); 
            border: 1px solid rgba(16, 138, 0, 0.12); 
            width: 100%; 
            box-sizing: border-box; 
        }

        .school-header-center {
            text-align: center;
            margin-bottom: 25px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
            border-bottom: 2px solid #f0f4f1;
            padding-bottom: 20px;
        }

        .school-logo-img {
            width: 70px;
            height: 70px;
            object-fit: cover;
            border-radius: 50%;
            margin-bottom: 5px;
            border: 2px solid #108a00;
        }

        .school-header-center h2 {
            font-size: 20px;
            font-weight: 800;
            color: #112918;
            margin: 0;
            letter-spacing: 0.5px;
        }

        .school-header-center p {
            font-size: 13px;
            color: #475569;
            margin: 0;
            font-weight: 600;
        }

        .section-title-center {
            text-align: center;
            margin-top: 15px;
            margin-bottom: 5px;
        }

        .section-title-center h3 {
            font-size: 22px;
            font-weight: 800;
            color: #108a00;
            margin: 0;
        }

        .enrolled-count-text {
            text-align: center;
            font-size: 14px;
            color: #334155;
            font-weight: 600;
            margin-bottom: 15px;
        }

        .table-controls {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: 8px;
            margin-bottom: 10px;
        }

        .zoom-btn {
            background: #ffffff;
            border: 1px solid #d1d5db;
            color: #374151;
            padding: 5px 12px;
            font-size: 12px;
            font-weight: 700;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .zoom-btn:hover {
            background: #108a00;
            color: #ffffff;
            border-color: #108a00;
        }

        .back-btn { 
            display: inline-flex; 
            align-items: center; 
            gap: 8px; 
            color: #108a00; 
            text-decoration: none; 
            font-weight: 700; 
            font-size: 14px; 
            margin-bottom: 5px; 
            background: #ffffff;
            padding: 8px 16px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            border: 1px solid #e2e8e4;
            transition: all 0.2s ease; 
        }

        .back-btn:hover { 
            background: #108a00;
            color: #fff;
            border-color: #108a00;
        }

        .table-responsive {
            width: 100%;
            overflow-x: auto;
        }

        .zoomable-table-container {
            font-size: 13px;
            transition: font-size 0.1s ease;
        }

        .styled-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 5px;
            text-align: left;
        }

        .styled-table th {
            background: #f8faf9;
            color: #112918;
            padding: 1em 1.2em;
            font-weight: 700;
            border-bottom: 2px solid #e2e8e4;
            white-space: nowrap;
        }

        .styled-table td {
            padding: 1em 1.2em;
            border-bottom: 1px solid #edf2f7;
            color: #334155;
            vertical-align: middle;
            white-space: nowrap;
        }

        .styled-table tr:hover {
            background: #fcfdfd;
        }

        .student-name-link {
            color: #1e293b;
            text-decoration: none;
            font-weight: 700;
            cursor: pointer;
            transition: color 0.2s ease;
        }

        .student-name-link:hover {
            color: #108a00;
            text-decoration: none;
        }

        .profile-cell-wrapper {
            display: flex;
            align-items: center;
            gap: 10px;
            position: relative;
        }

        .student-avatar {
            width: 2.5em;
            height: 2.5em;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #e2e8e4;
            cursor: pointer;
            transition: transform 0.2s ease, border-color 0.2s ease;
        }

        .student-avatar:hover {
            border-color: #108a00;
            transform: scale(1.05);
        }

        /* Custom Context Menu - Nakatabi at Pantay sa Picture */
        #customContextMenu {
            display: none;
            position: absolute;
            left: calc(100% + 10px);
            top: 50%;
            transform: translateY(-50%);
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
            border: 1px solid #e2e8e4;
            z-index: 2000;
            padding: 6px;
            min-width: 180px;
            animation: fadeIn 0.15s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-50%) scale(0.95); }
            to { opacity: 1; transform: translateY(-50%) scale(1); }
        }

        .context-menu-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 14px;
            font-size: 13px;
            font-weight: 600;
            color: #334155;
            background: transparent;
            border: none;
            width: 100%;
            text-align: left;
            border-radius: 8px;
            cursor: pointer;
            transition: background 0.2s, color 0.2s;
        }

        .context-menu-item i {
            color: #108a00;
            font-size: 14px;
        }

        .context-menu-item:hover {
            background: #e8f8f0;
            color: #108a00;
        }

        .badge-role {
            background: #e8f8f0;
            color: #108a00;
            padding: 0.3em 0.8em;
            border-radius: 6px;
            font-weight: 700;
            font-size: 0.85em;
            text-transform: uppercase;
        }

        .btn-delete { 
            background: #fee2e2; 
            color: #ef4444; 
            border: none; 
            width: 2.4em; 
            height: 2.4em; 
            border-radius: 8px; 
            cursor: pointer; 
            display: inline-flex; 
            align-items: center; 
            justify-content: center; 
            transition: all 0.2s ease; 
            text-decoration: none; 
        }

        .btn-delete:hover { 
            background: #ef4444; 
            color: white; 
            transform: scale(1.05);
        }

        /* Transfer Modal Styles */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
            justify-content: center;
            align-items: center;
            z-index: 3000;
            padding: 20px;
            box-sizing: border-box;
        }

        .modal-content {
            background: #ffffff;
            border-radius: 20px;
            width: 100%;
            max-width: 1100px;
            padding: 30px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
            position: relative;
            animation: modalPop 0.3s ease;
            max-height: 90vh;
            overflow-y: auto;
        }

        .transfer-modal-box {
            background: #ffffff;
            border-radius: 20px;
            width: 100%;
            max-width: 450px;
            padding: 30px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
            position: relative;
            animation: modalPop 0.3s ease;
        }

        @keyframes modalPop {
            0% { transform: scale(0.9); opacity: 0; }
            100% { transform: scale(1); opacity: 1; }
        }

        .modal-close {
            position: absolute;
            top: 20px;
            right: 20px;
            background: #f1f5f9;
            border: none;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            font-size: 16px;
            color: #64748b;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
        }

        .modal-close:hover {
            background: #fee2e2;
            color: #ef4444;
        }

        .form-group {
            margin-bottom: 16px;
            text-align: left;
        }

        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 700;
            color: #334155;
            margin-bottom: 6px;
        }

        .form-group select {
            width: 100%;
            padding: 10px 14px;
            border: 1.5px solid #cbd5e1;
            border-radius: 10px;
            font-size: 14px;
            outline: none;
            background: #fff;
            color: #1e293b;
        }

        .form-group select:focus {
            border-color: #108a00;
            box-shadow: 0 0 0 3px rgba(16, 138, 0, 0.1);
        }

        .btn-submit-transfer {
            background: #108a00;
            color: #ffffff;
            border: none;
            width: 100%;
            padding: 12px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 14px;
            cursor: pointer;
            transition: background 0.2s;
            margin-top: 10px;
        }

        .btn-submit-transfer:hover {
            background: #0d6d00;
        }

        .modal-profile-header {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 25px;
            border-bottom: 1px solid #e2e8e4;
            padding-bottom: 20px;
        }

        .modal-avatar {
            width: 75px;
            height: 75px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #108a00;
        }

        .modal-student-info h4 {
            margin: 0 0 5px 0;
            font-size: 20px;
            color: #112918;
            font-weight: 800;
        }

        .modal-student-info p {
            margin: 0;
            font-size: 14px;
            color: #64748b;
            font-weight: 600;
        }

        .modal-table-title {
            font-size: 15px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        .modal-table th {
            background: #f8faf9;
            color: #112918;
            padding: 12px 10px;
            font-weight: 700;
            border-bottom: 2px solid #e2e8e4;
            text-align: left;
        }

        .modal-table td {
            padding: 12px 10px;
            border-bottom: 1px solid #edf2f7;
            color: #334155;
        }

        .teacher-name-link {
            color: #111827;
            text-decoration: none;
            font-weight: 700;
            cursor: pointer;
            transition: color 0.2s ease;
        }

        .teacher-name-link:hover {
            color: #108a00;
            text-decoration: underline;
        }

        .back-to-summary-btn {
            background: #f1f5f9;
            border: 1px solid #cbd5e1;
            color: #334155;
            padding: 5px 12px;
            font-size: 12px;
            font-weight: 600;
            border-radius: 6px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .class-record-grid-wrapper {
            overflow-x: auto;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            background: #fff;
        }

        .class-record-sheet {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
            text-align: center;
        }

        .class-record-sheet th, .class-record-sheet td {
            border: 1px solid #cbd5e1;
            padding: 8px 4px;
            white-space: nowrap;
        }

        .class-record-sheet th {
            background: #f1f5f9;
            color: #112918;
            font-weight: 700;
            vertical-align: middle;
        }

        .class-record-sheet .col-student-name {
            text-align: left;
            padding-left: 12px;
            font-weight: 700;
            color: #111827;
            min-width: 220px;
        }

        .date-col-header {
            writing-mode: vertical-lr;
            transform: rotate(180deg);
            height: 90px;
            font-size: 11px;
            padding: 4px 2px;
            color: #475569;
        }
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
            <a href="admin_dashboard.php"><i class="fa-solid fa-house"></i> Home</a>
            <a href="about.php"><i class="fa-solid fa-circle-info"></i> About us</a>
            <a href="logout.php"><i class="fa-solid fa-right-from-bracket"></i> Log-out</a>
            <a href="admin_dashboard.php" class="active"><i class="fa-solid fa-user"></i> My Account</a>
        </div>
    </nav>

    <main class="main-wrapper">
        <div>
            <a href="view_sections.php?program_id=<?php echo $current_section['program_id']; ?>&year=<?php echo urlencode($current_section['year_level']); ?>" class="back-btn">
                <i class="fa-solid fa-arrow-left"></i> Back to Sections
            </a>
        </div>

        <div class="styled-card">
            <div class="school-header-center">
                <img src="kld-logo.png" alt="KLD Logo" class="school-logo-img">
                <h2>KOLEHIYO NG LUNGSOD NG DASMARIÑAS</h2>
                <p>Building the Foundation for the Dasmarineños</p>
            </div>

            <div class="section-title-center">
                <h3><?php echo htmlspecialchars($section_name); ?></h3>
            </div>

            <div class="enrolled-count-text">
                Number of Enrolled Students: <strong><?php echo $enrolled_count; ?></strong>
            </div>

            <div class="table-controls">
                <span style="font-size: 12px; font-weight: 600; color: #64748b;">Table Zoom:</span>
                <button type="button" class="zoom-btn" onclick="adjustZoom(-1)" title="Paliitin ang Text">-</button>
                <button type="button" class="zoom-btn" onclick="resetZoom()" title="Original Size">Reset</button>
                <button type="button" class="zoom-btn" onclick="adjustZoom(1)" title="Palakihin ang Text">+</button>
            </div>

            <div class="table-responsive">
                <div id="zoomableTable" class="zoomable-table-container">
                    <table class="styled-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Profile Photo</th>
                                <th>Full Name</th>
                                <th>ID Number</th>
                                <th>Role</th>
                                <th>Section</th>
                                <th>KLD Email</th>
                                <th style="text-align: center;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($students_result && $students_result->num_rows > 0): ?>
                                <?php $count = 1; while($student = $students_result->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo $count++; ?></td>
                                        <td>
                                            <div class="profile-cell-wrapper" id="wrapper-<?php echo $student['id']; ?>">
                                                <img src="<?php echo !empty($student['profile_pic']) ? htmlspecialchars($student['profile_pic']) : 'kld-logo.png'; ?>" 
                                                     alt="Profile" 
                                                     class="student-avatar"
                                                     oncontextmenu="showContextMenu(event, this, <?php echo $student['id']; ?>, '<?php echo htmlspecialchars(addslashes($student['name'])); ?>')">
                                            </div>
                                        </td>
                                        <td>
                                            <a class="student-name-link" onclick="openStudentModal(
                                                '<?php echo htmlspecialchars(addslashes($student['name'])); ?>', 
                                                '<?php echo !empty($student['profile_pic']) ? htmlspecialchars($student['profile_pic']) : 'kld-logo.png'; ?>', 
                                                '<?php echo htmlspecialchars($student['id_number']); ?>',
                                                '<?php echo $student['id']; ?>',
                                                '<?php echo htmlspecialchars($student['section']); ?>'
                                            )">
                                                <?php echo htmlspecialchars($student['name']); ?>
                                            </a>
                                        </td>
                                        <td><?php echo htmlspecialchars($student['id_number']); ?></td>
                                        <td><span class="badge-role"><?php echo htmlspecialchars($student['role']); ?></span></td>
                                        <td><?php echo htmlspecialchars($student['section']); ?></td>
                                        <td><?php echo htmlspecialchars($student['email']); ?></td>
                                        <td style="text-align: center;">
                                            <a href="view_class.php?section_id=<?php echo $section_id; ?>&delete_student=<?php echo $student['id']; ?>" class="btn-delete" onclick="return confirm('Are you sure you want to permanently delete this student record from the database?');" title="Delete Student Record">
                                                <i class="fa-solid fa-trash-can"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" style="text-align: center; color: #666; padding: 40px; font-style: italic; white-space: normal;">
                                        No students are currently registered under this section.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <!-- Transfer Student Modal -->
    <div id="transferModal" class="modal-overlay">
        <div class="transfer-modal-box">
            <button type="button" class="modal-close" onclick="closeTransferModal()"><i class="fa-solid fa-xmark"></i></button>
            <h3 style="margin-top: 0; color: #112918; font-size: 20px; font-weight: 800;">Transfer Student</h3>
            <p id="transferStudentSubtitle" style="font-size: 13px; color: #64748b; margin-bottom: 20px;"></p>
            
            <form action="view_class.php?section_id=<?php echo $section_id; ?>" method="POST">
                <input type="hidden" name="transfer_student_id" id="transferStudentIdInput">
                
                <div class="form-group">
                    <label>Select Program</label>
                    <select id="programSelect" onchange="updateSectionsDropdown()" required>
                        <option value="">-- Select Program --</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Select Section</label>
                    <select name="new_section_name" id="sectionSelect" required>
                        <option value="">-- Select Section --</option>
                    </select>
                </div>

                <button type="submit" class="btn-submit-transfer">Confirm Transfer</button>
            </form>
        </div>
    </div>

    <!-- Student Details Modal (Popup) -->
    <div id="studentModal" class="modal-overlay">
        <div class="modal-content">
            <button type="button" class="modal-close" onclick="closeStudentModal()"><i class="fa-solid fa-xmark"></i></button>
            
            <div class="modal-profile-header">
                <img id="modalStudentAvatar" alt="Student Profile" class="modal-avatar">
                <div class="modal-student-info">
                    <h4 id="modalStudentName"></h4>
                    <p>Student Number: <span id="modalStudentId" style="color: #108a00; font-weight: 700;"></span></p>
                </div>
            </div>

            <div class="modal-table-title">
                <span id="modalTableTitleText">Enrolled Courses & Real-time Attendance Summary</span>
                <button type="button" id="backToSummaryBtn" class="back-to-summary-btn" style="display: none;" onclick="loadMainSummary()">
                    <i class="fa-solid fa-arrow-left"></i> Back to Summary
                </button>
            </div>
            
            <div style="overflow-x: auto;">
                <table class="modal-table" id="coursesSummaryTable">
                    <thead>
                        <tr>
                            <th>Courses Enrolled</th>
                            <th>Course Instructor</th>
                            <th style="text-align: center;">Total Number of Days of Classes</th>
                            <th style="text-align: center;">Total Number of Days Present</th>
                            <th style="text-align: center;">Total Number of Days Late</th>
                            <th style="text-align: center;">Total Number of Days Absent</th>
                        </tr>
                    </thead>
                    <tbody id="modalAttendanceBody">
                        <tr>
                            <td colspan="6" style="text-align: center; color: #666; font-style: italic;">Loading real-time records...</td>
                        </tr>
                    </tbody>
                </table>

                <div id="classRecordSheetContainer" style="display: none;">
                    <div class="class-record-grid-wrapper">
                        <table class="class-record-sheet" id="classRecordSheetTable">
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const programsData = <?php echo $programs_json; ?>;

        let currentFontSize = 13;
        let currentStudentDbId = null;
        let currentStudentFullName = '';
        let currentSectionName = '';
        let activeContextMenu = null;

        function showContextMenu(event, imgElement, studentId, studentName) {
            event.preventDefault(); // Prevent default browser context menu

            hideActiveContextMenu();

            let wrapper = imgElement.closest('.profile-cell-wrapper');
            
            let menu = document.createElement('div');
            menu.id = 'customContextMenu';
            menu.innerHTML = `
                <button type="button" class="context-menu-item" id="contextMenuTransferBtn">
                    <i class="fa-solid fa-right-left"></i> Transfer Program & Section
                </button>
            `;

            wrapper.appendChild(menu);
            menu.style.display = 'block';
            activeContextMenu = menu;

            let transferBtn = menu.querySelector('#contextMenuTransferBtn');
            transferBtn.onclick = function() {
                hideActiveContextMenu();
                triggerTransferStudent(studentId, studentName);
            };
        }

        function hideActiveContextMenu() {
            if (activeContextMenu) {
                activeContextMenu.remove();
                activeContextMenu = null;
            }
        }

        window.addEventListener('click', function(event) {
            if (activeContextMenu && !activeContextMenu.contains(event.target) && !event.target.classList.contains('student-avatar')) {
                hideActiveContextMenu();
            }
        });

        function triggerTransferStudent(studentId, studentName) {
            document.getElementById('transferStudentIdInput').value = studentId;
            document.getElementById('transferStudentSubtitle').innerText = `Transferring student: ${studentName}`;
            
            // Populate Programs dropdown
            let progSelect = document.getElementById('programSelect');
            progSelect.innerHTML = '<option value="">-- Select Program --</option>';
            programsData.forEach(prog => {
                let opt = document.createElement('option');
                opt.value = prog.id;
                opt.textContent = prog.program_name;
                progSelect.appendChild(opt);
            });

            document.getElementById('sectionSelect').innerHTML = '<option value="">-- Select Section --</option>';
            document.getElementById('transferModal').style.display = 'flex';
        }

        function updateSectionsDropdown() {
            let progId = document.getElementById('programSelect').value;
            let secSelect = document.getElementById('sectionSelect');
            secSelect.innerHTML = '<option value="">-- Select Section --</option>';

            if (!progId) return;

            let selectedProg = programsData.find(p => p.id == progId);
            if (selectedProg && selectedProg.sections) {
                selectedProg.sections.forEach(sec => {
                    let opt = document.createElement('option');
                    opt.value = sec.section_name;
                    opt.textContent = sec.section_name;
                    secSelect.appendChild(opt);
                });
            }
        }

        function closeTransferModal() {
            document.getElementById('transferModal').style.display = 'none';
        }

        function adjustZoom(direction) {
            currentFontSize += direction * 1;
            if (currentFontSize < 9) currentFontSize = 9;
            if (currentFontSize > 20) currentFontSize = 20;
            document.getElementById('zoomableTable').style.fontSize = currentFontSize + 'px';
        }

        function resetZoom() {
            currentFontSize = 13;
            document.getElementById('zoomableTable').style.fontSize = currentFontSize + 'px';
        }

        function openStudentModal(name, pic, idNumber, studentDbId, sectionName) {
            currentStudentDbId = studentDbId;
            currentStudentFullName = name;
            currentSectionName = sectionName;

            document.getElementById('modalStudentName').innerText = name;
            document.getElementById('modalStudentAvatar').src = pic;
            document.getElementById('modalStudentId').innerText = idNumber;
            
            document.getElementById('studentModal').style.display = 'flex';
            loadMainSummary();
        }

        function loadMainSummary() {
            document.getElementById('modalTableTitleText').innerText = "Enrolled Courses & Real-time Attendance Summary";
            document.getElementById('backToSummaryBtn').style.display = 'none';
            document.getElementById('classRecordSheetContainer').style.display = 'none';
            document.getElementById('coursesSummaryTable').style.display = 'table';

            let tbody = document.getElementById('modalAttendanceBody');
            tbody.innerHTML = `<tr><td colspan="6" style="text-align: center; color: #666; font-style: italic;">Fetching real-time data...</td></tr>`;

            fetch('get_student_attendance.php?student_id=' + currentStudentDbId + '&section=' + encodeURIComponent(currentSectionName))
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success' && data.courses && data.courses.length > 0) {
                        let rows = '';
                        data.courses.forEach(course => {
                            let subjId = course.course_id || course.subject_id || 1;
                            let teacherName = course.teacher_name || 'N/A';
                            rows += `
                                <tr>
                                    <td style="font-weight: 700; color: #112918;">${course.course_name}</td>
                                    <td><a class="teacher-name-link" onclick="openTeacherClassRecord('${subjId}', '${escapeHtml(course.course_name)}', '${escapeHtml(teacherName)}', ${course.total_classes}, ${course.total_present}, ${course.total_late}, ${course.total_absent})">${teacherName}</a></td>
                                    <td style="text-align: center;">${course.total_classes}</td>
                                    <td style="text-align: center; color: #108a00; font-weight: 700;">${course.total_present}</td>
                                    <td style="text-align: center; color: #d97706; font-weight: 700;">${course.total_late}</td>
                                    <td style="text-align: center; color: #ef4444; font-weight: 700;">${course.total_absent}</td>
                                </tr>
                            `;
                        });
                        tbody.innerHTML = rows;
                    } else {
                        tbody.innerHTML = `<tr><td colspan="6" style="text-align: center; color: #666; font-style: italic;">No enrolled courses found for this student.</td></tr>`;
                    }
                })
                .catch(error => {
                    tbody.innerHTML = `<tr><td colspan="6" style="text-align: center; color: #ef4444; font-style: italic;">Error loading attendance records.</td></tr>`;
                });
        }

        function openTeacherClassRecord(subjectId, courseName, teacherName, sumClasses, sumPresent, sumLate, sumAbsent) {
            document.getElementById('modalTableTitleText').innerText = `${courseName} - Class Record (${teacherName})`;
            document.getElementById('backToSummaryBtn').style.display = 'inline-flex';
            document.getElementById('coursesSummaryTable').style.display = 'none';
            document.getElementById('classRecordSheetContainer').style.display = 'block';

            let sheetTable = document.getElementById('classRecordSheetTable');
            sheetTable.innerHTML = `<tr><td colspan="10" style="text-align: center; padding: 30px; color: #666; font-style: italic;">Loading class record sheet...</td></tr>`;

            fetch(`get_student_attendance_dates.php?student_id=${currentStudentDbId}&subject_id=${subjectId}&t=` + new Date().getTime())
                .then(response => response.json())
                .then(data => {
                    let dates = [];
                    let rowNum = 1; 
                    
                    if (data.status === 'success') {
                        if (data.row_number !== undefined) {
                            rowNum = data.row_number;
                        }
                        if (data.dates && Array.isArray(data.dates)) {
                            dates = data.dates;
                        } else if (data.classes && Array.isArray(data.classes)) {
                            dates = data.classes;
                        }
                    }

                    let totalClasses = (sumClasses !== undefined) ? sumClasses : dates.length;
                    let totalPresent = (sumPresent !== undefined) ? sumPresent : dates.filter(d => (d.status || '').toLowerCase() === 'present').length;
                    let totalLate = (sumLate !== undefined) ? sumLate : dates.filter(d => (d.status || '').toLowerCase() === 'late').length;
                    let totalAbsent = (sumAbsent !== undefined) ? sumAbsent : dates.filter(d => (d.status || '').toLowerCase() === 'absent').length;

                    let headerHtml = `<thead><tr>`;
                    headerHtml += `<th style="width: 40px;">#</th>`;
                    headerHtml += `<th class="col-student-name">STUDENT NAME</th>`;

                    dates.forEach(d => {
                        let dateLabel = d.date || d.class_date || 'Date';
                        headerHtml += `<th><div class="date-col-header">${dateLabel}</div></th>`;
                    });

                    headerHtml += `
                        <th style="font-size: 11px; padding: 6px;">TOTAL NUMBER OF CLASS DAYS</th>
                        <th style="font-size: 11px; padding: 6px;">TOTAL NUMBER OF DAYS PRESENT</th>
                        <th style="font-size: 11px; padding: 6px;">TOTAL NUMBER OF DAYS LATE</th>
                        <th style="font-size: 11px; padding: 6px;">TOTAL NUMBER OF DAYS ABSENT</th>
                    `;
                    headerHtml += `</tr></thead>`;

                    let bodyHtml = `<tbody><tr>`;
                    bodyHtml += `<td>${rowNum}</td>`;
                    bodyHtml += `<td class="col-student-name">${currentStudentFullName}</td>`;

                    dates.forEach(d => {
                        let st = (d.status || '').toLowerCase();
                        let mark = '';
                        let color = '#334155';
                        if (st === 'present') {
                            mark = 'P';
                            color = '#108a00';
                        } else if (st === 'late') {
                            mark = 'L';
                            color = '#d97706';
                        } else if (st === 'absent') {
                            mark = 'A';
                            color = '#ef4444';
                        }
                        bodyHtml += `<td style="font-weight: 700; color: ${color};">${mark}</td>`;
                    });

                    bodyHtml += `
                        <td style="font-weight: 700; text-align: center;">${totalClasses}</td>
                        <td style="font-weight: 700; color: #108a00; text-align: center;">${totalPresent}</td>
                        <td style="font-weight: 700; color: #d97706; text-align: center;">${totalLate}</td>
                        <td style="font-weight: 700; color: #ef4444; text-align: center;">${totalAbsent}</td>
                    `;
                    bodyHtml += `</tr></tbody>`;

                    sheetTable.innerHTML = headerHtml + bodyHtml;
                })
                .catch(error => {
                    sheetTable.innerHTML = `<tr><td colspan="10" style="text-align: center; color: #ef4444; padding: 20px;">Failed to load class record sheet.</td></tr>`;
                });
        }

        function escapeHtml(str) {
            return str.replace(/'/g, "\\'").replace(/"/g, '&quot;');
        }

        function closeStudentModal() {
            document.getElementById('studentModal').style.display = 'none';
        }

        window.onclick = function(event) {
            let modal = document.getElementById('studentModal');
            if (event.target === modal) {
                modal.style.display = 'none';
            }
            let transferModal = document.getElementById('transferModal');
            if (event.target === transferModal) {
                transferModal.style.display = 'none';
            }
        }
    </script>
</body>
</html>