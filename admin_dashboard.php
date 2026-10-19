<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$admin_id = $_SESSION['user_id'];
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        $first_name = trim($_POST['first_name'] ?? '');
        $middle_name = trim($_POST['middle_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $id_number = trim($_POST['id_number'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        $password = $_POST['password'] ?? '';
        $profile_pic_path = null;
        if ($first_name === '' || $last_name === '' || $id_number === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match('/^[A-Z0-9._%+-]+@kld\.edu\.ph$/i', $email)) {
            $error_message = 'Enter valid name, ID number, and official KLD email details.';
        } else {
            if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
                $tmp = $_FILES['profile_pic']['tmp_name']; $mime = mime_content_type($tmp);
                $allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
                if (isset($allowed[$mime]) && getimagesize($tmp)) {
                    if (!is_dir('uploads/')) mkdir('uploads/', 0755, true);
                    $profile_pic_path = 'uploads/' . bin2hex(random_bytes(12)) . '.' . $allowed[$mime];
                    if (!move_uploaded_file($tmp, $profile_pic_path)) $profile_pic_path = null;
                }
            }
            $sets = ['first_name=?','middle_name=?','last_name=?','id_number=?','email=?'];
            $params = [$first_name,$middle_name,$last_name,$id_number,$email]; $types = 'sssss';
            if ($password !== '') {
                if (strlen($password)<8 || strlen($password)>16 || !preg_match('/[A-Z]/',$password) || !preg_match('/[a-z]/',$password) || !preg_match('/[0-9]/',$password) || !preg_match('/[^A-Za-z0-9]/',$password)) {
                    $error_message = 'Password must be 8–16 characters and include uppercase, lowercase, a number, and a special character.';
                } else { $sets[]='password=?'; $params[]=password_hash($password,PASSWORD_DEFAULT); $types.='s'; }
            }
            if ($profile_pic_path) { $sets[]='profile_pic=?'; $params[]=$profile_pic_path; $types.='s'; }
            if ($error_message === '') {
                $params[]=$admin_id; $types.='i';
                $stmt_up=$conn->prepare("UPDATE users SET " . implode(', ', $sets) . " WHERE id=? AND role='admin'");
                $stmt_up->bind_param($types,...$params);
                if (!$stmt_up->execute()) $error_message = 'Profile update failed. The email or ID number may already be in use.';
                $stmt_up->close();
            }
        }
        if ($error_message === '') { header('Location: admin_dashboard.php'); exit(); }
    } elseif (isset($_POST['send_truncate_code'])) {
        $admin_stmt = $conn->prepare("SELECT email, first_name FROM users WHERE id=? AND role='admin' AND COALESCE(is_deleted,0)=0");
        $admin_stmt->bind_param('i', $admin_id); $admin_stmt->execute(); $admin_user = $admin_stmt->get_result()->fetch_assoc(); $admin_stmt->close();
        if (!$admin_user) { $error_message = 'The administrator account could not be verified.'; }
        else {
            $code = (string)random_int(100000, 999999); $hash = password_hash($code, PASSWORD_DEFAULT); $expires = date('Y-m-d H:i:s', time()+600);
            $save = $conn->prepare("INSERT INTO admin_action_codes (admin_id, action_name, code_hash, expires_at, created_at) VALUES (?, 'truncate_records', ?, ?, NOW())");
            if (!$save) { $error_message = 'Email verification is not configured. Apply the database migration first.'; }
            else { $save->bind_param('iss', $admin_id, $hash, $expires); $saved = $save->execute(); $save->close();
                $from = getenv('WEBYTE_MAIL_FROM') ?: 'no-reply@kld.edu.ph';
                $sent = $saved && @mail($admin_user['email'], 'Webyte records reset verification code', "Hello {$admin_user['first_name']},\n\nYour verification code to reset enrollment and handled-subject records is {$code}. It expires in 10 minutes. If you did not request this, ignore this email.\n\nWebyte", "From: {$from}\r\nContent-Type: text/plain; charset=UTF-8");
                $error_message = $sent ? 'A verification code has been sent to your registered administrator email. Enter it below within 10 minutes.' : 'The verification email could not be sent. Check the server mail configuration.';
            }
        }
    } elseif (isset($_POST['truncate_records'])) {
        $verification_code = trim($_POST['admin_verification_code'] ?? '');
        $code_stmt = $conn->prepare("SELECT id, code_hash FROM admin_action_codes WHERE admin_id=? AND action_name='truncate_records' AND expires_at>=NOW() AND used_at IS NULL ORDER BY created_at DESC LIMIT 1");
        $code_row = null;
        if ($code_stmt) { $code_stmt->bind_param('i', $admin_id); $code_stmt->execute(); $code_row = $code_stmt->get_result()->fetch_assoc(); $code_stmt->close(); }
        if ($code_row && preg_match('/^[0-9]{6}$/', $verification_code) && password_verify($verification_code, $code_row['code_hash'])) {
            $use_stmt=$conn->prepare('UPDATE admin_action_codes SET used_at=NOW() WHERE id=?');$use_stmt->bind_param('i',$code_row['id']);$use_stmt->execute();$use_stmt->close();
            $backup_dir = 'archives/';
            if (!is_dir($backup_dir)) {
                mkdir($backup_dir, 0777, true);
            }
            $timestamp = date('Y-m-d_H-i-s');
            $backup_file = $backup_dir . 'truncate_backup_' . $timestamp . '.json';

            $students_data = [];
            $res_stu = $conn->query("SELECT * FROM users WHERE role = 'student' AND COALESCE(is_deleted,0)=0");
            while ($row = $res_stu->fetch_assoc()) { $students_data[] = $row; }

            $teachers_data = [];
            $res_tea = $conn->query("SELECT * FROM users WHERE role IN ('teacher', 'faculty') AND COALESCE(is_deleted,0)=0");
            while ($row = $res_tea->fetch_assoc()) { $teachers_data[] = $row; }

            $snapshot = [
                'truncated_at' => date('Y-m-d H:i:s'),
                'admin_id' => $admin_id,
                'students' => $students_data,
                'teachers' => $teachers_data
            ];
            file_put_contents($backup_file, json_encode($snapshot, JSON_PRETTY_PRINT));

            $conn->query("UPDATE users SET section = NULL WHERE role = 'student'");
            if ($conn->query("SHOW TABLES LIKE 'student_enrollments'")->num_rows > 0) {
                $conn->query("UPDATE student_enrollments SET is_deleted = 1, deleted_at = NOW() WHERE COALESCE(is_deleted,0)=0");
            }

            if ($conn->query("SHOW TABLES LIKE 'teacher_subjects'")->num_rows > 0) {
                $conn->query("UPDATE teacher_subjects SET is_deleted = 1, deleted_at = NOW() WHERE COALESCE(is_deleted,0)=0");
            }
            if ($conn->query("SHOW TABLES LIKE 'teacher_loads'")->num_rows > 0) {
                $conn->query("UPDATE teacher_loads SET is_deleted = 1, deleted_at = NOW() WHERE COALESCE(is_deleted,0)=0");
            }

            if ($conn->query("SHOW TABLES LIKE 'class_records'")->num_rows > 0) {
                $conn->query("TRUNCATE TABLE class_records");
            }

            header("Location: admin_dashboard.php?success=truncated");
            exit();
        } else {
            $error_message = 'The verification code is invalid or expired. No records were changed.';
        }
    }
}

$stmt = $conn->prepare("SELECT first_name, middle_name, last_name, role, id_number, email, profile_pic FROM users WHERE id = ?");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

$first_name = $user['first_name'] ?? '';
$middle_name = $user['middle_name'] ?? '';
$last_name = $user['last_name'] ?? '';
$full_name = trim("$first_name $middle_name $last_name");

$stmt_teachers = $conn->query("SELECT COUNT(*) as total FROM users WHERE role IN ('teacher', 'faculty') AND COALESCE(is_deleted,0)=0");
$total_teachers = $stmt_teachers ? $stmt_teachers->fetch_assoc()['total'] : 0;

$stmt_students = $conn->query("SELECT COUNT(*) as total FROM users WHERE role = 'student' AND COALESCE(is_deleted,0)=0");
$total_students = $stmt_students ? (int)$stmt_students->fetch_assoc()['total'] : 0;
$chart_student_count = $total_students;
$chart_teacher_count = $total_teachers;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - KLD Attendance</title>
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

        .profile-buttons-column {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 6px;
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
            transition: all 0.2s;
            height: 28px;
            box-sizing: border-box;
        }

        .btn-edit-profile:hover {
            background: #108a00;
            color: #fff;
        }

        .profile-action-icons-row {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .profile-icon-btn {
            background: #108a00;
            color: #fff;
            border: none;
            width: 28px;
            height: 28px;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            transition: background 0.2s;
            font-size: 12px;
            cursor: pointer;
        }

        .profile-icon-btn:hover {
            background: #0d6d00;
            color: #fff;
        }

        .btn-truncate-records {
            background: #fff5f5;
            border: 1px solid #feb2b2;
            color: #c53030;
            padding: 4px 12px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.2s;
        }

        .btn-truncate-records:hover {
            background: #c53030;
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

        .dashboard-cards-grid {
            display: flex;
            justify-content: center;
            gap: 25px;
            margin-top: 20px;
            flex-wrap: wrap;
        }

        .dash-card {
            background: #ffffff;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.06);
            border: 1px solid #eef2f0;
            display: flex;
            flex-direction: column;
            width: 300px;
            position: relative;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            text-decoration: none;
            color: inherit;
        }

        .dash-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 25px rgba(0, 128, 0, 0.15);
        }

        .dash-card-banner {
            background: linear-gradient(135deg, #108a00 0%, #0d6d00 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 25px;
            min-height: 110px;
        }

        .dash-card-icon {
            width: 65px;
            height: 65px;
            border-radius: 50%;
            background: #e8f5e9;
            color: #108a00;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            border: 3px solid #ffffff;
            box-shadow: 0 4px 10px rgba(0,0,0,0.15);
        }

        .dash-card-body {
            padding: 20px;
            display: flex;
            flex-direction: column;
            gap: 8px;
            text-align: center;
        }

        .dash-card-body h4 {
            margin: 0;
            font-size: 18px;
            color: #1a331e;
            font-weight: 800;
        }

        .dash-card-body p {
            margin: 0;
            font-size: 13.5px;
            color: #4f5d52;
            font-weight: 600;
        }

        /* Modal Overlays & Messenger-like UI Styles */
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

        .edit-profile-modal-content {
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
        }

        /* Messenger Modal Specific Styles */
        .messenger-modal-content {
            max-width: 650px;
            height: 550px;
            display: flex;
            flex-direction: column;
            padding: 0;
            overflow: hidden;
        }

        .messenger-header {
            padding: 15px 20px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #f8f9fa;
        }

        .messenger-body {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            position: relative;
        }

        .chat-search-container {
            padding: 15px;
            border-bottom: 1px solid #eee;
            background: #fff;
        }

        .chat-search-input {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 20px;
            font-size: 13.5px;
            box-sizing: border-box;
            outline: none;
            transition: border-color 0.2s;
        }

        .chat-search-input:focus {
            border-color: #108a00;
        }

        .search-results-list {
            flex: 1;
            overflow-y: auto;
            padding: 10px;
        }

        .search-user-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 15px;
            border-radius: 12px;
            cursor: pointer;
            transition: background 0.2s;
            text-decoration: none;
            color: inherit;
            position: relative;
        }

        .search-user-item:hover {
            background: #f0f7f4;
        }

        .search-user-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: #e8f5e9;
            color: #108a00;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            font-weight: bold;
            overflow: hidden;
            border: 1px solid #108a00;
            flex-shrink: 0;
        }

        .search-user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .search-user-info {
            flex: 1;
            min-width: 0;
        }

        .search-user-info h5 {
            margin: 0 0 3px 0;
            font-size: 14px;
            color: #1a331e;
            font-weight: 700;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .search-user-info p {
            margin: 0;
            font-size: 12px;
            color: #666;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* Green Dot for Unread Messages */
        .unread-dot {
            width: 10px;
            height: 10px;
            min-width: 10px;
            background-color: #108a00;
            border-radius: 50%;
            margin-left: 8px;
            box-shadow: 0 0 4px rgba(16, 138, 0, 0.6);
        }

        .recent-chats-label {
            font-size: 12px;
            font-weight: 700;
            color: #108a00;
            text-transform: uppercase;
            padding: 10px 15px 5px 15px;
            letter-spacing: 0.5px;
        }

        /* Active Chat Conversation Area */
        .chat-conversation-area {
            display: none;
            flex-direction: column;
            flex: 1;
            height: 100%;
        }

        .chat-convo-header {
            padding: 10px 15px;
            background: #f8f9fa;
            border-bottom: 1px solid #eee;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .back-to-search-btn {
            background: none;
            border: none;
            color: #108a00;
            font-weight: 700;
            cursor: pointer;
            font-size: 13px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .chat-messages-box {
            flex: 1;
            padding: 15px;
            overflow-y: auto;
            background: #fcfdfc;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .message-bubble {
            max-width: 70%;
            padding: 10px 14px;
            border-radius: 14px;
            font-size: 13.5px;
            position: relative;
            word-break: break-word;
            cursor: pointer;
        }

        .message-bubble.incoming {
            background: #f1f3f2;
            color: #333;
            align-self: flex-start;
            border-bottom-left-radius: 4px;
        }

        .message-bubble.outgoing {
            background: #108a00;
            color: #fff;
            align-self: flex-end;
            border-bottom-right-radius: 4px;
        }

        .reply-banner {
            background: rgba(0,0,0,0.05);
            padding: 4px 8px;
            border-left: 3px solid #108a00;
            font-size: 11.5px;
            margin-bottom: 5px;
            border-radius: 4px;
        }

        .message-input-container {
            padding: 12px 15px;
            background: #fff;
            border-top: 1px solid #eee;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .replying-to-indicator {
            display: none;
            font-size: 12px;
            color: #666;
            background: #f8f9fa;
            padding: 5px 10px;
            border-radius: 6px;
            justify-content: space-between;
            align-items: center;
        }

        .message-input-row {
            display: flex;
            gap: 10px;
        }

        .message-input-row input {
            flex: 1;
            padding: 10px 14px;
            border: 1px solid #ddd;
            border-radius: 20px;
            outline: none;
            font-size: 13.5px;
        }

        .message-input-row input:focus {
            border-color: #108a00;
        }

        .send-msg-btn {
            background: #108a00;
            color: #fff;
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: background 0.2s;
        }

        .send-msg-btn:hover {
            background: #0d6d00;
        }

        /* Custom Context Menu for Right-Click */
        .custom-context-menu {
            display: none;
            position: fixed;
            background: #ffffff;
            border: 1px solid #ccc;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            list-style: none;
            padding: 6px 0;
            margin: 0;
            z-index: 2000;
            border-radius: 8px;
            min-width: 130px;
        }

        .custom-context-menu li {
            padding: 8px 16px;
            cursor: pointer;
            font-size: 13px;
            color: #333;
            transition: background 0.1s;
        }

        .custom-context-menu li:hover {
            background-color: #f0f7f4;
            color: #108a00;
        }

        /* Notifications Modal Specific Styles */
        .notifications-modal-content {
            max-width: 550px;
            max-height: 80vh;
            display: flex;
            flex-direction: column;
            padding: 0;
            overflow: hidden;
        }

        .notifications-list {
            flex: 1;
            overflow-y: auto;
            padding: 15px;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .notification-item {
            background: #f8f9fa;
            border: 1px solid #eee;
            border-radius: 12px;
            padding: 12px 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
        }

        .notification-content p {
            margin: 0 0 4px 0;
            font-size: 13.5px;
            color: #1a331e;
        }

        .notification-content span {
            font-size: 11.5px;
            color: #666;
        }

        .notification-actions {
            display: flex;
            gap: 6px;
            flex-shrink: 0;
        }

        .btn-accept-req {
            background: #108a00;
            color: #fff;
            border: none;
            padding: 5px 10px;
            border-radius: 6px;
            font-size: 11.5px;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.2s;
        }

        .btn-accept-req:hover {
            background: #0d6d00;
        }

        .btn-decline-req {
            background: #fff5f5;
            border: 1px solid #feb2b2;
            color: #c53030;
            padding: 5px 10px;
            border-radius: 6px;
            font-size: 11.5px;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.2s;
        }

        .btn-decline-req:hover {
            background: #c53030;
            color: #fff;
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

        .edit-form-group input:disabled {
            background-color: #f1f3f2;
            color: #666;
            cursor: not-allowed;
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
        .btn-submit-changes:disabled {
            background-color: #cccccc;
            cursor: not-allowed;
        }
        .btn-submit-changes:hover:not(:disabled) {
            background-color: #0d6d00;
        }

        .warning-text {
            color: #c53030;
            font-weight: 600;
            font-size: 13.5px;
            margin-bottom: 15px;
            background: #fff5f5;
            border: 1px solid #feb2b2;
            padding: 10px;
            border-radius: 6px;
        }

        .alert-error {
            background: #fff5f5;
            border: 1px solid #feb2b2;
            color: #c53030;
            padding: 12px 16px;
            border-radius: 10px;
            font-size: 13.5px;
            font-weight: 600;
            margin-bottom: 15px;
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
            <a href="admin_profile_requests.php"><i class="fa-solid fa-clipboard-check"></i> Profile Change Requests</a>
            <a href="notifications.php"><i class="fa-solid fa-bell"></i> Notifications</a>
        </div>
    </nav>

    <main class="main-wrapper">
        <?php if (!empty($error_message)): ?>
            <div class="alert-error">
                <i class="fa-solid fa-triangle-exclamation"></i> <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['success']) && $_GET['success'] === 'truncated'): ?>
            <div style="background: #e8f5e9; border: 1px solid #a3e9a4; color: #108a00; padding: 12px 16px; border-radius: 10px; font-size: 13.5px; font-weight: 600; margin-bottom: 15px;">
                <i class="fa-solid fa-circle-check"></i> Records successfully truncated. Backup archives created in the system folder.
            </div>
        <?php endif; ?>

        <div class="styled-card" style="margin-bottom:18px;padding:20px"><h3>System Overview</h3><div style="max-width:520px;height:250px"><canvas id="adminOverviewChart" aria-label="Student and teacher account totals"></canvas></div></div>
        <div class="styled-card profile-card-layout">
            <div class="profile-header-right">
                <span class="signed-in-badge">Signed in as Admin</span>
                <div class="profile-buttons-column">
                    <button type="button" class="btn-edit-profile" onclick="openEditProfileModal()">
                        <i class="fa-solid fa-pen-to-square"></i> Edit Profile
                    </button>
                    <div class="profile-action-icons-row">
                        <button type="button" class="profile-icon-btn" onclick="openNotificationsModal()" title="Notifications">
                            <i class="fa-solid fa-bell"></i>
                        </button>
                        <button type="button" class="profile-icon-btn" onclick="openChatModal()" title="Chat">
                            <i class="fa-solid fa-message"></i>
                        </button>
                    </div>
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
                    <h2><?php echo strtoupper(htmlspecialchars($full_name ?: 'Administrator')); ?></h2>
                    <p><i class="fa-solid fa-id-card"></i> ID Number: <span><?php echo htmlspecialchars($user['id_number'] ?? 'N/A'); ?></span></p>
                    <p><i class="fa-solid fa-envelope"></i> Email: <span><?php echo htmlspecialchars($user['email'] ?? 'N/A'); ?></span></p>
                </div>
            </div>
        </div>

        <div class="styled-card">
            <div class="section-header">
                <h3>System Management Overview</h3>
                <button type="button" class="btn-truncate-records" onclick="openTruncateModal()">
                    <i class="fa-solid fa-database"></i> Truncate Records
                </button>
            </div>
            
            <div class="dashboard-cards-grid">
                <a href="manage_teachers.php" class="dash-card">
                    <div class="dash-card-banner">
                        <div class="dash-card-icon">
                            <i class="fa-solid fa-chalkboard-user"></i>
                        </div>
                    </div>
                    <div class="dash-card-body">
                        <h4>Teachers Accounts</h4>
                        <p><?php echo $total_teachers; ?> Registered Teacher<?php echo $total_teachers == 1 ? '' : 's'; ?></p>
                        <span style="font-size: 12px; color: #108a00; font-weight: 700; margin-top: 5px;"><i class="fa-solid fa-arrow-right"></i> Click to view records</span>
                    </div>
                </a>

                <a href="manage_program.php" class="dash-card">
                    <div class="dash-card-banner">
                        <div class="dash-card-icon">
                            <i class="fa-solid fa-user-graduate"></i>
                        </div>
                    </div>
                    <div class="dash-card-body">
                        <h4>Students Accounts</h4>
                        <p><?php echo $total_students; ?> Registered Student<?php echo $total_students == 1 ? '' : 's'; ?></p>
                        <span style="font-size: 12px; color: #108a00; font-weight: 700; margin-top: 5px;"><i class="fa-solid fa-arrow-right"></i> Click to view records</span>
                    </div>
                </a>
            </div>
        </div>
    </main>

    <!-- Notifications Modal -->
    <div id="notificationsModal" class="modal-overlay" onclick="closeNotificationsModal(event)">
        <div class="modal-content notifications-modal-content" onclick="event.stopPropagation()">
            <div class="modal-header">
                <h4>System Notifications</h4>
                <span class="close-modal" onclick="closeNotificationsModal()">&times;</span>
            </div>
            <div class="notifications-list">
                <div class="notification-item">
                    <div class="notification-content">
                        <p><strong>student@kld.edu.ph</strong> is nagenroll under section <strong>Bachelor of Science in Information Systems 204</strong>.</p>
                        <span>2 minutes ago</span>
                    </div>
                </div>
                <div class="notification-item">
                    <div class="notification-content">
                        <p><strong>teacher@kld.edu.ph</strong> requested permission to edit their profile information.</p>
                        <span>15 minutes ago</span>
                    </div>
                    <div class="notification-actions">
                        <button type="button" class="btn-accept-req" onclick="alert('Request Accepted!')">Accept</button>
                        <button type="button" class="btn-decline-req" onclick="alert('Request Declined!')">Decline</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Chat Modal -->
    <div id="chatModal" class="modal-overlay" onclick="closeChatModal(event)">
        <div class="modal-content messenger-modal-content" onclick="event.stopPropagation()">
            <div class="messenger-header">
                <h4 id="chatModalTitle">Messages</h4>
                <span class="close-modal" onclick="closeChatModal()">&times;</span>
            </div>
            <div class="messenger-body">
                <!-- Search & Recent Threads view -->
                <div id="chatSearchView" style="display: flex; flex-direction: column; flex: 1; overflow: hidden;">
                    <div class="chat-search-container">
                        <input type="text" id="userSearchInput" class="chat-search-input" placeholder="Search registered student or teacher by full name..." autocomplete="off">
                    </div>
                    <div class="search-results-list" id="searchResultsList">
                        <div class="recent-chats-label">Recent Conversations</div>
                        <div id="recentThreadsContainer">
                            <!-- Dito kusang lalabas ang mga active chat threads lang -->
                        </div>
                    </div>
                </div>

                <!-- Active Conversation view -->
                <div id="chatConversationView" class="chat-conversation-area">
                    <div class="chat-convo-header">
                        <button type="button" class="back-to-search-btn" onclick="backToSearch()">
                            <i class="fa-solid fa-arrow-left"></i> Back
                        </button>
                        <span id="activeChatUserName" style="font-weight: 700; color: #1a331e; font-size: 14px;">User Name</span>
                    </div>
                    <div class="chat-messages-box" id="chatMessagesBox">
                        <!-- BLANKO BY DEFAULT: Magkakalaman lang kapag may mensahe na -->
                    </div>
                    <div class="message-input-container">
                        <div class="replying-to-indicator" id="replyingIndicator">
                            <span id="replyingToText">Replying to a message</span>
                            <span style="cursor: pointer; font-weight: bold;" onclick="cancelReply()">&times;</span>
                        </div>
                        <div class="message-input-row">
                            <input type="text" id="chatMessageInput" placeholder="Type a message...">
                            <button type="button" class="send-msg-btn" onclick="sendChatMessage()">
                                <i class="fa-solid fa-paper-plane"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Custom Context Menu for Right-Click -->
    <ul id="chatContextMenu" class="custom-context-menu">
        <li id="ctxUnsend" data-action="unsend" style="display: none;">Unsend</li>
        <li id="ctxRemove" data-action="remove" style="display: none;">Remove</li>
        <li data-action="forward">Forward</li>
        <li data-action="pin">Pin</li>
        <li data-action="reply">Reply</li>
    </ul>

    <!-- Edit Profile Modal -->
    <div id="editProfileModal" class="modal-overlay" onclick="closeEditProfileModal(event)">
        <div class="modal-content edit-profile-modal-content" onclick="event.stopPropagation()">
            <div class="modal-header">
                <h4>Edit Profile Details</h4>
                <span class="close-modal" onclick="closeEditProfileModal()">&times;</span>
            </div>
            <form action="admin_dashboard.php" method="POST" enctype="multipart/form-data" id="editProfileForm">
                <div class="edit-form-group">
                    <label>Profile Picture</label>
                    <input type="file" name="profile_pic" id="profilePicInput" accept="image/*" onchange="checkForEdits()">
                </div>
                <div class="edit-form-group">
                    <label>New Password (Leave blank if unchanged)</label>
                    <input type="password" name="password" id="passwordInput" placeholder="Enter new password" oninput="checkForEdits()">
                </div>
                <div class="edit-form-group">
                    <label>Last Name</label>
                    <input type="text" name="last_name" value="<?php echo htmlspecialchars($last_name); ?>" oninput="checkForEdits()" required>
                </div>
                <div class="edit-form-group">
                    <label>First Name</label>
                    <input type="text" name="first_name" value="<?php echo htmlspecialchars($first_name); ?>" oninput="checkForEdits()" required>
                </div>
                <div class="edit-form-group">
                    <label>Middle Name</label>
                    <input type="text" name="middle_name" value="<?php echo htmlspecialchars($middle_name); ?>" oninput="checkForEdits()">
                </div>
                <div class="edit-form-group">
                    <label>Role</label>
                    <input type="text" value="<?php echo htmlspecialchars($user['role'] ?? ''); ?>" disabled>
                </div>
                <div class="edit-form-group">
                    <label>Admin ID Number</label>
                    <input type="text" name="id_number" value="<?php echo htmlspecialchars($user['id_number'] ?? ''); ?>" oninput="checkForEdits()" required>
                </div>
                <div class="edit-form-group">
                    <label>KLD Email</label>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" oninput="checkForEdits()" required>
                </div>

                <div class="edit-modal-footer-buttons">
                    <button type="submit" name="update_profile" id="submitChangesBtn" class="bin-submit-changes" disabled>Submit Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Truncate Records Confirmation Modal -->
    <div id="truncateModal" class="modal-overlay" onclick="closeTruncateModal(event)">
        <div class="modal-content" onclick="event.stopPropagation()">
            <div class="modal-header">
                <h4>Truncate Records Confirmation</h4>
                <span class="close-modal" onclick="closeTruncateModal()">&times;</span>
            </div>
            <form action="admin_dashboard.php" method="POST">
                <div class="warning-text">
                    <i class="fa-solid fa-triangle-exclamation"></i> Warning: This action will delete records, are you sure you want to continue?
                </div>
                <div class="edit-form-group">
                    <label>Email Verification Code</label>
                    <input type="text" name="admin_verification_code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" placeholder="Enter the six-digit code" required>
                    <p style="font-size:12px;color:#666">First send a code to your registered administrator email. The code expires in 10 minutes.</p>
                </div>
                <div class="edit-modal-footer-buttons">
                    <button type="submit" name="send_truncate_code" class="btn-submit-changes" formnovalidate>Send Verification Code</button>
                    <button type="submit" name="truncate_records" class="btn-submit-changes" style="background-color: #c53030;">Verify and Reset Records</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openEditProfileModal() {
            document.getElementById('editProfileModal').style.display = 'flex';
        }

        function closeEditProfileModal(event) {
            if (!event || event.target === document.getElementById('editProfileModal') || event.target.classList.contains('close-modal')) {
                document.getElementById('editProfileModal').style.display = 'none';
            }
        }

        function openTruncateModal() {
            document.getElementById('truncateModal').style.display = 'flex';
        }

        function closeTruncateModal(event) {
            if (!event || event.target === document.getElementById('truncateModal') || event.target.classList.contains('close-modal')) {
                document.getElementById('truncateModal').style.display = 'none';
            }
        }

        function openNotificationsModal() {
            document.getElementById('notificationsModal').style.display = 'flex';
        }

        function closeNotificationsModal(event) {
            if (!event || event.target === document.getElementById('notificationsModal') || event.target.classList.contains('close-modal')) {
                document.getElementById('notificationsModal').style.display = 'none';
            }
        }

        // Chat Modal Functions
        function openChatModal() {
            document.getElementById('chatModal').style.display = 'flex';
            backToSearch();
            loadRecentThreads();
        }

        function closeChatModal(event) {
            if (!event || event.target === document.getElementById('chatModal') || event.target.classList.contains('close-modal')) {
                document.getElementById('chatModal').style.display = 'none';
                hideContextMenu();
            }
        }

        function loadRecentThreads() {
            fetch('get_recent_threads.php')
                .then(response => response.json())
                .then(data => {
                    const container = document.getElementById('recentThreadsContainer');
                    container.innerHTML = '';
                    
                    if (data.length === 0) {
                        container.innerHTML = `<div style="padding: 10px 15px; color: #888; font-size: 13px;">No recent conversations yet. Use search above to start chatting.</div>`;
                        return;
                    }

                    data.forEach(thread => {
                        let profilePic = thread.profile_pic && thread.profile_pic.trim() !== '' ? thread.profile_pic : '';
                        let avatarContent = profilePic ? `<img src="${profilePic}" alt="Profile Picture">` : `<i class="fa-solid fa-user"></i>`;
                        
                        let unreadDotHtml = thread.is_unread ? `<div class="unread-dot" title="Unread message"></div>` : '';
                        
                        let itemHtml = `
                            <div class="search-user-item" onclick="openConversation('${thread.full_name.replace(/'/g, "\\'")}', '${thread.role}', ${thread.user_id})">
                                <div class="search-user-avatar">
                                    ${avatarContent}
                                </div>
                                <div class="search-user-info">
                                    <h5>${thread.full_name}</h5>
                                    <p>${thread.last_message}</p>
                                </div>
                                <span style="font-size: 11px; color: #888; white-space: nowrap; margin-right: 5px;">${thread.updated_at}</span>
                                ${unreadDotHtml}
                            </div>
                        `;
                        container.innerHTML += itemHtml;
                    });
                })
                .catch(err => console.error('Error loading threads:', err));
        }

        // Live Search AJAX implementation
        const userSearchInput = document.getElementById('userSearchInput');

        userSearchInput.addEventListener('input', function() {
            let query = this.value.trim();

            if (query.length === 0) {
                loadRecentThreads();
                return;
            }

            fetch(`search_users.php?query=${encodeURIComponent(query)}`)
                .then(response => response.json())
                .then(data => {
                    const container = document.getElementById('recentThreadsContainer');
                    container.innerHTML = '';
                    
                    if (data.length === 0) {
                        container.innerHTML = `<div style="padding: 15px; text-align: center; color: #666; font-size: 13px;">No registered account found.</div>`;
                        return;
                    }

                    data.forEach(user => {
                        let profilePic = user.profile_pic && user.profile_pic.trim() !== '' ? user.profile_pic : '';
                        let avatarContent = profilePic ? `<img src="${profilePic}" alt="Profile Picture">` : `<i class="fa-solid fa-user"></i>`;
                        
                        let itemHtml = `
                            <div class="search-user-item" onclick="openConversation('${user.full_name.replace(/'/g, "\\'")}', '${user.role}', ${user.id ?? 0})">
                                <div class="search-user-avatar">
                                    ${avatarContent}
                                </div>
                                <div class="search-user-info">
                                    <h5>${user.full_name}</h5>
                                    <p>${user.role.charAt(0).toUpperCase() + user.role.slice(1)} • ${user.email}</p>
                                </div>
                            </div>
                        `;
                        container.innerHTML += itemHtml;
                    });
                })
                .catch(error => {
                    console.error('Error searching users:', error);
                });
        });

        let currentActiveUserId = null;

        function openConversation(userName, userRole, userId) {
            currentActiveUserId = userId;
            document.getElementById('chatSearchView').style.display = 'none';
            document.getElementById('chatConversationView').style.display = 'flex';
            document.getElementById('activeChatUserName').innerText = userName + ' (' + userRole + ')';
            document.getElementById('chatModalTitle').innerText = 'Chat with ' + userName;
            
            // Load messages for this conversation via AJAX
            loadConversationMessages(userId);
        }

        const chatMessageInput = document.getElementById('chatMessageInput');

        chatMessageInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                sendChatMessage();
            }
        });

        function loadConversationMessages(userId) {
            fetch(`get_messages.php?user_id=${userId}`)
                .then(response => {
                    // Check kung text/html ang ibinalik (halimbawa: error page o login redirect) sa halip na JSON
                    const contentType = response.headers.get("content-type");
                    if (contentType && contentType.indexOf("application/json") !== -1) {
                        return response.json();
                    } else {
                        return response.text().then(text => {
                            throw new Error("Server returned non-JSON response: " + text.substring(0, 100));
                        });
                    }
                })
                .then(data => {
                    const chatBox = document.getElementById('chatMessagesBox');
                    chatBox.innerHTML = '';

                    if (!Array.isArray(data) || data.length === 0) {
                        return;
                    }

                    data.forEach(msg => {
                        const newMsg = document.createElement('div');
                        newMsg.className = msg.is_outgoing ? 'message-bubble outgoing' : 'message-bubble incoming';
                        
                        let timeString = msg.created_at_formatted || '';
                        newMsg.setAttribute('title', 'Sent on: ' + timeString);

                        let htmlContent = '';
                        if (msg.reply_to) {
                            htmlContent += `<div class="reply-banner">Replying to: ${msg.reply_to}</div>`;
                        }
                        htmlContent += `${msg.message}`;
                        
                        newMsg.innerHTML = htmlContent;
                        chatBox.appendChild(newMsg);
                    });
                    chatBox.scrollTop = chatBox.scrollHeight;
                    attachMessageListeners();
                })
                .catch(err => {
                    console.error('Error loading messages:', err);
                    const chatBox = document.getElementById('chatMessagesBox');
                    chatBox.innerHTML = `<div style="padding: 10px; color: #c53030; font-size: 12.5px; text-align: center;">Error loading messages. Make sure <b>get_messages.php</b> exists and accepts ?user_id=ID</div>`;
                });
        }

        function backToSearch() {
            document.getElementById('chatConversationView').style.display = 'none';
            document.getElementById('chatSearchView').style.display = 'flex';
            document.getElementById('chatModalTitle').innerText = 'Messages';
            document.getElementById('userSearchInput').value = '';
            loadRecentThreads();
            hideContextMenu();
        }

        let replyingTo = null;
        let activeSelectedBubble = null;
        const contextMenu = document.getElementById('chatContextMenu');

        function attachMessageListeners() {
            const bubbles = document.querySelectorAll('.message-bubble');
            bubbles.forEach(bubble => {
                bubble.removeEventListener('contextmenu', handleBubbleRightClick);
                bubble.addEventListener('contextmenu', handleBubbleRightClick);
            });
        }

        function handleBubbleRightClick(e) {
            e.preventDefault();
            activeSelectedBubble = this;

            const isOutgoing = this.classList.contains('outgoing');
            const ctxUnsend = document.getElementById('ctxUnsend');
            const ctxRemove = document.getElementById('ctxRemove');

            if (isOutgoing) {
                ctxUnsend.style.display = 'block';
                ctxRemove.style.display = 'none';
            } else {
                ctxUnsend.style.display = 'none';
                ctxRemove.style.display = 'block';
            }

            contextMenu.style.top = `${e.clientY}px`;
            contextMenu.style.left = `${e.clientX}px`;
            contextMenu.style.display = 'block';
        }

        function hideContextMenu() {
            contextMenu.style.display = 'none';
            activeSelectedBubble = null;
        }

        window.addEventListener('click', function(e) {
            if (!contextMenu.contains(e.target)) {
                hideContextMenu();
            }
        });

        document.querySelectorAll('#chatContextMenu li').forEach(item => {
            item.addEventListener('click', function() {
                const action = this.getAttribute('data-action');
                if (!activeSelectedBubble) return;

                if (action === 'unsend' || action === 'remove') {
                    activeSelectedBubble.remove();
                } else if (action === 'forward') {
                    alert('Forwarded message: "' + activeSelectedBubble.innerText.trim() + '"');
                } else if (action === 'pin') {
                    alert('Pinned message: "' + activeSelectedBubble.innerText.trim() + '"');
                } else if (action === 'reply') {
                    prepareReply(activeSelectedBubble.innerText.trim());
                }

                hideContextMenu();
            });
        });

        function prepareReply(messageText) {
            replyingTo = messageText;
            const indicator = document.getElementById('replyingIndicator');
            const textSpan = document.getElementById('replyingToText');
            textSpan.innerText = 'Replying to: "' + messageText + '"';
            indicator.style.display = 'flex';
            document.getElementById('chatMessageInput').focus();
        }

        function cancelReply() {
            replyingTo = null;
            document.getElementById('replyingIndicator').style.display = 'none';
        }

        function sendChatMessage() {
            const input = document.getElementById('chatMessageInput');
            const text = input.value.trim();
            if (!text || !currentActiveUserId) return;

            const formData = new FormData();
            formData.append('user_id', currentActiveUserId);
            formData.append('message', text);
            if (replyingTo) {
                formData.append('reply_to', replyingTo);
            }

            fetch('send_message.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                const contentType = response.headers.get("content-type");
                if (contentType && contentType.indexOf("application/json") !== -1) {
                    return response.json();
                } else {
                    return response.text().then(text => {
                        throw new Error("Server returned non-JSON response: " + text.substring(0, 100));
                    });
                }
            })
            .then(data => {
                if (data.status === 'success') {
                    const chatBox = document.getElementById('chatMessagesBox');
                    const newMsg = document.createElement('div');
                    newMsg.className = 'message-bubble outgoing';
                    
                    const now = new Date();
                    const timeString = now.toLocaleString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: 'numeric', hour12: true });
                    newMsg.setAttribute('title', 'Sent on: ' + timeString);

                    let htmlContent = '';
                    if (replyingTo) {
                        htmlContent += `<div class="reply-banner">Replying to: ${replyingTo}</div>`;
                    }
                    htmlContent += `${text}`;
                    
                    newMsg.innerHTML = htmlContent;
                    chatBox.appendChild(newMsg);
                    chatBox.scrollTop = chatBox.scrollHeight;

                    input.value = '';
                    cancelReply();
                    attachMessageListeners();
                } else {
                    alert('Failed to send message: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(err => {
                console.error('Error sending message:', err);
                alert('Error sending message. Make sure send_message.php exists and returns JSON. Details in console.');
            });
        }

        function checkForEdits() {
            const picInput = document.getElementById('profilePicInput').files.length > 0;
            const passInput = document.getElementById('passwordInput').value.trim() !== '';
            const submitBtn = document.getElementById('submitChangesBtn');
            const identityInputs = document.querySelectorAll('#editProfileForm input[name="first_name"], #editProfileForm input[name="middle_name"], #editProfileForm input[name="last_name"], #editProfileForm input[name="id_number"], #editProfileForm input[name="email"]');
            const identityChanged = Array.from(identityInputs).some(input => input.value !== input.defaultValue);

            if (picInput || passInput || identityChanged) {
                submitBtn.removeAttribute('disabled');
            } else {
                submitBtn.setAttribute('disabled', 'true');
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            attachMessageListeners();
        });
    </script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script><script>document.addEventListener('DOMContentLoaded',function(){const c=document.getElementById('adminOverviewChart');if(!c||!window.Chart)return;const chart=new Chart(c,{type:'bar',data:{labels:['Students','Teachers'],datasets:[{label:'Active Accounts',data:[<?php echo (int)$chart_student_count; ?>,<?php echo (int)$chart_teacher_count; ?>]}]},options:{responsive:true,maintainAspectRatio:false,scales:{y:{beginAtZero:true,ticks:{precision:0}}}}});async function refresh(){try{const r=await fetch('dashboard_stats.php',{cache:'no-store'});const d=await r.json();if(d.values){chart.data.labels=d.labels;chart.data.datasets[0].data=d.values;chart.update();}}catch(e){console.warn('Dashboard chart refresh failed',e);}}setInterval(refresh,15000);});</script>
</body>
</html>