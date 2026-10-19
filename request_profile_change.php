<?php
session_start();
require_once 'db_connect.php';
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['student','faculty'])) { header('Location: login.php'); exit(); }
$userId = (int)$_SESSION['user_id']; $role = $_SESSION['role']; $message = ''; $error = '';
$allowed = $role === 'student' ? ['first_name'=>'First Name','middle_name'=>'Middle Name','last_name'=>'Last Name','id_number'=>'Student Number','email'=>'KLD Email','section'=>'Section'] : ['first_name'=>'First Name','middle_name'=>'Middle Name','last_name'=>'Last Name','id_number'=>'Employee Number','email'=>'KLD Email','institute'=>'Institute','position'=>'Position'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $requested = [];
  foreach ($allowed as $key=>$label) if (!empty($_POST['fields'][$key])) {
    $value = trim($_POST['values'][$key] ?? '');
    if ($value === '') { $error = 'Enter the requested new value for ' . $label . '.'; break; }
    $requested[$key] = $value;
  }
  if (!$error && !$requested) $error = 'Select at least one field to request.';
  if (!$error) {
    $json = json_encode($requested, JSON_UNESCAPED_UNICODE);
    $stmt = $conn->prepare("INSERT INTO profile_change_requests (user_id, requested_fields, status, created_at) VALUES (?, ?, 'pending', NOW())");
    $stmt->bind_param('is', $userId, $json);
    if ($stmt->execute()) $message = 'Your profile change request has been sent to the administrator.'; else $error = 'Could not submit the request. Apply the database migration and try again.';
    $stmt->close();
  }
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Request Profile Change - Webyte</title><link rel="stylesheet" href="style.css"><style>body{font-family:Arial,sans-serif;background:#f3f7f4;padding:24px}.panel{max-width:760px;margin:20px auto;background:#fff;border-radius:16px;padding:24px;box-shadow:0 8px 28px #0001}.field{display:grid;grid-template-columns:24px 1fr minmax(180px,1fr);gap:12px;align-items:center;padding:10px 0;border-bottom:1px solid #eee}input[type=text],input[type=email]{width:100%;padding:10px;border:1px solid #ccc;border-radius:8px}.btn{padding:11px 16px;border:0;border-radius:8px;background:#108a00;color:white;cursor:pointer}@media(max-width:600px){.field{grid-template-columns:24px 1fr}.field input[type=text],.field input[type=email]{grid-column:2}}</style></head><body><div class="panel"><h2>Request Profile Change</h2><p>Select the profile fields you want changed and provide the requested new values. An administrator must review your request.</p><?php if($message):?><p role="status" style="color:green"><?=htmlspecialchars($message)?></p><?php endif;?><?php if($error):?><p role="alert" style="color:#b42318"><?=htmlspecialchars($error)?></p><?php endif;?><form method="post"><?php foreach($allowed as $key=>$label):?><label class="field"><input type="checkbox" name="fields[<?=htmlspecialchars($key)?>]" value="1"><span><?=htmlspecialchars($label)?></span><input type="text" name="values[<?=htmlspecialchars($key)?>]" placeholder="Requested new value"></label><?php endforeach;?><p><button class="btn" type="submit">Submit Request</button> <a href="<?= $role==='student'?'student_dashboard.php':'teacher_dashboard.php' ?>">Back to Dashboard</a></p></form></div></body></html>