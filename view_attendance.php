<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit();
}

$student_id = $_SESSION['user_id'];
$subject_id = isset($_GET['subject_id']) ? intval($_GET['subject_id']) : 0;

$stmt_sub = $conn->prepare("
    SELECT ts.subject_name, ts.section_name, CONCAT(u.first_name, ' ', u.last_name) as teacher_name, u.profile_pic as teacher_pic 
    FROM teacher_subjects ts 
    JOIN users u ON ts.teacher_id = u.id 
    WHERE ts.id = ?
");
$stmt_sub->bind_param("i", $subject_id);
$stmt_sub->execute();
$subject = $stmt_sub->get_result()->fetch_assoc();
$stmt_sub->close();

if (!$subject) {
    header("Location: student_dashboard.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance History - <?php echo htmlspecialchars($subject['subject_name']); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
    <style>
        body {
            background-color: #f0f2f5;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 300px;
            background: #0d8a00;
            color: #ffffff;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 25px 20px;
            box-sizing: border-box;
            position: fixed;
            height: 100vh;
            left: 0;
            top: 0;
            overflow-y: auto;
        }

        .sidebar-top-content {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
        }

        .teacher-avatar {
            width: 85px;
            height: 85px;
            border-radius: 50%;
            margin-bottom: 12px;
            object-fit: cover;
            border: 3px solid rgba(255, 255, 255, 0.2);
            background: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #0d8a00;
            font-size: 35px;
        }

        .sidebar h3 {
            font-size: 16px;
            margin: 0 0 5px 0;
            font-weight: 700;
            line-height: 1.3;
        }

        .sidebar p {
            font-size: 12px;
            margin: 0 0 20px 0;
            opacity: 0.85;
            line-height: 1.4;
        }

        .sidebar-divider {
            width: 100%;
            height: 1px;
            background: rgba(255, 255, 255, 0.2);
            margin: 15px 0;
        }

        .btn-back-sidebar {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            background: rgba(255, 255, 255, 0.15);
            color: #ffffff;
            border: 1px solid rgba(255, 255, 255, 0.3);
            padding: 10px;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 600;
            font-size: 13px;
            transition: all 0.2s ease;
            width: 100%;
            box-sizing: border-box;
            margin-bottom: 8px;
        }

        .btn-back-sidebar:hover {
            background: #ffffff;
            color: #0d8a00;
        }

        .logout-link {
            color: #ffcccc;
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            font-size: 13px;
            padding: 8px 10px;
            justify-content: center;
            transition: opacity 0.2s;
        }

        .logout-link:hover {
            opacity: 0.8;
        }

        .main-content {
            margin-left: 300px;
            flex: 1;
            padding: 30px;
            box-sizing: border-box;
            max-width: calc(100% - 300px);
        }

        .styled-card {
            background: #ffffff;
            border-radius: 20px;
            padding: 25px 30px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.03);
            border: 1px solid #eef2f0;
            min-height: calc(100vh - 100px);
            box-sizing: border-box;
        }

        .table-container {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        th, td {
            padding: 14px 18px;
            text-align: left;
            border-bottom: 1px solid #f2f4f3;
            font-size: 14px;
        }

        th {
            background-color: #f8faf9;
            color: #0d8a00;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 11px;
            letter-spacing: 0.8px;
        }

        .badge-present { background: #d1e7dd; color: #0f5132; padding: 6px 14px; border-radius: 20px; font-weight: 600; font-size: 12px; display: inline-flex; align-items: center; gap: 6px; }
        .badge-late { background: #fff3cd; color: #664d03; padding: 6px 14px; border-radius: 20px; font-weight: 600; font-size: 12px; display: inline-flex; align-items: center; gap: 6px; }
        .badge-absent { background: #f8d7da; color: #842029; padding: 6px 14px; border-radius: 20px; font-weight: 600; font-size: 12px; display: inline-flex; align-items: center; gap: 6px; }
    </style>
</head>
<body>

    <!-- SIDEBAR -->
    <div class="sidebar">
        <div class="sidebar-top-content">
            <?php if (!empty($subject['teacher_pic']) && file_exists($subject['teacher_pic'])): ?>
                <img src="<?php echo htmlspecialchars($subject['teacher_pic']); ?>" alt="Teacher" class="teacher-avatar">
            <?php else: ?>
                <div class="teacher-avatar">
                    <i class="fa-solid fa-chalkboard-user"></i>
                </div>
            <?php endif; ?>

            <h3 style="font-size: 17px; margin-bottom: 4px;"><?php echo htmlspecialchars($subject['subject_name']); ?></h3>
            <p style="font-size: 12px; opacity: 0.9; margin-bottom: 15px;">
                <?php echo htmlspecialchars($subject['section_name']); ?>
            </p>

            <div style="background: rgba(255,255,255,0.12); padding: 10px 12px; border-radius: 10px; width: 100%; box-sizing: border-box; text-align: left;">
                <span style="font-size: 10px; text-transform: uppercase; opacity: 0.75; letter-spacing: 0.5px; display: block; margin-bottom: 2px;">Instructor</span>
                <strong style="font-size: 12px; display: block; line-height: 1.3;"><?php echo ucwords(strtolower(htmlspecialchars($subject['teacher_name']))); ?></strong>
            </div>
        </div>

        <div>
            <div class="sidebar-divider"></div>
            <a href="student_dashboard.php" class="btn-back-sidebar"><i class="fa-solid fa-arrow-left"></i> Bumalik sa Dashboard</a>
            <a href="logout.php" class="logout-link"><i class="fa-solid fa-right-from-bracket"></i> Log-out</a>
        </div>
    </div>

    <!-- MAIN CONTENT AREA -->
    <div class="main-content">
        <div class="styled-card">
            <h3 style="margin-top: 0; color: #0d8a00; font-size: 17px; display: flex; align-items: center; gap: 10px; margin-bottom: 20px;">
                <i class="fa-solid fa-clipboard-user"></i> Talaan ng Attendance History
            </h3>
            
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Petsa (Date)</th>
                            <th>QR Generation Time</th>
                            <th>Scan Time</th>
                            <th>Remarks (Status)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $stmt = $conn->prepare("
                            SELECT scanned_at, qr_generated_at, status 
                            FROM attendance 
                            WHERE student_id = ? AND subject_id = ?
                            ORDER BY scanned_at DESC
                        ");
                        $stmt->bind_param("ii", $student_id, $subject_id);
                        $stmt->execute();
                        $result = $stmt->get_result();

                        if ($result->num_rows > 0) {
                            while ($row = $result->fetch_assoc()) {
                                $scan_time = strtotime($row['scanned_at']);
                                $qr_gen_time = !empty($row['qr_generated_at']) ? strtotime($row['qr_generated_at']) : null;
                                
                                $date_formatted = date('F d, Y', $scan_time);
                                $time_formatted = date('h:i:s A', $scan_time);
                                $qr_gen_formatted = $qr_gen_time ? date('h:i:s A', $qr_gen_time) : 'N/A';

                                $status = trim($row['status']);
                                if (strcasecmp($status, 'Present') === 0) {
                                    $remark = '<span class="badge-present"><i class="fa-solid fa-circle-check"></i> Present</span>';
                                } elseif (strcasecmp($status, 'Late') === 0) {
                                    $remark = '<span class="badge-late"><i class="fa-solid fa-clock"></i> Late</span>';
                                } else {
                                    $remark = '<span class="badge-absent"><i class="fa-solid fa-circle-xmark"></i> ' . htmlspecialchars($status) . '</span>';
                                }

                                echo "<tr>
                                    <td>{$date_formatted}</td>
                                    <td>{$qr_gen_formatted}</td>
                                    <td>{$time_formatted}</td>
                                    <td>{$remark}</td>
                                </tr>";
                            }
                        } else {
                            echo "<tr><td colspan='4' style='text-align: center; color: #888; padding: 40px; font-style: italic;'>No attendance records are available for this subject.</td></tr>";
                        }
                        $stmt->close();
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</body>
</html>
