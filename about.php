<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us - KLD Attendance System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        .clickable-group-text {
            color: #006b37;
            cursor: pointer;
            transition: color 0.2s ease;
        }
        .clickable-group-text:hover {
            color: #00c853;
        }

        /* Modal Design */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(5px);
            justify-content: center;
            align-items: center;
            z-index: 2000;
            padding: 15px;
            box-sizing: border-box;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .modal-card {
            background: #ffffff;
            width: 100%;
            max-width: 520px;
            height: calc(100vh - 30px);
            max-height: calc(100vh - 30px);
            border-radius: 20px;
            padding: 30px 25px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
            position: relative;
            box-sizing: border-box;
            border-top: 6px solid #00c853;
            animation: slideUp 0.3s ease;
            display: flex;
            flex-direction: column;
        }

        @keyframes slideUp {
            from { transform: translateY(20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .close-modal {
            position: absolute;
            top: 15px;
            right: 15px;
            background: #f1f5f9;
            border: none;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            font-size: 16px;
            cursor: pointer;
            color: #64748b;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
            z-index: 10;
        }

        .close-modal:hover {
            background: #fee2e2;
            color: #ef4444;
            transform: rotate(90deg);
        }

        .modal-scrollable-content {
            overflow-y: auto;
            flex-grow: 1;
            padding-right: 5px;
            display: flex;
            flex-direction: column;
            gap: 14px;
        }

        .modal-header-content {
            text-align: center;
        }

        .modal-logo {
            width: 65px;
            height: 65px;
            object-fit: contain;
            margin-bottom: 8px;
            filter: drop-shadow(0 4px 6px rgba(0,0,0,0.05));
        }

        .modal-header-content h2 {
            font-size: 15px;
            font-weight: 800;
            color: #006b37;
            margin: 0 0 4px 0;
            letter-spacing: 0.5px;
        }

        .modal-header-content p {
            font-size: 11px;
            color: #64748b;
            font-weight: 600;
            margin: 0;
        }

        .course-section {
            text-align: center;
        }

        .course-section h3 {
            font-size: 13px;
            font-weight: 800;
            color: #1e293b;
            margin: 0 0 2px 0;
        }

        .course-section p {
            font-size: 11px;
            color: #64748b;
            font-weight: 600;
            margin: 0;
            text-transform: none;
        }

        .fulfillment-text {
            font-size: 11px;
            color: #475569;
            text-align: center;
            line-height: 1.4;
            padding: 0 5px;
        }

        .centered-block {
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 6px;
            margin: 4px 0;
        }

        .team-badge-box {
            background: linear-gradient(135deg, #e8f8f0 0%, #d4f4e2 100%);
            border: 1px solid rgba(0, 200, 83, 0.3);
            border-radius: 10px;
            padding: 9px 12px;
            font-size: 12px;
            font-weight: 800;
            color: #006b37;
            box-shadow: inset 0 1px 2px rgba(255,255,255,0.6);
            width: 100%;
            box-sizing: border-box;
            text-align: center;
        }

        .section-label {
            font-size: 11px;
            font-weight: 700;
            color: #64748b;
            text-transform: none;
        }

        .instructor-list, .members-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .member-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #f8fafc;
            padding: 8px 12px;
            border-radius: 10px;
            border: 1px solid #e2e8f0;
            font-size: 12px;
            transition: border-color 0.2s;
        }

        .member-row:hover {
            border-color: #00c853;
            background: #f0fdf4;
        }

        .member-name {
            font-weight: 700;
            color: #1e293b;
        }

        .member-role {
            font-size: 10px;
            font-weight: 600;
            color: #006b37;
            background: #e8f8f0;
            padding: 3px 8px;
            border-radius: 6px;
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
            <a href="about.php" class="active"><i class="fa-solid fa-circle-info"></i> About us</a>
            <a href="signup.php"><i class="fa-solid fa-user-plus"></i> Sign-up</a>
            <a href="login.php"><i class="fa-solid fa-right-to-bracket"></i> Log-in</a>
        </div>
    </nav>

    <main class="main-wrapper">
        <div class="about-card">
            <h1 style="font-size: 28px; font-weight: 800; margin-bottom: 24px; border-bottom: 2px solid #e2e8f0; padding-bottom: 12px;">About KLD Attendance System</h1>

            <div style="margin-bottom: 20px;">
                <h3 style="font-size: 16px; font-weight: 700; color: #00c853; display: flex; align-items: center; gap: 8px; margin-bottom: 6px;"><i class="fa-solid fa-laptop-code"></i> Our System</h3>
                <p style="font-size: 14px; color: #475569; line-height: 1.5;">Designed to provide a simple and automated way of recording student attendance in real time.</p>
            </div>

            <div style="margin-bottom: 20px;">
                <h3 style="font-size: 16px; font-weight: 700; color: #00c853; display: flex; align-items: center; gap: 8px; margin-bottom: 6px;"><i class="fa-solid fa-bullseye"></i> Our Purpose</h3>
                <p style="font-size: 14px; color: #475569; line-height: 1.5;">Our goal is to make attendance tracking hassle-free for students and teachers through modern web technology.</p>
            </div>

            <div style="margin-bottom: 20px;">
                <h3 style="font-size: 16px; font-weight: 700; color: #00c853; display: flex; align-items: center; gap: 8px; margin-bottom: 6px;"><i class="fa-solid fa-flag"></i> Our Goal</h3>
                <p style="font-size: 14px; color: #475569; line-height: 1.5;">We aim to create an efficient, reliable, and user-friendly system for managing overall academic attendance data.</p>
            </div>

            <div style="margin-top: 30px; background: #e8f8f0; border: 1px solid rgba(0, 200, 83, 0.3); border-radius: 12px; padding: 14px 20px; display: flex; align-items: center; justify-content: space-between;">
                <span style="font-size: 12px; font-weight: 700; color: #64748b; text-transform: uppercase;">Developed By</span>
                <strong style="font-size: 15px;"><span class="clickable-group-text" onclick="openTeamModal()">GROUP 8 | WE BYTE</span></strong>
            </div>
        </div>
    </main>

    <!-- Team Members Popup Modal -->
    <div class="modal-overlay" id="teamModal">
        <div class="modal-card">
            <button class="close-modal" onclick="closeTeamModal()"><i class="fa-solid fa-xmark"></i></button>
            
            <div class="modal-scrollable-content">
                <div class="modal-header-content">
                    <img src="kld-logo.png" alt="KLD Logo" class="modal-logo">
                    <h2>KOLEHIYO NG LUNGSOD NG DASMARIÑAS</h2>
                    <p>Building the Foundation for the Dasmarineños</p>
                </div>

                <div class="course-section">
                    <h3>BACHELOR OF SCIENCE IN INFORMATION SYSTEMS 204</h3>
                    <p>Academic Year 2026-2027</p>
                </div>

                <div class="fulfillment-text">
                    In partial fulfillment of the Web Development requirement in Web Systems and Technologies subject
                </div>

                <div class="centered-block">
                    <span class="section-label">Acknowledgement to</span>
                </div>

                <div class="instructor-list">
                    <div class="member-row">
                        <span class="member-name">Con Marvin B. Serrano</span>
                        <span class="member-role">Course Instructor</span>
                    </div>
                </div>

                <div class="centered-block" style="margin-top: 6px;">
                    <span class="section-label">A project of</span>
                    <div class="team-badge-box">GROUP 8 | WE BYTE</div>
                    <span class="section-label">through the collective efforts of</span>
                </div>

                <div class="members-list" style="margin-top: 8px;">
                    <div class="member-row">
                        <span class="member-name">Molines, Jan Micah, Valenciano</span>
                        <span class="member-role">Front-end Developer</span>
                    </div>
                    <div class="member-row">
                        <span class="member-name">Palileo, Jolo Nicko, Ocenar</span>
                        <span class="member-role">Front-end Developer</span>
                    </div>
                    <div class="member-row">
                        <span class="member-name">Moroña, Sai Gabriele, Ranay</span>
                        <span class="member-role">Database Developer and Manager</span>
                    </div>
                    <div class="member-row">
                        <span class="member-name">Patlonag, Mark Lourence, Tante</span>
                        <span class="member-role">Back-end Developer</span>
                    </div>
                    <div class="member-row">
                        <span class="member-name">Montes, Lyzamae, Banaag</span>
                        <span class="member-role">Process Manager</span>
                    </div>
                    <div class="member-row">
                        <span class="member-name">San Jose, Hanz Darwin, Rellora</span>
                        <span class="member-role">Project Manager</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function openTeamModal() {
            document.getElementById('teamModal').style.display = 'flex';
        }

        function closeTeamModal() {
            document.getElementById('teamModal').style.display = 'none';
        }

        window.onclick = function(event) {
            const modal = document.getElementById('teamModal');
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        }
    </script>
</body>
</html>
