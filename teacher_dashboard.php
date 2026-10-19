<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'faculty') {
    header("Location: login.php");
    exit();
}

$teacher_id = $_SESSION['user_id'];
$message = "";

function generateUniqueClassCode($conn) {
    do {
        $code = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 6));
        $stmt = $conn->prepare("SELECT id FROM teacher_subjects WHERE class_code = ? AND COALESCE(is_deleted,0)=0");
        $stmt->bind_param("s", $code);
        $stmt->execute();
        $result = $stmt->get_result();
        $exists = $result->num_rows > 0;
        $stmt->close();
    } while ($exists);
    return $code;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_assignment'])) {
        $subject_name = trim($_POST['subject_name']);
        $sections_input = trim($_POST['sections']);

        if (!empty($subject_name) && !empty($sections_input)) {
            $sections_array = array_map('trim', explode(',', $sections_input));

            foreach ($sections_array as $section_name) {
                if (!empty($section_name)) {
                    $stmt_check = $conn->prepare("SELECT id FROM teacher_subjects WHERE teacher_id = ? AND subject_name = ? AND section_name = ? AND COALESCE(is_deleted,0)=0");
                    $stmt_check->bind_param("iss", $teacher_id, $subject_name, $section_name);
                    $stmt_check->execute();
                    $result_check = $stmt_check->get_result();

                    if ($result_check->num_rows === 0) {
                        $class_code = generateUniqueClassCode($conn);

                        $stmt_insert = $conn->prepare("INSERT INTO teacher_subjects (teacher_id, subject_name, section_name, class_code) VALUES (?, ?, ?, ?)");
                        $stmt_insert->bind_param("isss", $teacher_id, $subject_name, $section_name, $class_code);
                        $stmt_insert->execute();
                        $stmt_insert->close();
                    }
                    $stmt_check->close();

                    // I-sync o siguraduhing nailalagay din sa teacher_loads para makita agad ng admin
                    $stmt_load_check = $conn->prepare("SELECT id FROM teacher_loads WHERE teacher_id = ? AND subject_name = ? AND section_name = ?");
                    $stmt_load_check->bind_param("iss", $teacher_id, $subject_name, $section_name);
                    $stmt_load_check->execute();
                    if ($stmt_load_check->get_result()->num_rows === 0) {
                        $stmt_load = $conn->prepare("INSERT INTO teacher_loads (teacher_id, subject_name, section_name) VALUES (?, ?, ?)");
                        $stmt_load->bind_param("iss", $teacher_id, $subject_name, $section_name);
                        $stmt_load->execute();
                        $stmt_load->close();
                    }
                    $stmt_load_check->close();
                }
            }

            header("Location: teacher_dashboard.php");
            exit();
        } else {
            $message = "Please complete all fields.";
        }
    } elseif (isset($_POST['update_profile'])) {
        // Teachers may only change their profile picture and password. Other details require admin approval.
        $fields = []; $params = []; $types = '';
        $password = $_POST['password'] ?? '';
        if ($password !== '') {
            if (strlen($password) < 8 || strlen($password) > 16 || !preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/[0-9]/', $password) || !preg_match('/[^A-Za-z0-9]/', $password)) {
                $message = 'Password must be 8–16 characters and include uppercase, lowercase, a number, and a special character.';
            } else { $fields[] = 'password = ?'; $params[] = password_hash($password, PASSWORD_DEFAULT); $types .= 's'; }
        }
        if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
            $tmp = $_FILES['profile_pic']['tmp_name']; $mime = mime_content_type($tmp);
            $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
            if (isset($allowed[$mime]) && getimagesize($tmp)) {
                if (!is_dir('uploads/')) mkdir('uploads/', 0755, true);
                $path = 'uploads/' . bin2hex(random_bytes(12)) . '.' . $allowed[$mime];
                if (move_uploaded_file($tmp, $path)) { $fields[] = 'profile_pic = ?'; $params[] = $path; $types .= 's'; }
            }
        }
        if ($message === '' && $fields) {
            $params[] = $teacher_id; $types .= 'i';
            $stmt_up = $conn->prepare("UPDATE users SET " . implode(', ', $fields) . " WHERE id = ? AND role = 'faculty'");
            $stmt_up->bind_param($types, ...$params); $stmt_up->execute(); $stmt_up->close();
            header('Location: teacher_dashboard.php'); exit();
        }
    } elseif (isset($_POST['delete_account'])) {
        $stmt_del_subjects = $conn->prepare("UPDATE teacher_subjects SET is_deleted=1, deleted_at=NOW() WHERE teacher_id=? AND COALESCE(is_deleted,0)=0");
        if ($stmt_del_subjects) { $stmt_del_subjects->bind_param("i", $teacher_id); $stmt_del_subjects->execute(); $stmt_del_subjects->close(); }
        $stmt_del_loads = $conn->prepare("UPDATE teacher_loads SET is_deleted=1, deleted_at=NOW() WHERE teacher_id=? AND COALESCE(is_deleted,0)=0");
        if ($stmt_del_loads) { $stmt_del_loads->bind_param("i", $teacher_id); $stmt_del_loads->execute(); $stmt_del_loads->close(); }
        $stmt_del_user = $conn->prepare("UPDATE users SET is_deleted = 1, deleted_at = NOW() WHERE id = ?");
        $stmt_del_user->bind_param("i", $teacher_id);
        $stmt_del_user->execute();
        $stmt_del_user->close();

        session_destroy();
        header("Location: login.php");
        exit();
    }
}

if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    
    $stmt_get_sec = $conn->prepare("SELECT subject_name, section_name FROM teacher_subjects WHERE id = ? AND teacher_id = ?");
    $stmt_get_sec->bind_param("ii", $delete_id, $teacher_id);
    $stmt_get_sec->execute();
    $res_sec = $stmt_get_sec->get_result();
    if ($row_sec = $res_sec->fetch_assoc()) {
        $sub_to_del = $row_sec['subject_name'];
        $sec_to_del = $row_sec['section_name'];

        $stmt_del_load = $conn->prepare("UPDATE teacher_loads SET is_deleted = 1, deleted_at = NOW() WHERE teacher_id = ? AND subject_name = ? AND section_name = ?");
        $stmt_del_load->bind_param("iss", $teacher_id, $sub_to_del, $sec_to_del);
        $stmt_del_load->execute();
        $stmt_del_load->close();
    }
    $stmt_get_sec->close();

    $stmt_delete = $conn->prepare("UPDATE teacher_subjects SET is_deleted = 1, deleted_at = NOW() WHERE id = ? AND teacher_id = ?");
    $stmt_delete->bind_param("ii", $delete_id, $teacher_id);
    $stmt_delete->execute();
    $stmt_delete->close();
    header("Location: teacher_dashboard.php");
    exit();
}

$stmt = $conn->prepare("SELECT first_name, middle_name, last_name, role, id_number, email, institute, position, profile_pic FROM users WHERE id = ?");
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

$first_name = $user['first_name'] ?? '';
$middle_name = $user['middle_name'] ?? '';
$last_name = $user['last_name'] ?? '';

$stmt_sub = $conn->prepare("SELECT DISTINCT subject_name FROM teacher_subjects WHERE teacher_id = ? AND COALESCE(is_deleted,0)=0");
$stmt_sub->bind_param("i", $teacher_id);
$stmt_sub->execute();
$subjects_result = $stmt_sub->get_result();
$teacher_subject_count_result = $conn->query('SELECT COUNT(DISTINCT subject_name) AS total FROM teacher_subjects WHERE teacher_id = ' . (int)$teacher_id . ' AND COALESCE(is_deleted,0)=0');
$teacher_subject_count = $teacher_subject_count_result ? (int)$teacher_subject_count_result->fetch_assoc()['total'] : 0;
$teacher_section_count_result = $conn->query('SELECT COUNT(*) AS total FROM teacher_subjects WHERE teacher_id = ' . (int)$teacher_id . ' AND COALESCE(is_deleted,0)=0');
$teacher_section_count = $teacher_section_count_result ? (int)$teacher_section_count_result->fetch_assoc()['total'] : 0;
$available_sections = [];
$section_query = $conn->query('SELECT section_name FROM sections ORDER BY section_name ASC');
if ($section_query) { while ($section_row = $section_query->fetch_assoc()) $available_sections[] = $section_row['section_name']; }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard - KLD Attendance</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
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
            padding: 15px 30px 20px 30px;
            display: flex;
            flex-direction: column;
            gap: 15px;
            width: 100%;
            box-sizing: border-box;
        }

        .styled-card {
            background: #ffffff;
            border-radius: 20px;
            padding: 25px 30px;
            box-shadow: 0 10px 30px rgba(0, 128, 0, 0.05);
            border: 1px solid rgba(16, 138, 0, 0.08);
            width: 100%;
            box-sizing: border-box;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .styled-card:first-child {
            margin-top: 0;
        }

        .profile-card-layout {
            display: flex;
            align-items: center;
            gap: 25px;
            background: linear-gradient(135deg, #ffffff 0%, #f4fbf7 100%);
            border-left: 6px solid #108a00;
            position: relative;
        }

        .profile-header-right {
            position: absolute;
            top: 15px;
            right: 25px;
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 8px;
        }

        .signed-in-badge {
            font-size: 12px;
            font-weight: 700;
            color: #108a00;
            background: #e8f5e9;
            padding: 3px 10px;
            border-radius: 20px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .btn-edit-profile {
            background: none;
            border: 1px solid #108a00;
            color: #108a00;
            padding: 4px 12px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            text-decoration: none;
            transition: all 0.2s;
        }

        .btn-edit-profile:hover {
            background: #108a00;
            color: #fff;
        }

        .profile-icon-btn {
            background: #108a00;
            color: #fff;
            border: none;
            width: 34px;
            height: 34px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            transition: background 0.2s;
            font-size: 14px;
        }

        .profile-icon-btn:hover {
            background: #0d6d00;
            color: #fff;
        }

        .profile-content {
            display: flex;
            align-items: center;
            gap: 25px;
            width: 100%;
            margin-top: 5px;
        }

        .user-profile-avatar {
            width: 85px;
            height: 85px;
            min-width: 85px;
            border-radius: 50%;
            background: #e8f5e9;
            color: #108a00;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 38px;
            border: 3px solid #108a00;
            overflow: hidden;
            box-shadow: 0 4px 10px rgba(0,0,0,0.08);
            box-sizing: border-box;
        }

        .user-profile-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
            display: block;
        }

        .user-details h2 {
            margin: 0 0 6px 0;
            color: #1a331e;
            font-size: 24px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .user-details-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(220px, max-content));
            gap: 4px 30px;
        }

        .user-details p {
            margin: 4px 0;
            color: #4f5d52;
            font-size: 13.5px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .user-details p i {
            color: #108a00;
            font-size: 14px;
            width: 16px;
            text-align: center;
        }

        .user-details p span {
            color: #2c3e50;
            font-weight: 600;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0;
        }

        .section-header h3 {
            font-size: 18px;
            font-weight: 800;
            color: #1a331e;
            margin: 0;
        }

        .btn-add {
            background-color: #108a00;
            color: #ffffff;
            border: none;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
            text-decoration: none;
            transition: background-color 0.2s ease;
        }

        .btn-add:hover {
            background-color: #0d6d00;
        }

        .add-form-container {
            display: none;
            margin-top: 20px;
            padding: 20px;
            background: #f8faf9;
            border-radius: 12px;
            border: 1px solid #eef2f0;
        }

        .add-form-container.active {
            display: block;
        }

        .form-row {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .form-input {
            flex: 1;
            padding: 10px 14px;
            border: 1px solid #ccc;
            border-radius: 8px;
            font-size: 14px;
            outline: none;
            transition: border-color 0.2s;
        }

        .form-input:focus {
            border-color: #108a00;
        }

        .btn-enter {
            background-color: #108a00;
            color: #ffffff;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.2s ease;
        }

        .btn-enter:hover {
            background-color: #0d6d00;
        }

        .subject-cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .subject-card {
            background: #ffffff;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.06);
            border: 1px solid #eef2f0;
            display: flex;
            flex-direction: column;
            position: relative;
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .subject-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 25px rgba(0, 128, 0, 0.1);
        }

        .subject-card-banner {
            background: linear-gradient(135deg, #108a00 0%, #0d6d00 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            min-height: 110px;
        }

        .card-logo {
            width: 60px;
            height: 60px;
            object-fit: contain;
        }

        .subject-card-body {
            padding: 20px;
            display: flex;
            flex-direction: column;
            gap: 15px;
            flex: 1;
            justify-content: space-between;
        }

        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(4px);
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }

        .modal-content {
            background: #ffffff;
            border-radius: 20px;
            padding: 25px 30px;
            width: 90%;
            max-width: 450px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.2);
            position: relative;
            animation: modalFadeIn 0.2s ease-out;
        }

        @keyframes modalFadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            border-bottom: 1px solid #eee;
            padding-bottom: 12px;
        }

        .modal-header h4 {
            margin: 0;
            color: #1a331e;
            font-size: 18px;
            font-weight: 800;
        }

        .close-modal {
            font-size: 24px;
            font-weight: bold;
            color: #aaa;
            cursor: pointer;
            transition: color 0.2s;
            line-height: 1;
        }

        .close-modal:hover {
            color: #333;
        }

        .sections-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
            max-height: 300px;
            overflow-y: auto;
        }

        .section-item-card {
            background: #f4fbf7;
            border: 1px solid #108a00;
            padding: 12px 16px;
            border-radius: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
        }

        .section-info h5 {
            margin: 0 0 4px 0;
            color: #1a331e;
            font-size: 15px;
            font-weight: 700;
        }

        .section-info span {
            font-size: 12px;
            color: #555;
            background: #e8f5e9;
            padding: 2px 8px;
            border-radius: 6px;
            font-weight: 600;
        }

        .section-actions {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .btn-goto-record {
            background: #108a00;
            color: #fff;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .btn-goto-record:hover {
            background: #0d6d00;
        }

        .btn-section-delete {
            background: #dc3545;
            color: #fff;
            padding: 6px 10px;
            border-radius: 6px;
            font-size: 12px;
            text-decoration: none;
        }

        .btn-section-delete:hover {
            background: #b02a37;
        }

        .edit-profile-modal-content {
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
        }

        .edit-form-group {
            margin-bottom: 15px;
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .edit-form-group label {
            font-size: 12.5px;
            font-weight: 700;
            color: #1a331e;
        }

        .edit-form-group input, .edit-form-group textarea {
            padding: 8px 12px;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-size: 13.5px;
        }

        .edit-modal-footer-buttons {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-top: 20px;
            border-top: 1px solid #eee;
            padding-top: 15px;
        }

        .btn-submit-changes {
            background-color: #108a00;
            color: #fff;
            border: none;
            padding: 10px;
            border-radius: 6px;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.2s;
        }
        .btn-submit-changes:hover {
            background-color: #0d6d00;
        }

        .btn-delete-acc {
            background-color: #dc3545;
            color: #fff;
            border: none;
            padding: 10px;
            border-radius: 6px;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.2s;
        }
        .btn-delete-acc:hover {
            background-color: #b02a37;
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
            <a href="teacher_dashboard.php"><i class="fa-solid fa-house"></i> Home</a>
            <a href="about.php"><i class="fa-solid fa-circle-info"></i> About us</a>
            <a href="logout.php"><i class="fa-solid fa-right-from-bracket"></i> Log-out</a>
            <a href="teacher_dashboard.php" class="active"><i class="fa-solid fa-user"></i> My Account</a>
        </div>
    </nav>

    <main class="main-wrapper">
        <div class="styled-card profile-card-layout">
            <div class="profile-header-right">
                <span class="signed-in-badge">Signed in as Teacher</span>
                <button type="button" class="btn-edit-profile" onclick="openEditProfileModal()">
                    <i class="fa-solid fa-pen-to-square"></i> Edit Profile
                </button>
                <div style="display: flex; gap: 8px; margin-top: 5px;">
                    <a href="notifications.php" class="profile-icon-btn" title="Notifications">
                        <i class="fa-solid fa-bell"></i>
                    </a>
                    <a href="chat.php" class="profile-icon-btn" title="Chat">
                        <i class="fa-solid fa-message"></i>
                    </a>
                </div>
            </div>
            <div class="profile-content">
                <div class="user-profile-avatar">
                    <?php if (!empty($user['profile_pic']) && file_exists($user['profile_pic'])): ?>
                        <img src="<?php echo htmlspecialchars($user['profile_pic']); ?>" alt="Profile Picture">
                    <?php else: ?>
                        <i class="fa-solid fa-user"></i>
                    <?php endif; ?>
                </div>
                <div class="user-details">
                    <h2><?php echo strtoupper(htmlspecialchars(trim("$first_name $middle_name $last_name") ?: 'Teacher')); ?></h2>
                    <div class="user-details-grid">
                        <p><i class="fa-solid fa-id-card"></i> ID Number: <span><?php echo htmlspecialchars($user['id_number'] ?? 'N/A'); ?></span></p>
                        <p><i class="fa-solid fa-building-columns"></i> Institute: <span><?php echo htmlspecialchars($user['institute'] ?? 'N/A'); ?></span></p>
                        <p><i class="fa-solid fa-envelope"></i> Email: <span><?php echo htmlspecialchars($user['email'] ?? 'N/A'); ?></span></p>
                        <p><i class="fa-solid fa-briefcase"></i> Position: <span><?php echo htmlspecialchars($user['position'] ?? 'N/A'); ?></span></p>
                    </div>
                </div>
            </div>
        </div>

        <div class="styled-card">
            <div class="section-header">
                <h3>Handled Subjects Overview</h3>
                <button type="button" class="btn-add" id="toggleAddBtn" onclick="toggleAddForm()">
                    <i class="fa-solid fa-plus"></i> Add
                </button>
            </div>

            <div class="add-form-container" id="addFormContainer">
                <?php if (!empty($message)): ?>
                    <p style="color: red; font-size: 13px; margin-bottom: 10px;"><?php echo $message; ?></p>
                <?php endif; ?>
                <form action="teacher_dashboard.php" method="POST">
                    <div class="form-row">
                        <input type="text" name="subject_name" class="form-input" placeholder="Enter Subject Name" required>
                        <select name="sections" class="form-input" required><option value="">Select Section</option><?php foreach ($available_sections as $available_section): ?><option value="<?php echo htmlspecialchars($available_section, ENT_QUOTES); ?>"><?php echo htmlspecialchars($available_section); ?></option><?php endforeach; ?></select>
                        <button type="submit" name="add_assignment" class="btn-enter">Enter</button>
                    </div>
                </form>
            </div>

            <div class="subject-cards-grid">
                <?php 
                $stmt_sub->execute();
                $cards_result = $stmt_sub->get_result();
                if ($cards_result && $cards_result->num_rows > 0): 
                    while ($card = $cards_result->fetch_assoc()): 
                        $current_subject = $card['subject_name'];
                        
                        $sec_stmt = $conn->prepare("SELECT id, section_name, class_code FROM teacher_subjects WHERE teacher_id = ? AND subject_name = ? AND COALESCE(is_deleted,0)=0");
                        $sec_stmt->bind_param("is", $teacher_id, $current_subject);
                        $sec_stmt->execute();
                        $sec_res = $sec_stmt->get_result();
                        $sections_data = [];
                        while ($s_row = $sec_res->fetch_assoc()) {
                            $sections_data[] = $s_row;
                        }
                        $sec_stmt->close();
                ?>
                    <div class="subject-card" onclick='openSectionsModal(<?php echo json_encode($current_subject, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>, <?php echo json_encode($sections_data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>)'>
                        <div class="subject-card-banner">
                            <img src="kld-logo.png" alt="KLD Logo" class="card-logo">
                        </div>
                        <div class="subject-card-body">
                            <div>
                                <h4 style="margin: 0 0 5px 0; font-size: 18px; color: #1a331e; font-weight: 800;"><?php echo htmlspecialchars($current_subject); ?></h4>
                                <h5 style="margin: 0; font-size: 14px; color: #108a00; font-weight: 700;">Sections: <?php echo count($sections_data); ?> assigned</h5>
                            </div>
                            <span style="font-size: 12px; color: #666;"><i class="fa-solid fa-circle-info"></i> Click to view sections & codes</span>
                        </div>
                    </div>
                <?php 
                    endwhile; 
                else: 
                ?>
                    <p style="color: #888; font-size: 14px; grid-column: 1 / -1; text-align: center; padding: 25px;">No subjects or sections have been assigned to your account yet.</p>
                <?php endif; ?>
            </div>
        </div>
        <div class="styled-card" style="margin-top:18px;padding:20px"><h3>Teaching Overview</h3><div style="max-width:520px;height:250px"><canvas id="teacherOverviewChart" aria-label="Subjects and assigned sections"></canvas></div></div>
    </main>

    <div id="sectionsModal" class="modal-overlay" onclick="closeSectionsModal(event)">
        <div class="modal-content" onclick="event.stopPropagation()">
            <div class="modal-header">
                <h4 id="modalSubjectTitle">Select Section</h4>
                <span class="close-modal" onclick="closeSectionsModal()">&times;</span>
            </div>
            <div class="sections-list" id="modalSectionsList">
            </div>
        </div>
    </div>

    <!-- Edit Profile Modal -->
    <div id="editProfileModal" class="modal-overlay" onclick="closeEditProfileModal(event)">
        <div class="modal-content edit-profile-modal-content" onclick="event.stopPropagation()">
            <div class="modal-header">
                <h4>Edit Profile Details</h4>
                <span class="close-modal" onclick="closeEditProfileModal()">&times;</span>
            </div>
            <p style="margin:0 0 12px"><a href="request_profile_change.php" onclick="window.open(this.href, 'profileChangeRequest', 'width=760,height=700,resizable=yes,scrollbars=yes'); return false;">Request Admin Approval to Change Identity Details</a></p>
            <form action="teacher_dashboard.php" method="POST" enctype="multipart/form-data" id="editProfileForm">
                <div class="edit-form-group">
                    <label>Profile Picture</label>
                    <input type="file" name="profile_pic" id="profilePicInput" accept="image/*" onchange="checkForEdits()">
                </div>
                <div class="edit-form-group">
                    <label>New Password (Leave blank if unchanged)</label>
                    <div style="position: relative; display: flex; align-items: center;">
                        <input type="password" name="password" id="passwordInput" placeholder="Enter new password" oninput="checkForEdits()" style="width: 100%; padding-right: 40px; box-sizing: border-box;">
                        <i class="fa-solid fa-eye" id="togglePasswordIcon" onclick="togglePasswordVisibility()" style="position: absolute; right: 12px; cursor: pointer; color: #666;"></i>
                    </div>
                </div>
                <div class="edit-form-group">
                    <label>Last Name</label>
                    <input type="text" name="last_name" value="<?php echo htmlspecialchars($last_name); ?>" readonly required>
                </div>
                <div class="edit-form-group">
                    <label>First Name</label>
                    <input type="text" name="first_name" value="<?php echo htmlspecialchars($first_name); ?>" readonly required>
                </div>
                <div class="edit-form-group">
                    <label>Middle Name</label>
                    <input type="text" name="middle_name" value="<?php echo htmlspecialchars($middle_name); ?>" readonly>
                </div>
                <div class="edit-form-group">
                    <label>Role</label>
                    <input type="text" name="role" value="<?php echo htmlspecialchars($user['role'] ?? ''); ?>" readonly required>
                </div>
                <div class="edit-form-group">
                    <label>Employee Number</label>
                    <input type="text" name="id_number" value="<?php echo htmlspecialchars($user['id_number'] ?? ''); ?>" readonly required>
                </div>
                <div class="edit-form-group">
                    <label>KLD Email</label>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" readonly required>
                </div>
                <div class="edit-form-group">
                    <label>Institute</label>
                    <input type="text" name="institute" value="<?php echo htmlspecialchars($user['institute'] ?? ''); ?>" readonly required>
                </div>
                <div class="edit-form-group">
                    <label>Position</label>
                    <input type="text" name="position" value="<?php echo htmlspecialchars($user['position'] ?? ''); ?>" readonly required>
                </div>

                <div class="edit-modal-footer-buttons">
                    <button type="submit" name="update_profile" id="submitChangesBtn" class="btn-submit-changes" disabled>Submit Changes</button>
                    <button type="submit" name="delete_account" class="btn-delete-acc" onclick="return confirm('Warning: Your account will be deactivated. Your records will be retained. Continue?');">Delete Account</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function toggleAddForm() {
            const formContainer = document.getElementById('addFormContainer');
            formContainer.classList.toggle('active');
        }

        function openSectionsModal(subjectName, sectionsArray) {
            document.getElementById('modalSubjectTitle').innerText = subjectName;
            const sectionsContainer = document.getElementById('modalSectionsList');
            sectionsContainer.innerHTML = '';

            if (sectionsArray && sectionsArray.length > 0) {
                sectionsArray.forEach(item => {
                    const cardDiv = document.createElement('div');
                    cardDiv.className = 'section-item-card';

                    cardDiv.innerHTML = `
                        <div class="section-info">
                            <h5><i class="fa-solid fa-users" style="margin-right: 6px; color: #108a00;"></i>${item.section_name}</h5>
                            <span>Code: <strong>${item.class_code}</strong></span>
                        </div>
                        <div class="section-actions">
                            <a href="class_record.php?id=${item.id}" class="btn-goto-record" title="View Class Record">
                                Record <i class="fa-solid fa-chevron-right" style="font-size: 10px;"></i>
                            </a>

                        </div>
                    `;
                    sectionsContainer.appendChild(cardDiv);
                });
            } else {
                sectionsContainer.innerHTML = '<p style="text-align: center; color: #888; margin: 10px 0;">No sections found.</p>';
            }

            document.getElementById('sectionsModal').style.display = 'flex';
        }

        function closeSectionsModal() {
            document.getElementById('sectionsModal').style.display = 'none';
        }

        function openEditProfileModal() {
            document.getElementById('editProfileModal').style.display = 'flex';
        }

        function closeEditProfileModal() {
            document.getElementById('editProfileModal').style.display = 'none';
        }

        function checkForEdits() {
            const pic = document.getElementById('profilePicInput').files.length > 0;
            const password = document.getElementById('passwordInput').value.trim() !== '';
            const button = document.getElementById('submitChangesBtn');
            button.disabled = !(pic || password);
        }

        function togglePasswordVisibility() {
            const passwordInput = document.getElementById('passwordInput');
            const toggleIcon = document.getElementById('togglePasswordIcon');
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }
    </script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script><script>document.addEventListener('DOMContentLoaded',function(){const c=document.getElementById('teacherOverviewChart');if(!c||!window.Chart)return;const chart=new Chart(c,{type:'bar',data:{labels:['Subjects','Assigned Sections'],datasets:[{label:'Total',data:[<?php echo $teacher_subject_count; ?>,<?php echo $teacher_section_count; ?>]}]},options:{responsive:true,maintainAspectRatio:false,scales:{y:{beginAtZero:true,ticks:{precision:0}}}}});async function refresh(){try{const r=await fetch('dashboard_stats.php',{cache:'no-store'});const d=await r.json();if(d.values){chart.data.labels=d.labels;chart.data.datasets[0].data=d.values;chart.update();}}catch(e){console.warn('Dashboard chart refresh failed',e);}}setInterval(refresh,15000);});</script>
</body>
</html>
