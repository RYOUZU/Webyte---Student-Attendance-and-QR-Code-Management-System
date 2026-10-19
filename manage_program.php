<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$admin_id = $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT first_name, middle_name, last_name, role, id_number, email, profile_pic FROM users WHERE id = ?");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

$message = "";
$status = "";

$selected_program_id = isset($_GET['program_id']) ? intval($_GET['program_id']) : 0;
$filter_year = isset($_GET['year']) ? trim($_GET['year']) : '';

// 1. Add Program
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_program') {
    $program_name = trim($_POST['program_name']);
    if (!empty($program_name)) {
        $stmt_add = $conn->prepare("INSERT INTO programs (program_name) VALUES (?)");
        $stmt_add->bind_param("s", $program_name);
        if ($stmt_add->execute()) {
            $message = "Program successfully added!";
            $status = "success";
        } else {
            $message = "An error occurred. Please try again.";
            $status = "error";
        }
        $stmt_add->close();
    } else {
        $message = "Please do not leave the program name empty.";
        $status = "error";
    }
}

// 2. Add Section to a Program
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_section') {
    $program_id = intval($_POST['program_id']);
    $section_name = trim($_POST['section_name']);
    $year_level = trim($_POST['year_level']);
    if (!empty($section_name) && !empty($year_level) && $program_id > 0) {
        $stmt_add_sec = $conn->prepare("INSERT INTO sections (program_id, section_name, year_level) VALUES (?, ?, ?)");
        $stmt_add_sec->bind_param("iss", $program_id, $section_name, $year_level);
        if ($stmt_add_sec->execute()) {
            $message = "Section successfully added!";
            $status = "success";
        } else {
            $message = "An error occurred. Please try again.";
            $status = "error";
        }
        $stmt_add_sec->close();
    }
}

// 3. Delete Program
if (isset($_GET['delete_program'])) {
    $del_prog_id = intval($_GET['delete_program']);
    $stmt_del_p = $conn->prepare("DELETE FROM programs WHERE id = ?");
    $stmt_del_p->bind_param("i", $del_prog_id);
    $stmt_del_p->execute();
    $stmt_del_p->close();
    header("Location: manage_program.php?deleted=1");
    exit();
}

$programs_result = $conn->query("SELECT * FROM programs ORDER BY program_name ASC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Programs - KLD Attendance</title>
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
            transition: all 0.3s ease;
        }

        .section-header { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            margin-bottom: 25px; 
            border-bottom: 2px solid #f0f4f1;
            padding-bottom: 15px;
        }

        .section-header h3 { 
            font-size: 20px; 
            font-weight: 800; 
            color: #112918; 
            margin: 0; 
            display: flex; 
            align-items: center; 
            gap: 12px; 
            letter-spacing: -0.3px;
        }

        .section-header h3 i {
            background: #e8f8f0;
            padding: 10px;
            border-radius: 12px;
            color: #108a00;
        }

        .form-inline { 
            display: flex; 
            gap: 12px; 
            margin-bottom: 30px; 
            flex-wrap: wrap; 
            background: #f8faf9;
            padding: 18px;
            border-radius: 16px;
            border: 1px solid #e2e8e4;
        }

        .form-inline input { 
            flex: 1; 
            padding: 12px 18px; 
            border: 1.5px solid #cbd5e1; 
            border-radius: 12px; 
            font-size: 14px; 
            outline: none; 
            min-width: 220px; 
            background: #fff; 
            color: #1e293b;
            transition: all 0.2s ease;
        }

        .form-inline input:focus { 
            border-color: #108a00; 
            box-shadow: 0 0 0 4px rgba(16, 138, 0, 0.12); 
            background: #fff;
        }

        .form-inline button { 
            background: linear-gradient(135deg, #108a00 0%, #0d6d00 100%); 
            color: white; 
            border: none; 
            padding: 12px 24px; 
            border-radius: 12px; 
            cursor: pointer; 
            font-weight: 700; 
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 12px rgba(16, 138, 0, 0.2);
            transition: all 0.2s ease; 
        }

        .form-inline button:hover { 
            transform: translateY(-2px);
            box-shadow: 0 6px 18px rgba(16, 138, 0, 0.3); 
        }
        
        .dashboard-cards-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 22px;
            margin-top: 10px;
        }

        @media (max-width: 1024px) {
            .dashboard-cards-grid { grid-template-columns: repeat(2, 1fr); }
        }

        @media (max-width: 768px) {
            .dashboard-cards-grid { grid-template-columns: 1fr; }
        }

        .dash-card {
            background: #ffffff;
            border-radius: 18px;
            overflow: hidden;
            box-shadow: 0 6px 20px rgba(0,0,0,0.04);
            border: 1px solid #e2e8e4;
            display: flex;
            flex-direction: column;
            position: relative;
            transition: all 0.3s cubic-bezier(0.165, 0.84, 0.44, 1);
            cursor: pointer;
        }

        .dash-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 12px 30px rgba(16, 138, 0, 0.15);
            border-color: #108a00;
        }

        .dash-card-banner {
            background: linear-gradient(135deg, #108a00 0%, #084c00 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 30px;
            min-height: 120px;
            position: relative;
            overflow: hidden;
        }

        .dash-card-icon {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            background: #ffffff;
            color: #108a00;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 30px;
            border: 4px solid rgba(255, 255, 255, 0.9);
            box-shadow: 0 8px 20px rgba(0,0,0,0.2);
            overflow: hidden;
            z-index: 1;
        }

        .dash-card-icon img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
            border-radius: 50%;
        }

        .dash-card-body {
            padding: 24px 25px 15px 25px;
            display: flex;
            flex-direction: column;
            gap: 8px;
            text-align: left;
            flex-grow: 1;
        }

        .dash-card-body h4 {
            margin: 0;
            font-size: 17px;
            color: #112918;
            font-weight: 800;
            line-height: 1.4;
        }

        .dash-card-footer {
            padding: 0 25px 22px 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .btn-view-sections {
            background: #e8f8f0;
            color: #108a00;
            padding: 9px 16px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s ease;
        }

        .btn-view-sections:hover { 
            background: #108a00; 
            color: #fff;
        }

        .btn-delete { 
            background: #fee2e2; 
            color: #ef4444; 
            border: none; 
            width: 38px; 
            height: 38px; 
            border-radius: 10px; 
            cursor: pointer; 
            display: flex; 
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
        
        .alert { 
            padding: 14px 18px; 
            border-radius: 12px; 
            font-size: 14px; 
            margin-bottom: 22px; 
            font-weight: 600; 
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .alert.success { background: #e8f5e9; color: #1b5e20; border: 1px solid #c8e6c9; }
        .alert.error { background: #ffebee; color: #b71c1c; border: 1px solid #ffcdd2; }
        
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

        /* Modal Styles */
        .modal-overlay {
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(5px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            animation: fadeIn 0.25s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .modal-box {
            background: #ffffff;
            padding: 35px;
            border-radius: 24px;
            width: 100%;
            max-width: 420px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
            text-align: center;
            position: relative;
            animation: scaleUp 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            border: 1px solid rgba(255,255,255,0.8);
        }

        .modal-logo-container {
            width: 65px;
            height: 65px;
            background: #ffffff;
            border-radius: 50%;
            margin: 0 auto 18px auto;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 6px 16px rgba(16, 138, 0, 0.15);
            border: 3px solid #108a00;
            padding: 4px;
            box-sizing: border-box;
        }

        .modal-logo-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
        }

        .modal-box h3 {
            margin-top: 0;
            color: #112918;
            font-size: 22px;
            font-weight: 800;
            letter-spacing: -0.5px;
        }

        .modal-box p {
            color: #64748b;
            font-size: 14px;
            margin-bottom: 25px;
            line-height: 1.5;
        }

        .year-options {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .btn-year {
            background: #f8faf9;
            border: 1.5px solid #e2e8e4;
            padding: 14px;
            border-radius: 14px;
            font-weight: 700;
            color: #1e293b;
            text-decoration: none;
            transition: all 0.25s ease;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-left: 20px;
            padding-right: 20px;
            font-size: 15px;
        }

        .btn-year::after {
            content: '\f054';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            font-size: 12px;
            color: #94a3b8;
            transition: transform 0.2s ease, color 0.2s ease;
        }

        .btn-year:hover {
            background: linear-gradient(135deg, #108a00 0%, #0d6d00 100%);
            color: #ffffff;
            border-color: #108a00;
            box-shadow: 0 6px 16px rgba(16, 138, 0, 0.25);
            transform: translateY(-2px);
        }

        .btn-year:hover::after {
            color: #ffffff;
            transform: translateX(4px);
        }

        .modal-close {
            position: absolute;
            top: 20px; right: 20px;
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
            transition: all 0.2s;
        }

        .modal-close:hover { 
            background: #fee2e2; 
            color: #ef4444; 
            transform: rotate(90deg);
        }

        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        @keyframes scaleUp { from { transform: scale(0.9); opacity: 0; } to { transform: scale(1); opacity: 1; } }
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
                <h3><i class="fa-solid fa-graduation-cap"></i> Manage Academic Programs</h3>
            </div>

            <?php if (!empty($message)): ?>
                <div class="alert <?php echo $status; ?>"><i class="fa-solid fa-circle-info"></i> <?php echo $message; ?></div>
            <?php endif; ?>
            <?php if (isset($_GET['deleted'])): ?>
                <div class="alert success"><i class="fa-solid fa-circle-check"></i> Successfully deleted!</div>
            <?php endif; ?>

            <form action="manage_program.php" method="POST" class="form-inline">
                <input type="hidden" name="action" value="add_program">
                <input type="text" name="program_name" placeholder="Enter program name (e.g., Bachelor of Science in Information Systems)" required>
                <button type="submit"><i class="fa-solid fa-plus"></i> Add Program</button>
            </form>

            <div class="dashboard-cards-grid">
                <?php if ($programs_result && $programs_result->num_rows > 0): ?>
                    <?php while($prog = $programs_result->fetch_assoc()): ?>
                        <div class="dash-card" onclick="openYearModal(<?php echo $prog['id']; ?>, '<?php echo htmlspecialchars($prog['program_name'], ENT_QUOTES); ?>')">
                            <div class="dash-card-banner">
                                <div class="dash-card-icon">
                                    <img src="kld-logo.png" alt="KLD Logo">
                                </div>
                            </div>
                            <div class="dash-card-body">
                                <h4><?php echo htmlspecialchars($prog['program_name']); ?></h4>
                            </div>
                            <div class="dash-card-footer" onclick="event.stopPropagation();">
                                <button class="btn-view-sections" onclick="openYearModal(<?php echo $prog['id']; ?>, '<?php echo htmlspecialchars($prog['program_name'], ENT_QUOTES); ?>')">
                                    View Sections <i class="fa-solid fa-chevron-right"></i>
                                </button>
                                <a href="manage_program.php?delete_program=<?php echo $prog['id']; ?>" class="btn-delete" onclick="return confirm('Are you sure you want to delete this program? All sections under it will also be deleted.');" title="Delete Program">
                                    <i class="fa-solid fa-trash-can"></i>
                                </a>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p style="text-align: center; color: #666; padding: 40px; font-style: italic; grid-column: span 3;">No programs added yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- YEAR LEVEL POPUP MODAL -->
    <div class="modal-overlay" id="yearModal">
        <div class="modal-box">
            <button class="modal-close" onclick="closeYearModal()"><i class="fa-solid fa-xmark"></i></button>
            <div class="modal-logo-container">
                <img src="kld-logo.png" alt="KLD Logo">
            </div>
            <h3 id="modalProgramName">Select Year Level</h3>
            <p>Please select the year level you want to view for this program.</p>
            <div class="year-options">
                <a href="#" id="linkFirstYear" class="btn-year">First Year</a>
                <a href="#" id="linkSecondYear" class="btn-year">Second Year</a>
                <a href="#" id="linkThirdYear" class="btn-year">Third Year</a>
                <a href="#" id="linkFourthYear" class="btn-year">Fourth Year</a>
            </div>
        </div>
    </div>

    <script>
        function openYearModal(programId, programName) {
            document.getElementById('modalProgramName').innerText = programName;
            document.getElementById('linkFirstYear').href = 'view_sections.php?program_id=' + programId + '&year=First Year';
            document.getElementById('linkSecondYear').href = 'view_sections.php?program_id=' + programId + '&year=Second Year';
            document.getElementById('linkThirdYear').href = 'view_sections.php?program_id=' + programId + '&year=Third Year';
            document.getElementById('linkFourthYear').href = 'view_sections.php?program_id=' + programId + '&year=Fourth Year';
            
            document.getElementById('yearModal').style.display = 'flex';
        }

        function closeYearModal() {
            document.getElementById('yearModal').style.display = 'none';
        }

        window.onclick = function(event) {
            let modal = document.getElementById('yearModal');
            if (event.target == modal) {
                closeYearModal();
            }
        }
    </script>
</body>
</html>