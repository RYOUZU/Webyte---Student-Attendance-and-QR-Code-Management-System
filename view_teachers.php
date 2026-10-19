<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$institute_id = isset($_GET['institute_id']) ? intval($_GET['institute_id']) : 0;

$inst_stmt = $conn->prepare("SELECT institute_name FROM institutes WHERE id = ?");
$inst_stmt->bind_param("i", $institute_id);
$inst_stmt->execute();
$inst_result = $inst_stmt->get_result();
$institute = $inst_result->fetch_assoc();
$inst_stmt->close();

if (!$institute) {
    header("Location: manage_teachers.php");
    exit();
}

$institute_name = $institute['institute_name'];

// Fetch all available institutes for the context menu transfer options
$all_institutes_result = $conn->query("SELECT institute_name FROM institutes");
$available_institutes = [];
while ($inst_row = $all_institutes_result->fetch_assoc()) {
    $available_institutes[] = $inst_row['institute_name'];
}

$available_positions = [
    'Institute Dean',
    'Institute Associate Dean',
    'Associate Professor I',
    'Associate Professor II',
    'Associate Professor III',
    'Assistant Professor I',
    'Assistant Professor II',
    'Assistant Professor III'
];

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_teacher'])) {
        $teacher_id = intval($_POST['teacher_id']);
        $sub_soft = $conn->prepare("UPDATE teacher_subjects SET is_deleted=1, deleted_at=NOW() WHERE teacher_id=? AND COALESCE(is_deleted,0)=0");
        if ($sub_soft) { $sub_soft->bind_param("i", $teacher_id); $sub_soft->execute(); $sub_soft->close(); }
        $load_soft = $conn->prepare("UPDATE teacher_loads SET is_deleted=1, deleted_at=NOW() WHERE teacher_id=? AND COALESCE(is_deleted,0)=0");
        if ($load_soft) { $load_soft->bind_param("i", $teacher_id); $load_soft->execute(); $load_soft->close(); }
        $del_stmt = $conn->prepare("UPDATE users SET is_deleted = 1, deleted_at = NOW() WHERE id = ? AND role = 'faculty'");
        $del_stmt->bind_param("i", $teacher_id);
        $del_stmt->execute();
        $del_stmt->close();
        header("Location: view_teachers.php?institute_id=" . $institute_id);
        exit();
    } elseif (isset($_POST['update_teacher_placement'])) {
        $teacher_id = intval($_POST['teacher_id']);
        $new_institute = trim($_POST['new_institute']);
        $new_position = trim($_POST['new_position']);

        if (!empty($new_institute) && !empty($new_position)) {
            // Check if position is Institute Dean or Institute Associate Dean
            if ($new_position === 'Institute Dean' || $new_position === 'Institute Associate Dean') {
                // Check if someone else already holds this position in the target institute
                $check_pos = $conn->prepare("SELECT id FROM users WHERE role = 'faculty' AND COALESCE(is_deleted,0)=0 AND institute = ? AND position = ? AND id != ?");
                $check_pos->bind_param("ssi", $new_institute, $new_position, $teacher_id);
                $check_pos->execute();
                $check_pos->store_result();

                if ($check_pos->num_rows > 0) {
                    $error_message = "Error: The position of " . $new_position . " is already occupied in " . $new_institute . ". Only one teacher can hold this position.";
                }
                $check_pos->close();
            }

            if (empty($error_message)) {
                $upd_stmt = $conn->prepare("UPDATE users SET institute = ?, position = ? WHERE id = ? AND role = 'faculty'");
                $upd_stmt->bind_param("ssi", $new_institute, $new_position, $teacher_id);
                $upd_stmt->execute();
                $upd_stmt->close();
                header("Location: view_teachers.php?institute_id=" . $institute_id);
                exit();
            }
        }
    }
}

// Added 'institute' to the SELECT query columns
$teachers_stmt = $conn->prepare("SELECT id, first_name, middle_name, last_name, position, email, profile_pic, institute FROM users WHERE role = 'faculty' AND COALESCE(is_deleted,0)=0 AND institute = ?");
$teachers_stmt->bind_param("s", $institute_name);
$teachers_stmt->execute();
$teachers_result = $teachers_stmt->get_result();

$grouped_teachers = [
    'Institute Dean' => [],
    'Institute Associate Dean' => [],
    'Associate Professor I' => [],
    'Associate Professor II' => [],
    'Associate Professor III' => [],
    'Assistant Professor I' => [],
    'Assistant Professor II' => [],
    'Assistant Professor III' => []
];

while ($t = $teachers_result->fetch_assoc()) {
    $pos = $t['position'];
    if (array_key_exists($pos, $grouped_teachers)) {
        $grouped_teachers[$pos][] = $t;
    }
}
$teachers_stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teachers in <?php echo htmlspecialchars($institute_name); ?> - KLD Attendance</title>
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
            padding: 20px 30px;
            display: flex;
            flex-direction: column;
            gap: 20px;
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

        .section-header-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }

        .section-header-top h3 {
            margin: 0;
            color: #1a331e;
            font-size: 20px;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .btn-back {
            background: #ffffff;
            border: 1px solid #108a00;
            color: #108a00;
            padding: 6px 14px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
        }

        .btn-back:hover {
            background: #108a00;
            color: #fff;
        }

        .alert-error {
            background: #fff5f5;
            border: 1px solid #feb2b2;
            color: #c53030;
            padding: 12px 16px;
            border-radius: 10px;
            font-size: 13.5px;
            font-weight: 600;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
        }

        .alert-content {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-close {
            background: none;
            border: none;
            color: #c53030;
            font-size: 18px;
            font-weight: bold;
            cursor: pointer;
            padding: 0;
            line-height: 1;
        }

        .alert-close:hover {
            color: #9b2c2c;
        }

        .hierarchy-container {
            display: flex;
            flex-direction: column;
            gap: 30px;
        }

        .tier-section {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .tier-title {
            font-size: 15px;
            font-weight: 700;
            color: #108a00;
            border-bottom: 2px solid #e8f5e9;
            padding-bottom: 5px;
            margin: 0;
        }

        .tier-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            gap: 15px;
        }

        .program-card {
            background: #ffffff;
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            border: 1px solid #eef2f0;
            display: flex;
            flex-direction: column;
            user-select: none;
            cursor: pointer;
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }

        .program-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(0, 128, 0, 0.1);
        }

        .program-card-banner {
            background: linear-gradient(135deg, #108a00 0%, #0d6d00 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 15px;
            min-height: 70px;
        }

        .teacher-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #ffffff;
            box-shadow: 0 4px 10px rgba(0,0,0,0.15);
            background: #fff;
        }

        .program-card-body {
            padding: 15px;
            display: flex;
            flex-direction: column;
            gap: 8px;
            text-align: center;
            flex: 1;
            justify-content: space-between;
        }

        .program-card-body h4 {
            margin: 0;
            font-size: 15px;
            color: #1a331e;
            font-weight: 800;
        }

        .teacher-email {
            font-size: 11.5px;
            color: #4f5d52;
            font-weight: 600;
            background: #e8f5e9;
            padding: 3px 8px;
            border-radius: 20px;
            display: inline-block;
            margin: 0 auto;
            word-break: break-all;
        }

        .card-actions {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-top: 4px;
        }

        .btn-delete-icon {
            background: #fff5f5;
            border: 1px solid #feb2b2;
            color: #e53e3e;
            width: 32px;
            height: 32px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-delete-icon:hover {
            background: #e53e3e;
            color: #fff;
        }
        
        .empty-tier {
            font-size: 13px;
            color: #888;
            font-style: italic;
            margin: 0;
        }

        /* Custom Right-Click Context Menu */
        #customContextMenu {
            display: none;
            position: absolute;
            z-index: 9999;
            background: #ffffff;
            border: 1px solid #dcdcdc;
            border-radius: 10px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.12);
            width: 240px;
            padding: 6px 0;
            font-size: 13px;
        }

        .context-menu-header {
            padding: 6px 14px;
            font-weight: 700;
            color: #1a331e;
            border-bottom: 1px solid #eee;
            font-size: 12px;
            background: #f8faf9;
        }

        .context-menu-item {
            padding: 10px 14px;
            color: #333;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: background 0.15s;
        }

        .context-menu-item:hover {
            background: #e8f5e9;
            color: #108a00;
        }

        .context-menu-item i {
            width: 16px;
            text-align: center;
            color: #108a00;
        }

        /* Transfer Modal Styling */
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
            z-index: 10000;
        }

        .modal-content {
            background: #ffffff;
            border-radius: 20px;
            padding: 25px 30px;
            width: 90%;
            max-width: 420px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.2);
            position: relative;
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
            line-height: 1;
        }

        .close-modal:hover {
            color: #333;
        }

        .form-group {
            margin-bottom: 15px;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .form-group label {
            font-size: 13px;
            font-weight: 700;
            color: #1a331e;
        }

        .form-select {
            padding: 10px 12px;
            border: 1px solid #ccc;
            border-radius: 8px;
            font-size: 14px;
            outline: none;
            background: #fff;
            transition: border-color 0.2s;
        }

        .form-select:focus {
            border-color: #108a00;
        }

        .btn-submit-modal {
            background-color: #108a00;
            color: #ffffff;
            border: none;
            padding: 10px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            width: 100%;
            margin-top: 10px;
            transition: background 0.2s;
        }

        .btn-submit-modal:hover {
            background-color: #0d6d00;
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
        <div class="styled-card">
            <div class="section-header-top">
                <h3><i class="fa-solid fa-chalkboard-user" style="color: #108a00;"></i> Teachers - <?php echo htmlspecialchars($institute_name); ?></h3>
                <a href="manage_teachers.php" class="btn-back">
                    <i class="fa-solid fa-arrow-left"></i> Back to Institutes
                </a>
            </div>

            <?php if (!empty($error_message)): ?>
                <div class="alert-error" id="errorAlertBox">
                    <div class="alert-content">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                        <div><?php echo htmlspecialchars($error_message); ?></div>
                    </div>
                    <button type="button" class="alert-close" onclick="closeAlertBox()">&times;</button>
                </div>
            <?php endif; ?>

            <div class="hierarchy-container">
                <?php 
                $tiers = [
                    'Institute Dean' => 'Institute Dean',
                    'Institute Associate Dean' => 'Institute Associate Dean',
                    'Associate Professor I' => 'Associate Professor I',
                    'Associate Professor II' => 'Associate Professor II',
                    'Associate Professor III' => 'Associate Professor III',
                    'Assistant Professor I' => 'Assistant Professor I',
                    'Assistant Professor II' => 'Assistant Professor II',
                    'Assistant Professor III' => 'Assistant Professor III'
                ];

                foreach ($tiers as $key => $title): 
                ?>
                    <div class="tier-section">
                        <h4 class="tier-title"><?php echo $title; ?></h4>
                        <?php if (!empty($grouped_teachers[$key])): ?>
                            <div class="tier-grid">
                                <?php foreach ($grouped_teachers[$key] as $t): ?>
                                    <?php 
                                        $fullname = trim($t['first_name'] . ' ' . (!empty($t['middle_name']) ? $t['middle_name'][0] . '. ' : '') . $t['last_name']);
                                        $pic = !empty($t['profile_pic']) ? $t['profile_pic'] : 'kld-logo.png';
                                        $email = !empty($t['email']) ? $t['email'] : 'No Email';

                                        // Fetch teacher loads with IDs from teacher_subjects
                                        $load_query = $conn->prepare("SELECT id, section_name, subject_name FROM teacher_subjects WHERE teacher_id = ?");
                                        $load_query->bind_param("i", $t['id']);
                                        $load_query->execute();
                                        $load_res = $load_query->get_result();
                                        $loads = [];
                                        while($l = $load_res->fetch_assoc()) { 
                                            $loads[] = $l; 
                                        }
                                        $load_query->close();
                                    ?>
                                    <div class="program-card" 
                                         data-id="<?php echo $t['id']; ?>"
                                         data-name="<?php echo htmlspecialchars($fullname, ENT_QUOTES); ?>"
                                         data-institute="<?php echo htmlspecialchars($t['institute'], ENT_QUOTES); ?>"
                                         data-position="<?php echo htmlspecialchars($t['position'], ENT_QUOTES); ?>"
                                         data-loads='<?php echo htmlspecialchars(json_encode($loads), ENT_QUOTES, 'UTF-8'); ?>'
                                         onclick="openTeacherLoadModal(this)"
                                         oncontextmenu="openTeacherContextMenu(event, this)">
                                        <div class="program-card-banner">
                                            <img src="<?php echo htmlspecialchars($pic); ?>" alt="Teacher Profile" class="teacher-avatar">
                                        </div>
                                        <div class="program-card-body">
                                            <div>
                                                <h4><?php echo htmlspecialchars($fullname); ?></h4>
                                                <div style="margin-top: 6px;">
                                                    <span class="teacher-email"><?php echo htmlspecialchars($email); ?></span>
                                                </div>
                                            </div>
                                            <div class="card-actions">
                                                <form action="view_teachers.php?institute_id=<?php echo $institute_id; ?>" method="POST" onsubmit="return confirm('Are you sure you want to delete this teacher?');" style="margin:0;" onclick="event.stopPropagation()">
                                                    <input type="hidden" name="teacher_id" value="<?php echo $t['id']; ?>">
                                                    <button type="submit" name="delete_teacher" class="btn-delete-icon" title="Remove Teacher">
                                                        <i class="fa-solid fa-trash-can"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="empty-tier">No registered <?php echo strtolower($title); ?> yet.</p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </main>

    <!-- Custom Right-Click Context Menu -->
    <div id="customContextMenu">
        <div class="context-menu-header" id="contextMenuTeacherName">Teacher Options</div>
        <div class="context-menu-item" onclick="openTransferModal()">
            <i class="fa-solid fa-right-left"></i> Transfer Institute / Position
        </div>
    </div>

    <!-- Subjects & Sections Popup Modal (Strictly Subjects Only, Sections on click redirect to Records View) -->
    <div id="teacherLoadModal" class="modal-overlay" onclick="closeTeacherLoadModal(event)">
        <div class="modal-content" onclick="event.stopPropagation()" style="max-width: 650px; width: 95%; padding: 32px; border-radius: 24px;">
            <div class="modal-header" style="border-bottom: 2px solid #e8f5e9; padding-bottom: 16px; margin-bottom: 24px;">
                <div>
                    <h4 id="modalLoadTitle" style="font-size: 20px; color: #1a331e; display: flex; align-items: center; gap: 10px;">
                        <i class="fa-solid fa-book-open-reader" style="color: #108a00;"></i> Teacher Subjects
                    </h4>
                    <p style="margin: 4px 0 0 0; font-size: 13px; color: #666;">Click on a subject to reveal its handled sections and view attendance records.</p>
                </div>
                <span class="close-modal" onclick="closeTeacherLoadModal()" style="font-size: 28px; color: #888; transition: color 0.2s;">&times;</span>
            </div>
            <div style="max-height: 500px; overflow-y: auto; padding-right: 6px;">
                <div id="modalLoadContent" style="display: flex; flex-direction: column; gap: 14px;">
                    <!-- Dynamic Content -->
                </div>
            </div>
        </div>
    </div>

    <!-- Transfer / Update Placement Modal -->
    <div id="transferModal" class="modal-overlay" onclick="closeTransferModal(event)">
        <div class="modal-content" onclick="event.stopPropagation()">
            <div class="modal-header">
                <h4>Transfer Teacher</h4>
                <span class="close-modal" onclick="closeTransferModal()">&times;</span>
            </div>
            <form action="view_teachers.php?institute_id=<?php echo $institute_id; ?>" method="POST">
                <input type="hidden" name="teacher_id" id="modalTeacherId">
                <div class="form-group">
                    <label>Select Institute:</label>
                    <select name="new_institute" id="modalInstituteSelect" class="form-select" required>
                        <?php foreach ($available_institutes as $inst_opt): ?>
                            <option value="<?php echo htmlspecialchars($inst_opt); ?>"><?php echo htmlspecialchars($inst_opt); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Select Position:</label>
                    <select name="new_position" id="modalPositionSelect" class="form-select" required>
                        <?php foreach ($available_positions as $pos_opt): ?>
                            <option value="<?php echo htmlspecialchars($pos_opt); ?>"><?php echo htmlspecialchars($pos_opt); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" name="update_teacher_placement" class="btn-submit-modal">Save Changes</button>
            </form>
        </div>
    </div>

    <script>
        let selectedTeacherId = null;
        let selectedTeacherInstitute = '';
        let selectedTeacherPosition = '';

        function openTeacherContextMenu(event, cardElement) {
            event.preventDefault();
            selectedTeacherId = cardElement.dataset.id;
            selectedTeacherInstitute = cardElement.dataset.institute;
            selectedTeacherPosition = cardElement.dataset.position;

            document.getElementById('contextMenuTeacherName').innerText = cardElement.dataset.name;

            const menu = document.getElementById('customContextMenu');
            menu.style.display = 'block';
            menu.style.left = `${event.pageX}px`;
            menu.style.top = `${event.pageY}px`;
        }

        window.addEventListener('click', function() {
            document.getElementById('customContextMenu').style.display = 'none';
        });

        function openTeacherLoadModal(cardElement) {
            const teacherName = cardElement.dataset.name;
            const rawLoads = cardElement.dataset.loads;
            
            document.getElementById('modalLoadTitle').innerHTML = `<i class="fa-solid fa-book-open-reader" style="color: #108a00;"></i> Subjects for ${teacherName}`;
            
            const contentContainer = document.getElementById('modalLoadContent');
            contentContainer.innerHTML = ''; 
            
            try {
                const loads = JSON.parse(rawLoads);
                if (loads.length > 0) {
                    // Group sections by subject_name, storing both section_name and teacher_subject id (for class_record.php?id=...)
                    const subjectMap = {};
                    loads.forEach(item => {
                        if (!subjectMap[item.subject_name]) {
                            subjectMap[item.subject_name] = [];
                        }
                        subjectMap[item.subject_name].push({
                            id: item.id,
                            section_name: item.section_name
                        });
                    });

                    // Render subjects list where clicking expands to show sections
                    Object.keys(subjectMap).forEach(subjectName => {
                        const sections = subjectMap[subjectName];

                        let subjectCard = document.createElement('div');
                        subjectCard.style.background = '#ffffff';
                        subjectCard.style.borderRadius = '16px';
                        subjectCard.style.border = '1px solid #e2e8f0';
                        subjectCard.style.boxShadow = '0 4px 15px rgba(0,0,0,0.03)';
                        subjectCard.style.overflow = 'hidden';
                        subjectCard.style.transition = 'all 0.2s ease';

                        // Card Header (Subject Name Only) - Clickable
                        let cardHeader = document.createElement('div');
                        cardHeader.style.padding = '18px 20px';
                        cardHeader.style.background = 'linear-gradient(135deg, #f8faf9 0%, #edf7ed 100%)';
                        cardHeader.style.display = 'flex';
                        cardHeader.style.justifyContent = 'space-between';
                        cardHeader.style.alignItems = 'center';
                        cardHeader.style.cursor = 'pointer';
                        cardHeader.style.borderBottom = '1px solid transparent';

                        cardHeader.innerHTML = `
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <div style="width: 38px; height: 38px; border-radius: 10px; background: #108a00; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 15px;">
                                    <i class="fa-solid fa-book"></i>
                                </div>
                                <div>
                                    <div style="font-size: 16px; font-weight: 800; color: #1a331e;">${subjectName}</div>
                                    <div style="font-size: 12px; font-weight: 600; color: #108a00; margin-top: 2px;">${sections.length} section(s) available</div>
                                </div>
                            </div>
                            <div style="width: 32px; height: 32px; border-radius: 50%; background: #fff; border: 1px solid #dcdcdc; display: flex; align-items: center; justify-content: center; color: #108a00; transition: transform 0.2s;">
                                <i class="fa-solid fa-chevron-down chevron-icon"></i>
                            </div>
                        `;

                        // Collapsible Body (Sections List - clicking a section opens class_record.php?id=...)
                        let cardBody = document.createElement('div');
                        cardBody.style.padding = '0 20px';
                        cardBody.style.maxHeight = '0';
                        cardBody.style.overflow = 'hidden';
                        cardBody.style.transition = 'max-height 0.3s ease, padding 0.3s ease';
                        cardBody.style.background = '#fff';

                        let sectionsInnerHtml = '<div style="padding: 15px 0; display: flex; flex-direction: column; gap: 10px; border-top: 1px dashed #e2e8f0;">';
                        sections.forEach(sec => {
                            sectionsInnerHtml += `
                                <a href="class_record.php?id=${sec.id}" style="text-decoration: none; display: flex; align-items: center; justify-content: space-between; padding: 12px 16px; background: #f8faf9; border-radius: 12px; border: 1px solid #e2e8f0; font-size: 14px; font-weight: 700; color: #1a331e; transition: all 0.2s ease;" onmouseover="this.style.background='#e8f5e9'; this.style.borderColor='#108a00'; this.style.color='#108a00';" onmouseout="this.style.background='#f8faf9'; this.style.borderColor='#e2e8f0'; this.style.color='#1a331e';">
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <i class="fa-solid fa-layer-group" style="color: #108a00; font-size: 13px;"></i>
                                        <span>${sec.section_name}</span>
                                    </div>
                                    <div style="display: flex; align-items: center; gap: 6px; font-size: 12.5px; color: #108a00; font-weight: 600;">
                                        <span>View Records</span>
                                        <i class="fa-solid fa-arrow-right" style="font-size: 11px;"></i>
                                    </div>
                                </a>
                            `;
                        });
                        sectionsInnerHtml += '</div>';
                        cardBody.innerHTML = sectionsInnerHtml;

                        // Toggle accordion function on click
                        cardHeader.addEventListener('click', () => {
                            const isOpen = cardBody.style.maxHeight !== '0px' && cardBody.style.maxHeight !== '';
                            const chevron = cardHeader.querySelector('.chevron-icon');
                            
                            if (isOpen) {
                                cardBody.style.maxHeight = '0px';
                                cardHeader.style.borderBottomColor = 'transparent';
                                chevron.style.transform = 'rotate(0deg)';
                            } else {
                                cardBody.style.maxHeight = cardBody.scrollHeight + 'px';
                                cardHeader.style.borderBottomColor = '#e2e8f0';
                                chevron.style.transform = 'rotate(180deg)';
                            }
                        });

                        subjectCard.appendChild(cardHeader);
                        subjectCard.appendChild(cardBody);
                        contentContainer.appendChild(subjectCard);
                    });
                } else {
                    contentContainer.innerHTML = `
                        <div style="text-align: center; padding: 40px 20px; color: #666;">
                            <i class="fa-solid fa-folder-open" style="font-size: 40px; color: #cbd5e0; margin-bottom: 12px;"></i>
                            <p style="font-size: 14px; font-style: italic; margin: 0;">No subjects or sections assigned yet.</p>
                        </div>
                    `;
                }
            } catch (e) {
                contentContainer.innerHTML = '<p style="color: #c53030; text-align: center;">Error loading data.</p>';
            }
            
            document.getElementById('teacherLoadModal').style.display = 'flex';
        }

        function closeTeacherLoadModal(event) {
            if (!event || event.target === document.getElementById('teacherLoadModal') || event.target.classList.contains('close-modal')) {
                document.getElementById('teacherLoadModal').style.display = 'none';
            }
        }

        function openTransferModal() {
            document.getElementById('customContextMenu').style.display = 'none';
            document.getElementById('modalTeacherId').value = selectedTeacherId;
            document.getElementById('modalInstituteSelect').value = selectedTeacherInstitute;
            document.getElementById('modalPositionSelect').value = selectedTeacherPosition;
            document.getElementById('transferModal').style.display = 'flex';
        }

        function closeTransferModal(event) {
            if (!event || event.target === document.getElementById('transferModal') || event.target.classList.contains('close-modal')) {
                document.getElementById('transferModal').style.display = 'none';
            }
        }

        function closeAlertBox() {
            const alertBox = document.getElementById('errorAlertBox');
            if (alertBox) {
                alertBox.style.display = 'none';
            }
        }
    </script>
</body>
</html>