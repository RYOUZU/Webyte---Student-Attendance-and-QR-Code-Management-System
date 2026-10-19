<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$admin_id = $_SESSION['user_id'];

// Handle Add / Delete Institute actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_institute'])) {
        $institute_name = trim($_POST['institute_name']);
        if (!empty($institute_name)) {
            $stmt = $conn->prepare("INSERT INTO institutes (institute_name) VALUES (?)");
            $stmt->bind_param("s", $institute_name);
            $stmt->execute();
            $stmt->close();
        }
        header("Location: manage_teachers.php");
        exit();
    }
    
    if (isset($_POST['delete_institute'])) {
        $institute_id = intval($_POST['institute_id']);
        $stmt = $conn->prepare("DELETE FROM institutes WHERE id = ?");
        $stmt->bind_param("i", $institute_id);
        $stmt->execute();
        $stmt->close();
        header("Location: manage_teachers.php");
        exit();
    }
}

// Kunin ang listahan ng mga Instituto
$institutes_result = $conn->query("SELECT * FROM institutes");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Institutes - KLD Attendance</title>
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
            margin-bottom: 20px;
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

        .add-form-container {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
        }

        .add-form-container input {
            flex: 1;
            padding: 12px 16px;
            border: 1px solid #ccc;
            border-radius: 10px;
            font-size: 14px;
            outline: none;
            transition: border-color 0.2s;
        }

        .add-form-container input:focus {
            border-color: #108a00;
        }

        .btn-add {
            background-color: #108a00;
            color: #fff;
            border: none;
            padding: 12px 24px;
            border-radius: 10px;
            font-weight: 700;
            cursor: pointer;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: background 0.2s;
        }

        .btn-add:hover {
            background-color: #0d6d00;
        }

        .cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
        }

        .program-card {
            background: #ffffff;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.06);
            border: 1px solid #eef2f0;
            display: flex;
            flex-direction: column;
            position: relative;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .program-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 25px rgba(0, 128, 0, 0.15);
        }

        .program-card-banner {
            background: linear-gradient(135deg, #108a00 0%, #0d6d00 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            min-height: 90px;
        }

        .program-card-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: #e8f5e9;
            color: #108a00;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            border: 3px solid #ffffff;
            box-shadow: 0 4px 10px rgba(0,0,0,0.15);
        }

        .program-card-body {
            padding: 20px;
            display: flex;
            flex-direction: column;
            gap: 15px;
            text-align: center;
            flex: 1;
            justify-content: space-between;
        }

        .program-card-body h4 {
            margin: 0;
            font-size: 16px;
            color: #1a331e;
            font-weight: 800;
        }

        .card-actions {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .btn-view {
            flex: 1;
            background: #f4fbf7;
            border: 1px solid #108a00;
            color: #108a00;
            padding: 8px 12px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            transition: all 0.2s;
        }

        .btn-view:hover {
            background: #108a00;
            color: #fff;
        }

        .btn-delete-icon {
            background: #fff5f5;
            border: 1px solid #feb2b2;
            color: #e53e3e;
            width: 35px;
            height: 35px;
            border-radius: 8px;
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
                <h3><i class="fa-solid fa-building-columns" style="color: #108a00;"></i> Manage Institutes</h3>
                <a href="admin_dashboard.php" class="btn-back">
                    <i class="fa-solid fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>

            <!-- Form para mag-add ng Institute -->
            <form action="manage_teachers.php" method="POST" class="add-form-container">
                <input type="text" name="institute_name" placeholder="Enter institute name (e.g., Institute of Computing Studies)" required>
                <button type="submit" name="add_institute" class="btn-add">
                    <i class="fa-solid fa-plus"></i> Add Institute
                </button>
            </form>

            <!-- Listahan ng mga Institute na naka-Grid Card layout -->
            <div class="cards-grid">
                <?php if ($institutes_result && $institutes_result->num_rows > 0): ?>
                    <?php while($inst = $institutes_result->fetch_assoc()): ?>
                        <div class="program-card">
                            <div class="program-card-banner">
                                <div class="program-card-icon">
                                    <i class="fa-solid fa-building-columns"></i>
                                </div>
                            </div>
                            <div class="program-card-body">
                                <h4><?php echo htmlspecialchars($inst['institute_name']); ?></h4>
                                <div class="card-actions">
                                    <!-- Pwedeng i-link sa susunod na pahina para sa mga guro ng institutong ito -->
                                    <a href="view_teachers.php?institute_id=<?php echo $inst['id']; ?>" class="btn-view">
                                        View Teachers <i class="fa-solid fa-chevron-right" style="font-size: 10px;"></i>
                                    </a>
                                    <form action="manage_teachers.php" method="POST" onsubmit="return confirm('Are you sure you want to delete this institute?');" style="margin:0;">
                                        <input type="hidden" name="institute_id" value="<?php echo $inst['id']; ?>">
                                        <button type="submit" name="delete_institute" class="btn-delete-icon" title="Delete Institute">
                                            <i class="fa-solid fa-trash-can"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p style="color: #666; font-size: 14px; grid-column: 1 / -1; text-align: center; padding: 20px;">No institutes have been registered yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </main>
</body>
</html>