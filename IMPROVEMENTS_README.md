# Webyte improvement patch

This patch is based on the uploaded `webyte-main.zip` and keeps the existing project structure. It modifies only the relevant PHP pages and adds a migration plus profile-request pages.

## Before using

1. Back up the existing `webyte` database.
2. Import `migrations/2026-10-19_improvements.sql` into the existing database (MariaDB 10.4+ supports the `ADD COLUMN IF NOT EXISTS` syntax used here).
3. Configure outgoing email on the PHP server. Signup confirmation and password-reset codes use PHP `mail()`. Set `WEBYTE_MAIL_FROM` to a sender address approved by your mail host. On a local XAMPP/WAMP install, `mail()` usually needs SMTP/sendmail configuration before it can deliver messages.
4. Run the project with a supported PHP version and verify camera permissions using HTTPS or localhost.

## Implemented in this patch

- Signup now validates names on the server, normalizes name capitalization, requires middle name or `N/A`, restricts public signup to Student, validates the 4-2-6 student-number format, enforces `@kld.edu.ph`, checks password complexity, requires privacy-policy consent, validates uploaded profile pictures, and sends a registration confirmation email.
- Forgot-password flow sends a six-digit email code, expires it after 10 minutes, verifies it before allowing password reset, and enforces the password policy.
- Student and teacher self-profile updates are restricted server-side to profile picture/password; identity fields are readonly in the forms. Added profile-change request submission and an admin review page that applies approved requested values.
- Teacher section entry is a dropdown sourced from admin-created sections; the per-section delete control was removed from the visible section modal.
- QR generation stores a 10-digit ID and server timestamp in `teacher_qr_sessions`; attendance status uses elapsed time: 0–10 minutes Present, 11–15 Late, 16+ Absent. The student scanner no longer mirrors the camera view. Student-name table cells are kept on one line where the class-record style applies.
- Added data-driven Chart.js summaries to the admin, teacher, and student dashboards, refreshed automatically every 15 seconds.
- Added working student/teacher chat page using the existing messages APIs, a notifications page, and attendance-recorded notifications. Student/teacher chat is restricted to administrator contacts; administrators can message active accounts. Admins can open notifications and profile-change requests from navigation. The truncate/reset action now requires a six-digit code sent to the administrator email.
- Account deletion now marks users as soft-deleted instead of removing the user row and related enrollment/teaching records. The login query excludes soft-deleted accounts.

## Important integration notes

- The existing repository contains multiple legacy attendance flows and overlapping tables (`attendance`, `attendance_logs`, `attendance_records`, `qr_sessions`). This patch updates both `process_qr.php` and `process_attendance.php` to use the current teacher QR-session format and the same status thresholds. Older attendance tables still exist in the repository and should be consolidated in a separately tested migration before production use.
- Email delivery depends on server mail configuration; code cannot prove delivery without a configured mail transport.
- Run PHP syntax checks after deployment and test with a database copy first. Do not import the original `webyte.sql` over an existing database because it contains sample inserts and schema setup statements.
