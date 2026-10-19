<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'faculty') {
    header("Location: login.php");
    exit();
}

$teacher_id = $_SESSION['user_id'];
$response_msg = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['qr_code'], $_POST['subject_id'])) {
    $qr_code = trim($_POST['qr_code']);
    $subject_id = intval($_POST['subject_id']);

    // Hanapin ang student gamit ang ID number o QR data
    $stmt_stu = $conn->prepare("SELECT id FROM users WHERE id_number = ? AND role = 'student'");
    $stmt_stu->bind_param("s", $qr_code);
    $stmt_stu->execute();
    $res_stu = $stmt_stu->get_result();

    if ($res_stu->num_rows > 0) {
        $student = $res_stu->fetch_assoc();
        $student_id = $student['id'];

        // I-record ang attendance transaction
        $status = "Present"; 
        $stmt_ins = $conn->prepare("INSERT INTO attendance (student_id, subject_id, scanned_at, status) VALUES (?, ?, NOW(), ?)");
        $stmt_ins->bind_param("iis", $student_id, $subject_id, $status);
        if ($stmt_ins->execute()) {
            $response_msg = "Attendance recorded successfully.";
        } else {
            $response_msg = "An error occurred while saving the record.";
        }
        $stmt_ins->close();
    } else {
        $response_msg = "The QR code or student ID was not recognized.";
    }
    $stmt_stu->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Transaction - Scan QR Attendance</title>
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
</head>
<body style="padding: 40px; display: flex; justify-content: center;">
    <div style="background: #fff; padding: 30px; border-radius: 20px; width: 100%; max-width: 500px; box-shadow: 0 10px 30px rgba(0,0,0,0.05);">
        <h3 style="color: #108a00; margin-bottom: 15px;">QR Attendance Transaction</h3>
        <?php if(!empty($response_msg)) echo "<p style='color: #108a00; font-weight: bold; margin-bottom: 15px;'>{$response_msg}</p>"; ?>
        <form action="scan_qr.php" method="POST">
            <div style="margin-bottom: 15px;">
                <label style="font-size: 13px; font-weight: bold;">Select a Subject ID:</label>
                <input type="number" name="subject_id" class="form-input" required placeholder="Subject ID">
            </div>
            <div style="margin-bottom: 15px;">
                <label style="font-size: 13px; font-weight: bold;">Scan Student QR Code / ID Number:</label>
                <input type="text" name="qr_code" class="form-input" required autofocus placeholder="Scan here...">
            </div>
            <button type="submit" class="btn-enter" style="width: 100%; padding: 12px;">Record Transaction</button>
        </form>
    </div>
</body>
</html>