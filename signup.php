<?php
session_start();
require_once 'db_connect.php';

$error = '';
$success_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = preg_replace('/\s+/', ' ', trim($_POST['first_name'] ?? ''));
    $middle_name = preg_replace('/\s+/', ' ', trim($_POST['middle_name'] ?? ''));
    $last_name = preg_replace('/\s+/', ' ', trim($_POST['last_name'] ?? ''));
    $id_number = trim($_POST['id_number'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $raw_password = $_POST['password'] ?? '';
    // Public registration is student-only. Staff accounts are created through authorized admin workflows.
    $role = 'student';
    $sec_val = trim($_POST['student_section'] ?? '');
    $student_section = $sec_val !== '' ? $sec_val : null;
    $teacher_institute = null;
    $teacher_position = null;

    $name_pattern = "/^[\\p{L}]+(?:[ -][\\p{L}]+)*$/u";
    foreach (['First name' => $first_name, 'Middle name' => $middle_name, 'Last name' => $last_name] as $label => $value) {
        if ($label === 'Middle name' && strtoupper($value) === 'N/A') continue;
        if ($value === '' || !preg_match($name_pattern, $value)) {
            $error = $label . ' may contain letters, spaces, and hyphens only.';
            break;
        }
    }
    $capitalize_name = static function ($value) {
        return preg_replace_callback('/(^|[\s\-])(\p{L})/u', static function ($m) { return $m[1] . mb_strtoupper($m[2], 'UTF-8'); }, mb_strtolower($value, 'UTF-8'));
    };
    $first_name = $capitalize_name($first_name);
    $middle_name = strtoupper($middle_name) === 'N/A' ? 'N/A' : $capitalize_name($middle_name);
    $last_name = $capitalize_name($last_name);

    if ($error === '' && empty($_POST['privacy_agree'])) $error = 'You must agree to the privacy policy before signing up.';
    if ($error === '' && !preg_match('/^202[0-9]-[0-9]{2}-[0-9]{6}$/', $id_number)) $error = 'Enter an ID number in the format 202X-0X-XXXXXX.';
    if ($error === '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $error = 'Enter a valid school email address.';
    if ($error === '' && !preg_match('/^[A-Z0-9._%+-]+@kld\.edu\.ph$/i', $email)) $error = 'Only official KLD email addresses ending in @kld.edu.ph are accepted.';
    if ($error === '' && (strlen($raw_password) < 8 || strlen($raw_password) > 16 || !preg_match('/[A-Z]/', $raw_password) || !preg_match('/[a-z]/', $raw_password) || !preg_match('/[0-9]/', $raw_password) || !preg_match('/[^A-Za-z0-9]/', $raw_password))) $error = 'Password must be 8–16 characters and include uppercase, lowercase, a number, and a special character.';
    if ($error === '' && $student_section === null) $error = 'Please select your section.';

    if ($error === '') {
        $password = password_hash($raw_password, PASSWORD_DEFAULT);
        $profile_pic = 'kld-logo.png';
        if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
            $tmp = $_FILES['profile_pic']['tmp_name'];
            $mime = mime_content_type($tmp);
            $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
            if (isset($allowed[$mime]) && getimagesize($tmp)) {
                $upload_dir = 'uploads/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                $file_name = bin2hex(random_bytes(12)) . '.' . $allowed[$mime];
                if (move_uploaded_file($tmp, $upload_dir . $file_name)) $profile_pic = $upload_dir . $file_name;
            }
        }
        $stmt = $conn->prepare("INSERT INTO users (first_name, middle_name, last_name, id_number, email, password, role, section, institute, position, profile_pic) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssssssss", $first_name, $middle_name, $last_name, $id_number, $email, $password, $role, $student_section, $teacher_institute, $teacher_position, $profile_pic);
        if ($stmt->execute()) {
            $full_name = trim($first_name . ' ' . $middle_name . ' ' . $last_name);
            $subject = 'Webyte account registration confirmation';
            $body = "Hello {$full_name},\n\nYour Webyte student account has been registered successfully using this email address. If you did not create this account, please contact your school administrator.\n\nWebyte";
            $headers = "From: " . (getenv('WEBYTE_MAIL_FROM') ?: 'no-reply@kld.edu.ph') . "\r\nContent-Type: text/plain; charset=UTF-8";
            $mail_sent = @mail($email, $subject, $body, $headers);
            if (!$mail_sent) $success_message = 'Your account was created, but the confirmation email could not be sent. Ask the administrator to configure the server email service.';
            else $success_message = 'Registration successful. A confirmation email has been sent. You may now log in.';
        } else {
            $error = 'Registration failed. ID number or email may already be in use.';
        }
        $stmt->close();
    }
}

$programs_query = "SELECT id, program_name FROM programs ORDER BY program_name ASC";
$programs_result = $conn->query($programs_query);

$sections_query = "SELECT s.id, s.section_name, s.year_level, s.program_id, p.program_name FROM sections s JOIN programs p ON s.program_id = p.id ORDER BY s.section_name ASC";
$sections_result = $conn->query($sections_query);
$sections_data = [];
while ($row = $sections_result->fetch_assoc()) {
    $sections_data[] = $row;
}

$institutes_query = "SELECT id, institute_name FROM institutes ORDER BY institute_name ASC";
$institutes_result = $conn->query($institutes_query);
$institutes_data = [];
while ($row = $institutes_result->fetch_assoc()) {
    $institutes_data[] = $row;
}

$taken_positions_query = "SELECT institute, position FROM users WHERE role = 'faculty' AND position IN ('Institute Dean', 'Institute Associate Dean')";
$taken_res = $conn->query($taken_positions_query);
$taken_positions = [];
while ($row = $taken_res->fetch_assoc()) {
    $taken_positions[$row['institute']][] = $row['position'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - KLD Attendance System</title>
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
        .signup-card {
            background: #ffffff;
            border-radius: 20px;
            padding: 35px;
            width: 100%;
            max-width: 550px;
            box-shadow: 0 10px 30px rgba(0, 128, 0, 0.08);
            border: 1px solid rgba(16, 138, 0, 0.08);
            box-sizing: border-box;
        }
        .signup-header {
            text-align: center;
            margin-bottom: 25px;
        }
        .signup-header h2 {
            color: #1a331e;
            margin: 10px 0 5px 0;
            font-size: 26px;
            font-weight: 800;
        }
        .signup-header p {
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
        .input-with-icon i.fa-solid:not(.password-toggle) {
            position: absolute;
            left: 15px;
            color: #108a00;
            font-size: 14px;
            pointer-events: none;
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
        .form-control:disabled {
            background-color: #f1f3f2;
            cursor: not-allowed;
            opacity: 0.7;
        }
        .role-buttons-group {
            display: flex;
            gap: 10px;
        }
        .role-btn {
            flex: 1;
            padding: 12px 10px;
            background: #f8faf9;
            border: 1px solid #ddd;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 700;
            color: #555;
            cursor: pointer;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 5px;
        }
        .role-btn.active {
            background: #108a00;
            color: #ffffff;
            border-color: #108a00;
        }
        .role-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            background-color: #f1f3f2;
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
            margin-top: 10px;
        }
        .btn-submit:disabled {
            background: #ccc;
            cursor: not-allowed;
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
        .login-link {
            text-align: center;
            margin-top: 20px;
            font-size: 13px;
            color: #666;
            display: flex;
            flex-direction: column;
            gap: 8px;
            align-items: center;
        }
        .login-link a {
            color: #108a00;
            text-decoration: none;
            font-weight: 700;
        }
        .login-link a:hover {
            text-decoration: underline;
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
            <a href="signup.php" class="active"><i class="fa-solid fa-user-plus"></i> Sign-up</a>
            <a href="login.php"><i class="fa-solid fa-right-to-bracket"></i> Log-in</a>
        </div>
    </nav>

    <div class="main-container">
        <div class="signup-card">
            <div class="signup-header">
                <img src="kld-logo.png" alt="KLD Logo" style="width: 55px; height: 55px; object-fit: contain; margin-bottom: 5px;">
                <h2>Registration</h2>
                <p>Register your KLD Account</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="error-msg"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form action="signup.php" method="POST" enctype="multipart/form-data" id="signupForm">
                <div class="form-group">
                    <label>Upload Profile Photo (Optional)</label>
                    <input type="file" name="profile_pic" class="form-control" accept="image/*">
                </div>

                <div class="form-group">
                    <label>First Name</label>
                    <div class="input-with-icon">
                        <i class="fa-solid fa-user"></i>
                        <input type="text" name="first_name" id="firstName" class="form-control" placeholder="e.g., Hanz Darwin" required oninput="checkFormProgression()">
                    </div>
                </div>

                <div class="form-group">
                    <label>Middle Name</label>
                    <div class="input-with-icon">
                        <i class="fa-solid fa-user"></i>
                        <input type="text" name="middle_name" id="middleName" class="form-control" placeholder="e.g., Rellora or N/A" required disabled oninput="checkFormProgression()">
                    </div>
                </div>

                <div class="form-group">
                    <label>Last Name</label>
                    <div class="input-with-icon">
                        <i class="fa-solid fa-user"></i>
                        <input type="text" name="last_name" id="lastName" class="form-control" placeholder="e.g., San Jose" required disabled oninput="checkFormProgression()">
                    </div>
                </div>

                <div class="form-group">
                    <label>Role</label>
                    <input type="hidden" name="role" id="roleInput" value="student" required>
                    <div class="role-buttons-group"><span class="role-btn active" aria-current="true"><i class="fa-solid fa-user-graduate"></i> Student</span></div>
                </div>

                <div class="form-group">
                    <label id="idNumberLabel">Student Number</label>
                    <div class="input-with-icon">
                        <i class="fa-solid fa-id-card"></i>
                        <input type="text" name="id_number" id="idNumber" class="form-control" placeholder="202X-0X-XXXXXX" pattern="202[0-9]-[0-9]{2}-[0-9]{6}" minlength="14" maxlength="14" required disabled oninput="checkFormProgression()">
                    </div>
                </div>

                <div class="form-group">
                    <label>KLD Email</label>
                    <div class="input-with-icon">
                        <i class="fa-solid fa-envelope"></i>
                        <input type="email" name="email" id="emailInput" class="form-control" placeholder="e.g., hdsanjose@kld.edu.ph" required disabled oninput="checkFormProgression()">
                    </div>
                </div>

                <div class="form-group">
                    <label>Password</label>
                    <div class="input-with-icon">
                        <i class="fa-solid fa-lock"></i>
                        <input type="password" name="password" id="passwordInput" class="form-control" placeholder="••••••••" required disabled oninput="checkFormProgression()">
                        <i class="fa-solid fa-eye password-toggle" id="togglePassword" onclick="togglePasswordVisibility('passwordInput', 'togglePassword')"></i>
                    </div>
                </div>

                <div id="studentFields">
                    <div class="form-group">
                        <label>Program / Course</label>
                        <select name="student_program" id="studentProgram" class="form-control" disabled onchange="onProgramChange()">
                            <option value="">Select Program / Course</option>
                            <?php if ($programs_result && $programs_result->num_rows > 0): ?>
                                <?php while ($prog = $programs_result->fetch_assoc()): ?>
                                    <option value="<?php echo $prog['id']; ?>"><?php echo htmlspecialchars($prog['program_name']); ?></option>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Year Level</label>
                        <select name="student_year" id="studentYear" class="form-control" disabled onchange="onYearChange()">
                            <option value="">Select Year Level</option>
                            <option value="First Year">First Year</option>
                            <option value="Second Year">Second Year</option>
                            <option value="Third Year">Third Year</option>
                            <option value="Fourth Year">Fourth Year</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Section</label>
                        <select name="student_section" id="studentSection" class="form-control" disabled onchange="checkFormProgression()">
                            <option value="">Select Section</option>
                        </select>
                    </div>
                </div>

                <div id="teacherFields" style="display: none;">
                    <div class="form-group">
                        <label>Institute</label>
                        <select name="teacher_institute" id="teacherInstitute" class="form-control" disabled onchange="onInstituteChange()">
                            <option value="">Select Institute</option>
                            <?php if (!empty($institutes_data)): ?>
                                <?php foreach ($institutes_data as $inst): ?>
                                    <option value="<?php echo htmlspecialchars($inst['institute_name']); ?>"><?php echo htmlspecialchars($inst['institute_name']); ?></option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Position</label>
                        <select name="teacher_position" id="teacherPosition" class="form-control" disabled onchange="checkFormProgression()">
                            <option value="">Select Position</option>
                            <option value="Institute Dean">Institute Dean</option>
                            <option value="Institute Associate Dean">Institute Associate Dean</option>
                            <option value="Associate Professor I">Associate Professor I</option>
                            <option value="Associate Professor II">Associate Professor II</option>
                            <option value="Associate Professor III">Associate Professor III</option>
                            <option value="Assistant Professor I">Assistant Professor I</option>
                            <option value="Assistant Professor II">Assistant Professor II</option>
                            <option value="Assistant Professor III">Assistant Professor III</option>
                        </select>
                    </div>
                </div>

                <div class="form-group" style="display:flex;align-items:flex-start;gap:9px;margin:14px 0;">
                    <input type="checkbox" name="privacy_agree" id="privacyAgree" value="1" required style="margin-top:4px;">
                    <label for="privacyAgree" style="font-size:13px;line-height:1.45;">I agree to the <a href="privacy_policy.php" target="_blank" rel="noopener">Privacy Policy</a>.</label>
                </div>
                <?php if ($error !== ''): ?><p role="alert" style="color:#b42318;"><?php echo htmlspecialchars($error); ?></p><?php endif; ?>
                <?php if ($success_message !== ''): ?><p role="status" style="color:#137333;"><?php echo htmlspecialchars($success_message); ?> <a href="login.php">Go to login</a></p><?php endif; ?>
                <button type="submit" class="btn-submit" id="submitBtn" disabled>Sign Up</button>
            </form>

            <div class="login-link">
                <div>Already have an account? <a href="login.php">Log-in here</a> | <a href="privacy_policy.php" target="_blank">Data Privacy Policy</a></div>
            </div>
        </div>
    </div>

    <script>
        const allSections = <?php echo json_encode($sections_data); ?>;
        const takenPositionsByInstitute = <?php echo json_encode($taken_positions); ?>;

        document.addEventListener('DOMContentLoaded', () => {
            checkFormProgression();
        });

        function selectRole(role) {
            document.getElementById('roleInput').value = role;
            
            document.querySelectorAll('.role-btn').forEach(btn => {
                if (btn.getAttribute('data-role') === role) {
                    btn.classList.add('active');
                } else {
                    btn.classList.remove('active');
                }
            });

            const studentFields = document.getElementById('studentFields');
            const teacherFields = document.getElementById('teacherFields');
            const idNumberLabel = document.getElementById('idNumberLabel');

            if (role === 'student') {
                studentFields.style.display = 'block';
                teacherFields.style.display = 'none';
                idNumberLabel.textContent = 'Student Number';
            } else if (role === 'faculty') {
                studentFields.style.display = 'none';
                teacherFields.style.display = 'block';
                idNumberLabel.textContent = 'Employee Number';
            } else if (role === 'admin') {
                studentFields.style.display = 'none';
                teacherFields.style.display = 'none';
                idNumberLabel.textContent = 'Admin ID Number';
            }
            checkFormProgression();
        }

        function onProgramChange() {
            const programId = document.getElementById('studentProgram').value;
            const yearSelect = document.getElementById('studentYear');
            const sectionSelect = document.getElementById('studentSection');

            yearSelect.value = "";
            sectionSelect.innerHTML = '<option value="">Select Section</option>';
            sectionSelect.setAttribute('disabled', 'true');

            if (programId !== "") {
                yearSelect.removeAttribute('disabled');
            } else {
                yearSelect.setAttribute('disabled', 'true');
            }
            checkFormProgression();
        }

        function onYearChange() {
            const programId = document.getElementById('studentProgram').value;
            const yearLevel = document.getElementById('studentYear').value;
            const sectionSelect = document.getElementById('studentSection');

            sectionSelect.innerHTML = '<option value="">Select Section</option>';

            if (yearLevel !== "" && programId !== "") {
                const filtered = allSections.filter(sec => sec.program_id == programId && sec.year_level === yearLevel);
                
                if (filtered.length > 0) {
                    filtered.forEach(sec => {
                        const opt = document.createElement('option');
                        opt.value = sec.section_name;
                        opt.textContent = sec.section_name;
                        sectionSelect.appendChild(opt);
                    });
                    sectionSelect.removeAttribute('disabled');
                } else {
                    const opt = document.createElement('option');
                    opt.value = "";
                    opt.textContent = "No sections found";
                    sectionSelect.appendChild(opt);
                    sectionSelect.setAttribute('disabled', 'true');
                }
            } else {
                sectionSelect.setAttribute('disabled', 'true');
            }
            checkFormProgression();
        }

        function onInstituteChange() {
            const instituteSelect = document.getElementById('teacherInstitute');
            const positionSelect = document.getElementById('teacherPosition');
            const selectedInstitute = instituteSelect.value;

            positionSelect.value = "";
            for (let option of positionSelect.options) {
                option.disabled = false;
            }

            if (selectedInstitute && takenPositionsByInstitute[selectedInstitute]) {
                const taken = takenPositionsByInstitute[selectedInstitute];
                for (let option of positionSelect.options) {
                    if (taken.includes(option.value)) {
                        option.disabled = true;
                    }
                }
            }
            checkFormProgression();
        }

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

        function checkFormProgression() {
            const firstName = document.getElementById('firstName');
            const middleName = document.getElementById('middleName');
            const lastName = document.getElementById('lastName');
            const roleButtons = document.querySelectorAll('.role-btn');
            const idNumber = document.getElementById('idNumber');
            const emailInput = document.getElementById('emailInput');
            const passwordInput = document.getElementById('passwordInput');
            const role = document.getElementById('roleInput').value;
            
            const studentProgram = document.getElementById('studentProgram');
            const studentYear = document.getElementById('studentYear');
            const studentSection = document.getElementById('studentSection');
            
            const teacherInstitute = document.getElementById('teacherInstitute');
            const teacherPosition = document.getElementById('teacherPosition');
            const submitBtn = document.getElementById('submitBtn');

            if (firstName.value.trim() !== "") {
                middleName.removeAttribute('disabled');
            } else {
                middleName.setAttribute('disabled', 'true');
                middleName.value = "";
            }

            if (middleName.value.trim() !== "") {
                lastName.removeAttribute('disabled');
            } else {
                lastName.setAttribute('disabled', 'true');
                lastName.value = "";
            }

            if (lastName.value.trim() !== "") {
                roleButtons.forEach(btn => btn.removeAttribute('disabled'));
            } else {
                roleButtons.forEach(btn => btn.setAttribute('disabled', 'true'));
            }

            if (lastName.value.trim() !== "") {
                idNumber.removeAttribute('disabled');
            } else {
                idNumber.setAttribute('disabled', 'true');
                idNumber.value = "";
            }

            if (idNumber.value.trim() !== "") {
                emailInput.removeAttribute('disabled');
            } else {
                emailInput.setAttribute('disabled', 'true');
                emailInput.value = "";
            }

            if (emailInput.value.trim() !== "") {
                passwordInput.removeAttribute('disabled');
            } else {
                passwordInput.setAttribute('disabled', 'true');
                passwordInput.value = "";
            }

            let roleFieldsComplete = true;
            if (passwordInput.value.trim() !== "") {
                if (role === 'student') {
                    studentProgram.removeAttribute('disabled');
                    teacherInstitute.setAttribute('disabled', 'true');
                    teacherPosition.setAttribute('disabled', 'true');
                    if (studentProgram.value === "" || studentYear.value === "" || studentSection.value === "") {
                        roleFieldsComplete = false;
                    }
                } else if (role === 'faculty') {
                    teacherInstitute.removeAttribute('disabled');
                    teacherPosition.removeAttribute('disabled');
                    studentProgram.setAttribute('disabled', 'true');
                    studentYear.setAttribute('disabled', 'true');
                    studentSection.setAttribute('disabled', 'true');
                    if (teacherInstitute.value === "" || teacherPosition.value === "") {
                        roleFieldsComplete = false;
                    }
                } else {
                    teacherInstitute.setAttribute('disabled', 'true');
                    teacherPosition.setAttribute('disabled', 'true');
                    studentProgram.setAttribute('disabled', 'true');
                    studentYear.setAttribute('disabled', 'true');
                    studentSection.setAttribute('disabled', 'true');
                }
            } else {
                if (studentProgram) {
                    studentProgram.setAttribute('disabled', 'true');
                    studentProgram.value = "";
                }
                if (studentYear) {
                    studentYear.setAttribute('disabled', 'true');
                    studentYear.value = "";
                }
                if (studentSection) {
                    studentSection.setAttribute('disabled', 'true');
                    studentSection.innerHTML = '<option value="">Select Section</option>';
                }
                if (teacherInstitute) {
                    teacherInstitute.setAttribute('disabled', 'true');
                    teacherInstitute.value = "";
                }
                if (teacherPosition) {
                    teacherPosition.setAttribute('disabled', 'true');
                    teacherPosition.value = "";
                }
                roleFieldsComplete = false;
            }

            if (
                firstName.value.trim() !== "" &&
                middleName.value.trim() !== "" &&
                lastName.value.trim() !== "" &&
                idNumber.value.trim() !== "" &&
                emailInput.value.trim() !== "" &&
                passwordInput.value.trim() !== "" &&
                roleFieldsComplete
            ) {
                submitBtn.removeAttribute('disabled');
            } else {
                submitBtn.setAttribute('disabled', 'true');
            }
        }
    </script>
</body>
</html>
