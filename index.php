<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home - KLD Attendance System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <nav>
        <div class="logo-container">
            <img src="kld-logo.png" alt="KLD Logo" class="sidebar-logo">
            <h3 class="brand-title">KLD</h3>
            <span class="brand-subtitle">Attendance Monitoring System</span>
        </div>
        <div class="nav-links">
            <a href="index.php" class="active"><i class="fa-solid fa-house"></i> Home</a>
            <a href="about.php"><i class="fa-solid fa-circle-info"></i> About us</a>
            <a href="signup.php"><i class="fa-solid fa-user-plus"></i> Sign-up</a>
            <a href="login.php"><i class="fa-solid fa-right-to-bracket"></i> Log-in</a>
        </div>
    </nav>

    <main class="main-wrapper">
        <div class="hero-card">
            <h1 style="font-size: 32px; font-weight: 800; margin-bottom: 8px;">Attendance Monitoring System</h1>
            <p style="font-size: 16px; color: #64748b; margin-bottom: 30px;">A simple, smart, and organized way to monitor student attendance and track academic presence seamlessly.</p>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px;">
                <div style="background: #f8fafc; padding: 24px; border-radius: 16px; border: 1px solid #e2e8f0;">
                    <div style="width: 44px; height: 44px; background: #e8f8f0; color: #00c853; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 20px; margin-bottom: 14px;"><i class="fa-solid fa-calendar-check"></i></div>
                    <h3 style="font-size: 18px; font-weight: 700; margin-bottom: 6px;">Track Attendance</h3>
                    <p style="font-size: 13.5px; color: #64748b;">Stay updated with your daily class attendance records anywhere, anytime.</p>
                </div>
                <div style="background: #f8fafc; padding: 24px; border-radius: 16px; border: 1px solid #e2e8f0;">
                    <div style="width: 44px; height: 44px; background: #e8f8f0; color: #00c853; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 20px; margin-bottom: 14px;"><i class="fa-solid fa-qrcode"></i></div>
                    <h3 style="font-size: 18px; font-weight: 700; margin-bottom: 6px;">QR Code Attendance</h3>
                    <p style="font-size: 13.5px; color: #64748b;">Fast and reliable automated attendance tracking using QR code scanning.</p>
                </div>
            </div>
        </div>
    </main>
</body>
</html>