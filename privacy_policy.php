<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Privacy Policy - KLD Attendance Monitoring System</title>
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
        .policy-container {
            background: #ffffff;
            max-width: 800px;
            width: 100%;
            padding: 45px;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 128, 0, 0.08);
            border: 1px solid rgba(16, 138, 0, 0.08);
            box-sizing: border-box;
        }
        .policy-header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #f0f5f1;
            padding-bottom: 20px;
        }
        .policy-header h2 {
            color: #1a331e;
            margin: 10px 0 5px 0;
            font-size: 26px;
            font-weight: 800;
        }
        .policy-header p {
            color: #666;
            font-size: 14px;
            margin: 0;
        }
        .policy-content h4 {
            color: #108a00;
            font-size: 16px;
            margin-top: 25px;
            margin-bottom: 10px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .policy-content p, .policy-content li {
            font-size: 14px;
            color: #4f5d52;
            line-height: 1.6;
        }
        .policy-content ul {
            margin: 0 0 15px 20px;
            padding: 0;
        }
        .policy-content li {
            margin-bottom: 6px;
        }
        .back-section {
            margin-top: 35px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            text-align: left;
        }
        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #108a00;
            color: #ffffff;
            padding: 12px 22px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 14px;
            text-decoration: none;
            transition: background 0.3s ease;
            box-shadow: 0 4px 12px rgba(16, 138, 0, 0.15);
        }
        .back-btn:hover {
            background: #0d6e00;
        }
    </style>
</head>
<body>
    <div class="main-container">
        <div class="policy-container">
            <div class="policy-header">
                <img src="kld-logo.png" alt="KLD Logo" style="width: 55px; height: 55px; object-fit: contain; margin-bottom: 5px;">
                <h2>Data Privacy Policy</h2>
                <p>Republic Act No. 10173 (Data Privacy Act of 2012)</p>
            </div>
            
            <div class="policy-content">
                <p>The Kolehiyo ng Lungsod ng Dasmariñas (KLD) Attendance Monitoring System is deeply committed to protecting and respecting your personal privacy. This policy outlines our practices regarding the collection, use, processing, and safeguarding of your information in compliance with the Data Privacy Act.</p>
                
                <h4><i class="fa-solid fa-folder-open"></i> 1. Information We Collect</h4>
                <p>To ensure proper identification and system functionality, we collect and process the following categories of data:</p>
                <ul>
                    <li>Full legal name, identification (ID) number, and official KLD institutional email address.</li>
                    <li>Academic details, class schedules, and attendance tracking logs (including scan timestamps, entry status, and location verifications).</li>
                    <li>Uploaded profile pictures and customized account preferences.</li>
                    <li>Technical system metadata, including active session durations and basic access logs for security auditing.</li>
                </ul>

                <h4><i class="fa-solid fa-bullseye"></i> 2. Purpose of Data Collection</h4>
                <p>The data gathered within this platform is strictly utilized for legitimate educational, operational, and security goals, which include:</p>
                <ul>
                    <li>Monitoring and recording accurate class attendance for enrolled courses and institutional activities.</li>
                    <li>Generating analytical attendance reports and summaries for authorized faculty members and department heads.</li>
                    <li>Maintaining system security, preventing identity fraud, and resolving technical discrepancies.</li>
                    <li>Facilitating seamless communication channels between students, instructors, and system administrators.</li>
                </ul>

                <h4><i class="fa-solid fa-shield-halved"></i> 3. Data Protection and Security Measures</h4>
                <p>We implement rigorous organizational, technical, and physical security protocols—such as password hashing, encrypted database architecture, and restricted role-based user permissions—to safeguard your personal information against unauthorized access, modification, disclosure, or accidental destruction.</p>

                <h4><i class="fa-solid fa-user-check"></i> 4. User Consent and Rights</h4>
                <p>By registering or using the KLD Attendance Monitoring System, you acknowledge that you understand and consent to the collection and processing of your data as outlined in this policy. You retain the right to review your stored account details and report privacy concerns through official institutional channels.</p>
            </div>
            
            <div class="back-section">
                <a href="signup.php" class="back-btn"><i class="fa-solid fa-arrow-left"></i> Back to Sign Up</a>
            </div>
        </div>
    </div>
</body>
</html>