<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$admin_id = $_SESSION['user_id'];

// Kunin ang profile ng Admin para sa header/sidebar layout[cite: 4]
$stmt = $conn->prepare("SELECT full_name, role, id_number, email, profile_pic FROM users WHERE id = ?");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

$message = "";
$status = "";

// Pagdaragdag ng Section
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_section') {
    $section_name = trim($_POST['section_name']);
    
    if (!empty($section_name)) {
        $stmt_add = $conn->prepare("INSERT INTO sections (section_name) VALUES (?)");
        $stmt_add->bind_param("s", $section_name);
        if ($stmt_add->execute()) {
            $message = "Section added successfully.";
            $status = "success";
        } else {
            $message = "May naganap na error. Subukan muli.";
            $status = "error";
        }
        $stmt_add->close();
    } else {
        $message = "Huwag iwanang bakante ang pangalan ng seksyon.";
        $status = "error";
    }
}

// Pag-delete ng Section
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    $stmt_del = $conn->prepare("DELETE FROM sections WHERE id = ?");
    $stmt_del->bind_param("i", $delete_id);
    if ($stmt_del->execute()) {
        header("Location: " . basename($_SERVER['PHP_SELF']) . "?deleted=1");
        exit();
    }
    $stmt_del->close();
}

// Kunin ang listahan ng mga sections (bars) mula sa database
$sections_result = $conn->query("SELECT * FROM sections ORDER BY section_name ASC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Sections - KLD Attendance</title>
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

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .section-header h3 {
            font-size: 18px;
            font-weight: 800;
            color: #1a331e;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-inline {
            display: flex;
            gap: 10px;
            margin-bottom: 25px;
        }

        .form-inline input {
            flex: 1;
            padding: 10px 15px;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            font-size: 14px;
            outline: none;
        }

        .form-inline input:focus {
            border-color: #108a00;
            box-shadow: 0 0 0 3px rgba(16, 138, 0, 0.1);
        }

        .form-inline button {
            background: #108a00;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 700;
            transition: background 0.2s;
        }

        .form-inline button:hover {
            background: #0d6d00;
        }

        .sections-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .section-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #f8fafc;
            padding: 14px 20px;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            transition: all 0.2s;
        }

        .section-bar:hover {
            background: #e8f8f0;
            border-color: rgba(0, 200, 83, 0.3);
        }

        .section-name {
            font-weight: 700;
            color: #1e293b;
            font-size: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-delete {
            background: #fee2e2;
            color: #ef4444;
            border: none;
            width: 36px;
            height: 36px;
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
            text-decoration: none;
        }

        .btn-delete:hover {
            background: #ef4444;
            color: white;
        }

        .alert {
            padding: 12px 15px;
            border-radius: 10px;
            font-size: 13px;
            margin-bottom: 20px;
            font-weight: 600;
        }
        .alert.success { background: #e8f5e9; color: #2e7d32; }
        .alert.error { background: #ffebee; color: #c62828; }

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #108a00;
            text-decoration: none;
            font-weight: 700;
            font-size: 13px;
            margin-bottom: 5px;
            transition: opacity 0.2s;
        }

        .back-btn:hover {
            opacity: 0.8;
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
            <a href="admin_dashboard.php" class="back-btn"><i class="fa-solid fa-arrow-left"></i> Back to Dashboard</a>
        </div>

        <div class="styled-card">
            <div class="section-header">
                <h3><i class="fa-solid fa-layer-group" style="color: #108a00;"></i> Manage Student Sections</h3>
            </div>

            <?php if (!empty($message)): ?>
                <div class="alert <?php echo $status; ?>"><?php echo $message; ?></div>
            <?php endif; ?>
            <?php if (isset($_GET['deleted'])): ?>
                <div class="alert success">Section deleted successfully.</div>
            <?php endif; ?>

            <form action="" method="POST" class="form-inline">
                <input type="hidden" name="action" value="add_section">
                <input type="text" name="section_name" placeholder="Ilagay ang pangalan ng seksyon (e.g., BSIS 204)" required>
                <button type="submit"><i class="fa-solid fa-plus"></i> Magdagdag</button>
            </form>

            <div class="sections-list">
                <?php if ($sections_result && $sections_result->num_rows > 0): ?>
                    <?php while($row = $sections_result->fetch_assoc()): ?>
                        <div class="section-bar">
                            <span class="section-name"><i class="fa-solid fa-bookmark" style="color: #108a00;"></i> <?php echo htmlspecialchars($row['section_name']); ?></span>
                            <a href="?delete_id=<?php echo $row['id']; ?>" class="btn-delete" onclick="return confirm('Are you sure you want to delete this section?');" title="Delete Section">
                                <i class="fa-solid fa-trash-can"></i>
                            </a>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p style="text-align: center; color: #666; padding: 30px; font-style: italic;">No sections have been added yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </main>
</body>
</html>