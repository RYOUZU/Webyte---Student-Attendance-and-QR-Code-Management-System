<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$program_id = isset($_GET['program_id']) ? intval($_GET['program_id']) : 0;
$filter_year = isset($_GET['year']) ? trim($_GET['year']) : '';

if ($program_id <= 0 || empty($filter_year)) {
    header("Location: manage_program.php");
    exit();
}

// Fetch Program Details
$stmt_p = $conn->prepare("SELECT * FROM programs WHERE id = ?");
$stmt_p->bind_param("i", $program_id);
$stmt_p->execute();
$current_program = $stmt_p->get_result()->fetch_assoc();
$stmt_p->close();

if (!$current_program) {
    header("Location: manage_program.php");
    exit();
}

$error_message = "";

// 1. Add Section with Validations
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_section') {
    $section_name = trim($_POST['section_name']);
    $prog_name = trim($current_program['program_name']);

    if (empty($section_name)) {
        $error_message = "Please enter a section name.";
    } else {
        // Validation: Course / Program Match Check
        if (stripos($section_name, $prog_name) === false) {
            $error_message = "Invalid format! Section name must correspond to the current course (" . htmlspecialchars($prog_name) . ").";
        } else {
            // Validation: Duplication Check
            $stmt_check = $conn->prepare("SELECT id FROM sections WHERE program_id = ? AND year_level = ? AND section_name = ?");
            $stmt_check->bind_param("iss", $program_id, $filter_year, $section_name);
            $stmt_check->execute();
            if ($stmt_check->get_result()->num_rows > 0) {
                $error_message = "This section already exists (Duplication error).";
            }
            $stmt_check->close();

            if (empty($error_message)) {
                // Fetch existing sections to check numbering sequence
                $stmt_all = $conn->prepare("SELECT section_name FROM sections WHERE program_id = ? AND year_level = ? ORDER BY id ASC");
                $stmt_all->bind_param("is", $program_id, $filter_year);
                $stmt_all->execute();
                $res_all = $stmt_all->get_result();
                $existing_sections = [];
                while ($row = $res_all->fetch_assoc()) {
                    $existing_sections[] = $row['section_name'];
                }
                $stmt_all->close();

                // Extract number from new section name (e.g., trailing digits like 201, 202)
                preg_match('/(\d+)$/', $section_name, $matches);
                $new_num = isset($matches[1]) ? intval($matches[1]) : 0;

                // Determine expected prefix digit based on year level (First Year -> 1, Second Year -> 2, etc.)
                $expected_prefix = '';
                if (stripos($filter_year, 'First') !== false) $expected_prefix = '1';
                elseif (stripos($filter_year, 'Second') !== false) $expected_prefix = '2';
                elseif (stripos($filter_year, 'Third') !== false) $expected_prefix = '3';
                elseif (stripos($filter_year, 'Fourth') !== false) $expected_prefix = '4';

                if ($new_num === 0) {
                    $error_message = "Section name must end with a valid number (e.g., 201, 202).";
                } elseif ($expected_prefix !== '' && substr(strval($new_num), 0, 1) !== $expected_prefix) {
                    $error_message = "The section number must match the year level (e.g., starting with {$expected_prefix}01 for " . htmlspecialchars($filter_year) . ").";
                } else {
                    if (count($existing_sections) === 0) {
                        // First section must start with X01 (e.g., 201)
                        $base_num = intval($expected_prefix . '01');
                        if ($new_num !== $base_num) {
                            $error_message = "The first section for this year level must start with {$base_num}.";
                        }
                    } else {
                        // Find maximum existing number
                        $max_num = 0;
                        foreach ($existing_sections as $ex_sec) {
                            preg_match('/(\d+)$/', $ex_sec, $ex_matches);
                            if (isset($ex_matches[1])) {
                                $ex_n = intval($ex_matches[1]);
                                if ($ex_n > $max_num) $max_num = $ex_n;
                            }
                        }

                        // Validation: Sequential Numbering Check (No skipping numbers)
                        if ($new_num !== $max_num + 1) {
                            $error_message = "Sequential error: You cannot skip numbers. The next section number should be " . ($max_num + 1) . ".";
                        }
                    }
                }
            }

            if (empty($error_message)) {
                // Insert if all validations pass
                $stmt_add_sec = $conn->prepare("INSERT INTO sections (program_id, section_name, year_level) VALUES (?, ?, ?)");
                $stmt_add_sec->bind_param("iss", $program_id, $section_name, $filter_year);
                $stmt_add_sec->execute();
                $stmt_add_sec->close();
                
                header("Location: view_sections.php?program_id=" . $program_id . "&year=" . urlencode($filter_year));
                exit();
            }
        }
    }
}

// 2. Delete Section
if (isset($_GET['delete_section'])) {
    $del_sec_id = intval($_GET['delete_section']);
    $stmt_del_s = $conn->prepare("DELETE FROM sections WHERE id = ?");
    $stmt_del_s->bind_param("i", $del_sec_id);
    $stmt_del_s->execute();
    $stmt_del_s->close();
    
    header("Location: view_sections.php?program_id=" . $program_id . "&year=" . urlencode($filter_year));
    exit();
}

// Fetch Sections for this Program and Year Level
$stmt_s = $conn->prepare("SELECT * FROM sections WHERE program_id = ? AND year_level = ? ORDER BY section_name ASC");
$stmt_s->bind_param("is", $program_id, $filter_year);
$stmt_s->execute();
$sections_result = $stmt_s->get_result();
$stmt_s->close();
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

        /* Grid Cards Design */
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

        .alert.error { 
            background: #ffebee; 
            color: #b71c1c; 
            border: 1px solid #ffcdd2; 
            padding: 14px 18px; 
            border-radius: 12px; 
            font-size: 14px; 
            margin-bottom: 22px; 
            font-weight: 600; 
            display: flex; 
            align-items: center; 
            justify-content: space-between; /* Inilagay ang space para mapunta sa kanan ang X button */
            gap: 10px; 
        }

        .alert.error .error-content {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-close-btn {
            background: transparent;
            border: none;
            color: #b71c1c;
            cursor: pointer;
            font-size: 16px;
            padding: 4px 8px;
            border-radius: 6px;
            transition: background 0.2s ease;
        }

        .alert-close-btn:hover {
            background: rgba(183, 28, 28, 0.1);
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
            <a href="manage_program.php" class="back-btn"><i class="fa-solid fa-arrow-left"></i> Back to Programs</a>
        </div>

        <div class="styled-card">
            <div class="section-header">
                <h3>
                    <i class="fa-solid fa-layer-group"></i> Sections under: 
                    <span style="color: #108a00; font-weight: 800;">
                        <?php echo htmlspecialchars($current_program['program_name']); ?> (<?php echo htmlspecialchars($filter_year); ?>)
                    </span>
                </h3>
            </div>

            <!-- Error message na may functional close button sa kanan -->
            <?php if (!empty($error_message)): ?>
                <div class="alert error" id="errorAlert">
                    <div class="error-content">
                        <i class="fa-solid fa-circle-xmark"></i> 
                        <span><?php echo $error_message; ?></span>
                    </div>
                    <button type="button" class="alert-close-btn" onclick="document.getElementById('errorAlert').style.display='none';" title="Close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
            <?php endif; ?>

            <form action="view_sections.php?program_id=<?php echo $program_id; ?>&year=<?php echo urlencode($filter_year); ?>" method="POST" class="form-inline">
                <input type="hidden" name="action" value="add_section">
                <input type="text" name="section_name" placeholder="Section name (e.g., BSIS 204)" required>
                <button type="submit"><i class="fa-solid fa-plus"></i> Add Section</button>
            </form>

            <div class="dashboard-cards-grid">
                <?php if ($sections_result && $sections_result->num_rows > 0): ?>
                    <?php while($sec = $sections_result->fetch_assoc()): ?>
                        <div class="dash-card">
                            <div class="dash-card-banner">
                                <div class="dash-card-icon">
                                    <img src="kld-logo.png" alt="KLD Logo">
                                </div>
                            </div>
                            <div class="dash-card-body">
                                <h4><?php echo htmlspecialchars($sec['section_name']); ?></h4>
                            </div>
                            <div class="dash-card-footer">
                                <a href="view_class.php?section_id=<?php echo $sec['id']; ?>" class="btn-view-sections">
                                    View Class <i class="fa-solid fa-chevron-right"></i>
                                </a>
                                <a href="view_sections.php?program_id=<?php echo $program_id; ?>&year=<?php echo urlencode($filter_year); ?>&delete_section=<?php echo $sec['id']; ?>" class="btn-delete" onclick="return confirm('Are you sure you want to delete this section?');" title="Delete Section">
                                    <i class="fa-solid fa-trash-can"></i>
                                </a>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p style="text-align: center; color: #666; padding: 40px; font-style: italic; grid-column: span 3;">No sections added for this year level yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </main>
</body>
</html>