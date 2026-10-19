<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$message = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_school_subject'])) {
    $subject_name = trim($_POST['subject_name']);
    if (!empty($subject_name)) {
        $stmt = $conn->prepare("INSERT INTO school_subjects (subject_name) VALUES (?) ON DUPLICATE KEY UPDATE subject_name = subject_name");
        $stmt->bind_param("s", $subject_name);
        if ($stmt->execute()) {
            $message = "Subject successfully added!";
        } else {
            $message = "Error adding subject.";
        }
        $stmt->close();
    }
}

// Handle deletion kung kinakailangan
if (isset($_GET['delete_id'])) {
    $del_id = intval($_GET['delete_id']);
    $stmt_del = $conn->prepare("DELETE FROM school_subjects WHERE id = ?");
    $stmt_del->bind_param("i", $del_id);
    $stmt_del->execute();
    $stmt_del->close();
    header("Location: admin_manage_subjects.php");
    exit();
}

$subjects_result = $conn->query("SELECT * FROM school_subjects ORDER BY subject_name ASC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage School Subjects - KLD Attendance</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
    <style>
        body { background: linear-gradient(135deg, #f0f7f4 0%, #e8f5e9 100%); font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 0; display: flex; min-height: 100vh; }
        .main-wrapper { flex: 1; padding: 20px 30px; display: flex; flex-direction: column; gap: 20px; box-sizing: border-box; }
        .styled-card { background: #fff; border-radius: 20px; padding: 25px 30px; box-shadow: 0 10px 30px rgba(0, 128, 0, 0.05); border: 1px solid rgba(16, 138, 0, 0.08); }
        .subject-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 15px; margin-top: 20px; }
        .subject-bar { background: #f4fbf7; border: 1px solid #108a00; border-radius: 12px; padding: 15px; display: flex; align-items: center; justify-content: space-between; }
        .subject-bar-left { display: flex; align-items: center; gap: 12px; }
        .subject-bar-left img { width: 35px; height: 35px; object-fit: contain; }
        .form-row { display: flex; gap: 10px; margin-top: 15px; }
        .form-input { flex: 1; padding: 10px 14px; border: 1px solid #ccc; border-radius: 8px; font-size: 14px; }
        .btn-enter { background: #108a00; color: #fff; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; }
    </style>
</head>
<body>
    <main class="main-wrapper">
        <div class="styled-card">
            <h3><i class="fa-solid fa-book" style="color: #108a00;"></i> Manage Offered School Subjects</h3>
            <?php if(!empty($message)) echo "<p style='color:green; font-size:13px;'>$message</p>"; ?>
            
            <form method="POST" class="form-row">
                <input type="text" name="subject_name" class="form-input" placeholder="Enter new school subject (e.g. Data Structures)" required>
                <button type="submit" name="add_school_subject" class="btn-enter">Add Subject</button>
            </form>

            <div class="subject-grid">
                <?php if ($subjects_result && $subjects_result->num_rows > 0): ?>
                    <?php while ($row = $subjects_result->fetch_assoc()): ?>
                        <div class="subject-bar">
                            <div class="subject-bar-left">
                                <img src="kld-logo.png" alt="KLD Logo">
                                <strong style="color: #1a331e; font-size: 14px;"><?php echo htmlspecialchars($row['subject_name']); ?></strong>
                            </div>
                            <a href="admin_manage_subjects.php?delete_id=<?php echo $row['id']; ?>" onclick="return confirm('Are you sure you want to delete this subject?');" style="color: #dc3545;"><i class="fa-solid fa-trash"></i></a>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p style="color: #666; grid-column: 1/-1;">The administrator has not added any subjects yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </main>
</body>
</html>