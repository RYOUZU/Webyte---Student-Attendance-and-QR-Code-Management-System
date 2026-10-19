<?php
session_start();
require_once 'db_connect.php';

$error = '';
$show_section_modal = false;

// Kunin ang mga existing unique sections para sa dropdown
$existing_sections = [];
$sec_query = $conn->query("SELECT DISTINCT section FROM users WHERE section IS NOT NULL AND section != '' ORDER BY section ASC");
if ($sec_query) {
    while ($row = $sec_query->fetch_assoc()) {
        $existing_sections[] = $row['section'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    if (isset($_POST['action']) && $_POST['action'] === 'verify_student_account') {
        header('Content-Type: application/json');
        $student_id = trim($_POST['student_id'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        if ($student_id === '' || $email === '') { echo json_encode(['status' => 'error', 'message' => 'Please enter your ID number and KLD email.']); exit(); }
        $stmt = $conn->prepare("SELECT id, first_name FROM users WHERE id_number = ? AND email = ? AND COALESCE(is_deleted, 0) = 0");
        $stmt->bind_param('ss', $student_id, $email); $stmt->execute(); $user = $stmt->get_result()->fetch_assoc(); $stmt->close();
        if (!$user) { echo json_encode(['status' => 'error', 'message' => 'The ID number and email do not match our records.']); exit(); }
        $code = (string) random_int(100000, 999999);
        $expires = date('Y-m-d H:i:s', time() + 600);
        $upd = $conn->prepare('UPDATE users SET reset_code = ?, reset_expires = ? WHERE id = ?');
        $upd->bind_param('ssi', $code, $expires, $user['id']); $upd->execute(); $upd->close();
        $subject = 'Webyte password reset verification code';
        $body = "Hello {$user['first_name']},\n\nYour Webyte password reset code is: {$code}\nIt expires in 10 minutes. If you did not request this, ignore this email.\n\nWebyte";
        $from = getenv('WEBYTE_MAIL_FROM') ?: 'no-reply@kld.edu.ph';
        if (!@mail($email, $subject, $body, "From: {$from}\r\nContent-Type: text/plain; charset=UTF-8")) {
            echo json_encode(['status' => 'error', 'message' => 'The email could not be sent. Please contact the administrator to configure email delivery.']); exit();
        }
        $_SESSION['reset_email_user_id'] = (int)$user['id'];
        echo json_encode(['status' => 'success', 'message' => 'A verification code has been sent to your registered email address.']); exit();
    }

    if (isset($_POST['action']) && $_POST['action'] === 'verify_reset_code') {
        header('Content-Type: application/json');
        $code = trim($_POST['verification_code'] ?? '');
        $user_id = (int)($_SESSION['reset_email_user_id'] ?? 0);
        if (!$user_id || !preg_match('/^[0-9]{6}$/', $code)) { echo json_encode(['status'=>'error','message'=>'Enter the six-digit code sent to your email.']); exit(); }
        $stmt = $conn->prepare('SELECT id FROM users WHERE id = ? AND reset_code = ? AND reset_expires >= NOW() AND COALESCE(is_deleted, 0) = 0');
        $stmt->bind_param('is', $user_id, $code); $stmt->execute(); $ok = $stmt->get_result()->fetch_assoc(); $stmt->close();
        if (!$ok) { echo json_encode(['status'=>'error','message'=>'The verification code is invalid or expired.']); exit(); }
        $_SESSION['temp_reset_user_id'] = $user_id;
        echo json_encode(['status'=>'success','message'=>'Email verified. You may now create a new password.']); exit();
    }

    if (isset($_POST['action']) && $_POST['action'] === 'reset_password_direct') {
        header('Content-Type: application/json');
        
        if (!isset($_SESSION['temp_reset_user_id'])) {
            echo json_encode(['status' => 'error', 'message' => 'Session expired. Please repeat the verification process.']);
            exit();
        }

        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        $user_id = $_SESSION['temp_reset_user_id'];

        if (empty($new_password) || empty($confirm_password)) {
            echo json_encode(['status' => 'error', 'message' => 'Please fill in all password fields.']);
            exit();
        }

        if ($new_password !== $confirm_password) {
            echo json_encode(['status' => 'error', 'message' => 'The new password and confirmation do not match.']);
            exit();
        }

        if (strlen($new_password) < 8 || strlen($new_password) > 16 || !preg_match('/[A-Z]/', $new_password) || !preg_match('/[a-z]/', $new_password) || !preg_match('/[0-9]/', $new_password) || !preg_match('/[^A-Za-z0-9]/', $new_password)) {
            echo json_encode(['status' => 'error', 'message' => 'Password must be 8–16 characters and include uppercase, lowercase, a number, and a special character.']); exit();
        }
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $update = $conn->prepare("UPDATE users SET password = ?, reset_code = NULL, reset_expires = NULL WHERE id = ?");
        $update->bind_param("si", $hashed_password, $user_id);

        if ($update->execute()) {
            unset($_SESSION['temp_reset_user_id'], $_SESSION['reset_email_user_id']);
            echo json_encode(['status' => 'success', 'message' => 'Password successfully updated! You may now log in.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to update password. Please try again.']);
        }
        exit();
    }

    if (isset($_POST['action']) && $_POST['action'] === 'save_student_section') {
        header('Content-Type: application/json');
        
        if (!isset($_SESSION['temp_student_id'])) {
            echo json_encode(['status' => 'error', 'message' => 'Session expired. Please log in again.']);
            exit();
        }

        $section = trim($_POST['section']);
        $student_id = $_SESSION['temp_student_id'];

        if (empty($section)) {
            echo json_encode(['status' => 'error', 'message' => 'Please select your section.']);
            exit();
        }

        // Update user section in database
        $update = $conn->prepare("UPDATE users SET section = ? WHERE id = ?");
        $update->bind_param("si", $section, $student_id);
        $update->execute();
        $update->close();

        // Also record in student_enrollments if table exists
        $check_table = $conn->query("SHOW TABLES LIKE 'student_enrollments'");
        if ($check_table && $check_table->num_rows > 0) {
            // Kunin ang lahat ng subject IDs sa teacher_subjects para sa section na ito
            $subj_query = $conn->prepare("SELECT id FROM teacher_subjects WHERE section_name = ?");
            if ($subj_query) {
                $subj_query->bind_param("s", $section);
                $subj_query->execute();
                $subj_result = $subj_query->get_result();
                
                while ($subj_row = $subj_result->fetch_assoc()) {
                    $subject_id = $subj_row['id'];
                    // I-insert ang student_id at tamang subject_id
                    $stmt_enr = $conn->prepare("INSERT IGNORE INTO student_enrollments (student_id, subject_id) VALUES (?, ?)");
                    if ($stmt_enr) {
                        $stmt_enr->bind_param("ii", $student_id, $subject_id);
                        $stmt_enr->execute();
                        $stmt_enr->close();
                    }
                }
                $subj_query->close();
            }
        }

        // Fetch user data to complete login
        $stmt_u = $conn->prepare("SELECT id, first_name, middle_name, last_name, role, profile_pic FROM users WHERE id = ?");
        $stmt_u->bind_param("i", $student_id);
        $stmt_u->execute();
        $u_data = $stmt_u->get_result()->fetch_assoc();
        $stmt_u->close();

        unset($_SESSION['temp_student_id']);
        $_SESSION['user_id'] = $u_data['id'];
        $_SESSION['full_name'] = trim($u_data['first_name'] . ' ' . ($u_data['middle_name'] ? $u_data['middle_name'] . ' ' : '') . $u_data['last_name']);
        $_SESSION['role'] = $u_data['role'];
        $_SESSION['profile_pic'] = $u_data['profile_pic'];

        echo json_encode(['status' => 'success', 'message' => 'Section saved successfully! Redirecting...']);
        exit();
    }

    $id_number = trim($_POST['id_number'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!empty($id_number) && !empty($password)) {
        $stmt = $conn->prepare("SELECT id, first_name, middle_name, last_name, password, role, profile_pic, section FROM users WHERE (id_number = ? OR email = ?) AND COALESCE(is_deleted, 0) = 0");
        $stmt->bind_param("ss", $id_number, $id_number);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();

            if (password_verify($password, $user['password'])) {
                // Check if user is a student and their section is empty (due to truncation)
                if ($user['role'] === 'student' && (empty($user['section']) || $user['section'] === null)) {
                    $_SESSION['temp_student_id'] = $user['id'];
                    $show_section_modal = true;
                } else {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['full_name'] = trim($user['first_name'] . ' ' . ($user['middle_name'] ? $user['middle_name'] . ' ' : '') . $user['last_name']);
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['profile_pic'] = $user['profile_pic'];

                    if ($user['role'] === 'faculty') {
                        header("Location: teacher_dashboard.php");
                        exit();
                    } elseif ($user['role'] === 'student') {
                        header("Location: student_dashboard.php");
                        exit();
                    } elseif ($user['role'] === 'admin') {
                        header("Location: admin_dashboard.php");
                        exit();
                    } else {
                        header("Location: index.php");
                        exit();
                    }
                }
            } else {
                $error = "Incorrect password. Please try again.";
            }
        } else {
            $error = "No account found with that ID Number or Email.";
        }
    } else {
        $error = "Please fill in all fields.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log-in - KLD Attendance System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
    <style>
        body {
            background: linear-gradient(135deg, #f0f7f4 0%, #e8f5e9 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        .main-container {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 40px 20px;
        }
        .login-card {
            background: #ffffff;
            border-radius: 20px;
            padding: 35px;
            width: 100%;
            max-width: 420px;
            box-shadow: 0 10px 30px rgba(0, 128, 0, 0.08);
            border: 1px solid rgba(16, 138, 0, 0.08);
            box-sizing: border-box;
        }
        .login-header {
            text-align: center;
            margin-bottom: 25px;
        }
        .login-header h2 {
            color: #1a331e;
            margin: 10px 0 5px 0;
            font-size: 26px;
            font-weight: 800;
        }
        .login-header p {
            color: #666;
            font-size: 14px;
            margin: 0;
        }
        .form-group {
            margin-bottom: 18px;
            position: relative;
        }
        .form-group label {
            display: block;
            margin-bottom: 6px;
            color: #2c3e50;
            font-weight: 700;
            font-size: 13px;
        }
        .input-with-icon {
            position: relative;
            display: flex;
            align-items: center;
        }
        .input-with-icon i:not(.password-toggle) {
            position: absolute;
            left: 15px;
            color: #108a00;
            font-size: 14px;
            pointer-events: none;
            z-index: 2;
        }
        .input-with-icon .form-control {
            padding-left: 42px;
            padding-right: 40px;
        }
        .password-toggle {
            position: absolute;
            right: 15px;
            color: #666;
            cursor: pointer;
            font-size: 14px;
            z-index: 10;
        }
        .password-toggle:hover {
            color: #108a00;
        }
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 10px;
            font-size: 14px;
            box-sizing: border-box;
            background: #fff;
        }
        .form-control:focus {
            border-color: #108a00;
            box-shadow: 0 0 0 3px rgba(16, 138, 0, 0.1);
            outline: none;
        }
        select.form-control {
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23108a00' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 15px center;
            background-size: 14px;
            padding-right: 40px;
        }
        .forgot-password-container {
            text-align: right;
            margin-top: 8px; /* Binago mula -10px patungong positive value para hindi mag-overlap sa password text box */
            margin-bottom: 20px; /* Dinagdagan ang space bago ang submit button */
        }
        .forgot-password-link {
            font-size: 12px;
            color: #108a00;
            text-decoration: none;
            font-weight: 700;
            cursor: pointer;
        }
        .forgot-password-link:hover {
            text-decoration: underline;
        }
        .btn-submit {
            background: #108a00;
            color: #ffffff;
            border: none;
            width: 100%;
            padding: 14px;
            font-size: 16px;
            font-weight: 700;
            border-radius: 12px;
            cursor: pointer;
            margin-top: 5px; /* Binago para balanse ang spacing */
        }
        .btn-submit:hover {
            background: #0d7000;
        }
        .error-msg {
            background: #ffebee;
            color: #c62828;
            padding: 10px 15px;
            border-radius: 8px;
            font-size: 13px;
            margin-bottom: 15px;
            text-align: center;
            font-weight: 600;
        }
        .signup-link {
            text-align: center;
            margin-top: 20px;
            font-size: 13px;
            color: #666;
        }
        .signup-link a {
            color: #108a00;
            text-decoration: none;
            font-weight: 700;
        }
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            justify-content: center;
            align-items: center;
            z-index: 1000;
            padding: 20px;
            box-sizing: border-box;
        }
        .modal-card {
            background: #ffffff;
            width: 100%;
            max-width: 450px;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
            position: relative;
            box-sizing: border-box;
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }
        .modal-header h3 {
            color: #108a00;
            margin: 0;
            font-size: 18px;
        }
        .close-modal {
            background: none;
            border: none;
            font-size: 20px;
            cursor: pointer;
            color: #666;
        }
        .close-modal:hover {
            color: #108a00;
        }
        .modal-body p {
            font-size: 13px;
            color: #555;
            margin-bottom: 15px;
            line-height: 1.5;
        }
        .modal-alert {
            padding: 10px;
            border-radius: 8px;
            font-size: 12px;
            margin-bottom: 15px;
            display: none;
            text-align: center;
            font-weight: 600;
        }
        .modal-alert.error {
            background: #ffebee;
            color: #c62828;
            display: block;
        }
        .modal-alert.success {
            background: #e8f5e9;
            color: #2e7d32;
            display: block;
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
            <a href="index.php"><i class="fa-solid fa-house"></i> Home</a>
            <a href="about.php"><i class="fa-solid fa-circle-info"></i> About us</a>
            <a href="signup.php"><i class="fa-solid fa-user-plus"></i> Sign-up</a>
            <a href="login.php" class="active"><i class="fa-solid fa-right-to-bracket"></i> Log-in</a>
        </div>
    </nav>

    <div class="main-container">
        <div class="login-card">
            <div class="login-header">
                <img src="kld-logo.png" alt="KLD Logo" style="width: 55px; height: 55px; object-fit: contain; margin-bottom: 5px;">
                <h2>Welcome Back</h2>
                <p>Log in to your KLD Account</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="error-msg"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form action="login.php" method="POST">
                <div class="form-group">
                    <label>ID Number or Email</label>
                    <div class="input-with-icon">
                        <i class="fa-solid fa-user"></i>
                        <input type="text" name="id_number" class="form-control" placeholder="Enter ID Number or Email" required>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 5px;">
                    <label>Password</label>
                    <div class="input-with-icon">
                        <i class="fa-solid fa-lock"></i>
                        <input type="password" name="password" id="loginPassword" class="form-control" placeholder="••••••••" required>
                        <i class="fa-solid fa-eye password-toggle" id="toggleLoginPassword" onclick="togglePasswordVisibility('loginPassword', 'toggleLoginPassword')"></i>
                    </div>
                </div>

                <div class="forgot-password-container">
                    <a href="#" class="forgot-password-link" onclick="openForgotPasswordModal(event)">Forgot Password?</a>
                </div>

                <button type="submit" class="btn-submit">Log In</button>
            </form>

            <div class="signup-link">
                Don't have an account? <a href="signup.php">Sign up here</a>
            </div>
        </div>
    </div>

    <!-- Section Selection Modal (Triggered ONLY if existing student section was truncated/blank) -->
    <div class="modal-overlay" id="sectionSelectionModal" style="<?php echo $show_section_modal ? 'display: flex;' : 'display: none;'; ?>">
        <div class="modal-card">
            <div class="modal-header">
                <h3>Select Your Section</h3>
                <button class="close-modal" onclick="closeSectionModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div id="sectionModalAlert" class="modal-alert"></div>
                <p>System records were reset by the Admin. Please select your correct section from the list to properly update your enrollment in the database before proceeding.</p>
                <div class="form-group">
                    <label>Existing Section</label>
                    <div class="input-with-icon">
                        <i class="fa-solid fa-users-rectangle"></i>
                        <select id="studentSectionSelect" class="form-control" required>
                            <option value="">-- Select your Section --</option>
                            <?php 
                            $sec_query_modal = $conn->query("SELECT DISTINCT section_name FROM sections UNION SELECT DISTINCT section FROM users WHERE section IS NOT NULL AND section != '' ORDER BY 1 ASC");
                            if ($sec_query_modal && $sec_query_modal->num_rows > 0) {
                                while ($s_row = $sec_query_modal->fetch_assoc()) {
                                    $sec_val = $s_row['section_name'] ?? $s_row['section'];
                                    if (!empty($sec_val)) {
                                        echo '<option value="' . htmlspecialchars($sec_val) . '">' . htmlspecialchars($sec_val) . '</option>';
                                    }
                                }
                            } else {
                                foreach ($existing_sections as $sec) {
                                    echo '<option value="' . htmlspecialchars($sec) . '">' . htmlspecialchars($sec) . '</option>';
                                }
                            }
                            ?>
                        </select>
                    </div>
                </div>
                <button type="button" class="btn-submit" id="saveSectionBtn" onclick="saveStudentSection()">Confirm and Proceed</button>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="forgotPasswordModal">
        <div class="modal-card">
            <div class="modal-header">
                <h3 id="modalTitle">Reset Password</h3>
                <button class="close-modal" onclick="closeForgotPasswordModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div id="modalAlert" class="modal-alert"></div>

                <div id="stepVerifyContainer">
                    <p>Please enter your Student ID and registered KLD email to verify your account.</p>
                    <div class="form-group">
                        <label>Student ID Number</label>
                        <div class="input-with-icon">
                            <i class="fa-solid fa-id-card"></i>
                            <input type="text" id="verifyStudentId" class="form-control" placeholder="e.g., 2023-00123" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>KLD Email Address</label>
                        <div class="input-with-icon">
                            <i class="fa-solid fa-envelope"></i>
                            <input type="email" id="verifyEmail" class="form-control" placeholder="e.g., jdelacruz@kld.edu.ph" required>
                        </div>
                    </div>
                    <button type="button" class="btn-submit" id="verifyAccountBtn" onclick="verifyStudentAccount()">Verify Account</button>
                </div>

                <div id="stepEmailCodeContainer" style="display: none;">
                    <p>Enter the six-digit verification code sent to your registered email address. The code expires in 10 minutes.</p>
                    <div class="form-group"><label>Email Verification Code</label><input type="text" id="resetVerificationCode" class="form-control" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" placeholder="123456" required></div>
                    <button type="button" class="btn-submit" id="verifyResetCodeBtn" onclick="verifyResetCode()">Verify Code</button>
                </div>
                <div id="stepNewPasswordContainer" style="display: none;">
                    <p>Email verified. Choose a strong new password (8–16 characters, uppercase, lowercase, number, and special character).</p>
                    <div class="form-group">
                        <label>New Password</label>
                        <div class="input-with-icon">
                            <i class="fa-solid fa-key"></i>
                            <input type="password" id="newPassword" class="form-control" placeholder="••••••••" required>
                            <i class="fa-solid fa-eye password-toggle" id="toggleNewPassword" onclick="togglePasswordVisibility('newPassword', 'toggleNewPassword')"></i>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Confirm New Password</label>
                        <div class="input-with-icon">
                            <i class="fa-solid fa-key"></i>
                            <input type="password" id="confirmPassword" class="form-control" placeholder="••••••••" required>
                            <i class="fa-solid fa-eye password-toggle" id="toggleConfirmPassword" onclick="togglePasswordVisibility('confirmPassword', 'toggleConfirmPassword')"></i>
                        </div>
                    </div>
                    <button type="button" class="btn-submit" id="saveNewPasswordBtn" onclick="saveNewPassword()">Save New Password</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function togglePasswordVisibility(fieldId, iconId) {
            const passwordField = document.getElementById(fieldId);
            const toggleIcon = document.getElementById(iconId);
            
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordField.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }

        function closeSectionModal() {
            document.getElementById('sectionSelectionModal').style.display = 'none';
        }

        function openForgotPasswordModal(e) {
            e.preventDefault();
            document.getElementById('forgotPasswordModal').style.display = 'flex';
            document.getElementById('stepVerifyContainer').style.display = 'block';
            document.getElementById('stepNewPasswordContainer').style.display = 'none';
            document.getElementById('modalTitle').textContent = 'Reset Password - Verify';
            document.getElementById('modalAlert').style.display = 'none';
            document.getElementById('verifyStudentId').value = '';
            document.getElementById('verifyEmail').value = '';
            document.getElementById('newPassword').value = '';
            document.getElementById('confirmPassword').value = '';
        }

        function closeForgotPasswordModal() {
            document.getElementById('forgotPasswordModal').style.display = 'none';
        }

        window.onclick = function(event) {
            const modal = document.getElementById('forgotPasswordModal');
            const sectionModal = document.getElementById('sectionSelectionModal');
            if (event.target === modal) {
                modal.style.display = 'none';
            }
            if (event.target === sectionModal) {
                // Keep open or let them close via X button
            }
        }

        function showModalAlert(message, type, alertBoxId = 'modalAlert') {
            const alertBox = document.getElementById(alertBoxId);
            alertBox.textContent = message;
            alertBox.className = 'modal-alert ' + type;
            alertBox.style.display = 'block';
        }

        function saveStudentSection() {
            const section = document.getElementById('studentSectionSelect').value;
            const btn = document.getElementById('saveSectionBtn');

            if (!section) {
                showModalAlert('Please select your section from the dropdown.', 'error', 'sectionModalAlert');
                return;
            }

            btn.disabled = true;
            btn.textContent = 'Saving Section...';

            const formData = new FormData();
            formData.append('action', 'save_student_section');
            formData.append('section', section);

            fetch('login.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                btn.disabled = false;
                btn.textContent = 'Confirm and Proceed';

                if (data.status === 'success') {
                    showModalAlert(data.message, 'success', 'sectionModalAlert');
                    setTimeout(() => {
                        window.location.href = 'student_dashboard.php';
                    }, 1200);
                } else {
                    showModalAlert(data.message, 'error', 'sectionModalAlert');
                }
            })
            .catch(() => {
                btn.disabled = false;
                btn.textContent = 'Confirm and Proceed';
                showModalAlert('A network error occurred. Please try again.', 'error', 'sectionModalAlert');
            });
        }

        function verifyStudentAccount() {
            const studentId = document.getElementById('verifyStudentId').value.trim();
            const email = document.getElementById('verifyEmail').value.trim();
            const btn = document.getElementById('verifyAccountBtn');

            if (!studentId || !email) {
                showModalAlert('Please enter your Student ID and KLD Email.', 'error');
                return;
            }

            btn.disabled = true;
            btn.textContent = 'Checking Records...';

            const formData = new FormData();
            formData.append('action', 'verify_student_account');
            formData.append('student_id', studentId);
            formData.append('email', email);

            fetch('login.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                btn.disabled = false;
                btn.textContent = 'Verify Account';

                if (data.status === 'success') {
                    showModalAlert(data.message, 'success');
                    setTimeout(() => {
                        document.getElementById('modalAlert').style.display = 'none';
                        document.getElementById('stepVerifyContainer').style.display = 'none';
                        document.getElementById('stepEmailCodeContainer').style.display = 'block';
                        document.getElementById('modalTitle').textContent = 'Verify Email';
                    }, 1000);
                } else {
                    showModalAlert(data.message, 'error');
                }
            })
            .catch(() => {
                btn.disabled = false;
                btn.textContent = 'Verify Account';
                showModalAlert('A network error occurred. Please try again.', 'error');
            });
        }

        function verifyResetCode() {
            const code = document.getElementById('resetVerificationCode').value.trim();
            const btn = document.getElementById('verifyResetCodeBtn');
            if (!/^[0-9]{6}$/.test(code)) { showModalAlert('Enter the six-digit code sent to your email.', 'error'); return; }
            btn.disabled = true; btn.textContent = 'Verifying...';
            const data = new FormData(); data.append('action', 'verify_reset_code'); data.append('verification_code', code);
            fetch('login.php', { method: 'POST', body: data }).then(r => r.json()).then(result => {
                btn.disabled = false; btn.textContent = 'Verify Code';
                if (result.status === 'success') {
                    showModalAlert(result.message, 'success');
                    setTimeout(() => { document.getElementById('stepEmailCodeContainer').style.display = 'none'; document.getElementById('stepNewPasswordContainer').style.display = 'block'; document.getElementById('modalTitle').textContent = 'Create New Password'; }, 500);
                } else showModalAlert(result.message, 'error');
            }).catch(() => { btn.disabled = false; btn.textContent = 'Verify Code'; showModalAlert('A network error occurred. Please try again.', 'error'); });
        }

        function saveNewPassword() {
            const newPassword = document.getElementById('newPassword').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            const btn = document.getElementById('saveNewPasswordBtn');

            if (!newPassword || !confirmPassword) {
                showModalAlert('Please fill in all password fields.', 'error');
                return;
            }

            if (newPassword !== confirmPassword) {
                showModalAlert('The new password and confirmation do not match.', 'error');
                return;
            }

            btn.disabled = true;
            btn.textContent = 'Verifying and Updating...';

            const formData = new FormData();
            formData.append('action', 'reset_password_direct');
            formData.append('new_password', newPassword);
            formData.append('confirm_password', confirmPassword);

            fetch('login.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                btn.disabled = false;
                btn.textContent = 'Save New Password';

                if (data.status === 'success') {
                    showModalAlert(data.message, 'success');
                    setTimeout(() => {
                        closeForgotPasswordModal();
                        location.reload();
                    }, 2000);
                } else {
                    showModalAlert(data.message, 'error');
                }
            })
            .catch(() => {
                btn.disabled = false;
                btn.textContent = 'Save New Password';
                showModalAlert('A network error occurred. Please try again.', 'error');
            });
        }
    </script>
</body>
</html>