<?php
session_start(); require_once 'db_connect.php';
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') { header('Location: login.php'); exit(); }
$notice=''; $error='';
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['request_id'],$_POST['decision'])) {
 $rid=(int)$_POST['request_id']; $decision=$_POST['decision'];
 if (!in_array($decision,['approved','rejected'],true)) $error='Invalid decision.';
 else {
  $stmt=$conn->prepare("SELECT r.id,r.user_id,r.requested_fields,u.role FROM profile_change_requests r JOIN users u ON u.id=r.user_id WHERE r.id=? AND r.status='pending'"); $stmt->bind_param('i',$rid);$stmt->execute();$req=$stmt->get_result()->fetch_assoc();$stmt->close();
  if(!$req)$error='This request has already been reviewed or does not exist.';
  else {
   $fields=json_decode($req['requested_fields'],true) ?: [];
   $allow=$req['role']==='student'?['first_name','middle_name','last_name','id_number','email','section']:['first_name','middle_name','last_name','id_number','email','institute','position'];
   if($decision==='approved' && $fields){$sets=[];$types='';$vals=[];foreach($fields as $col=>$val){if(!in_array($col,$allow,true))continue;$sets[]="`$col`=?";$types.='s';$vals[]=(string)$val;}if($sets){$vals[]=(int)$req['user_id'];$types.='i';$up=$conn->prepare('UPDATE users SET '.implode(',',$sets).' WHERE id=?');$up->bind_param($types,...$vals);$up->execute();$up->close();}}
   $reviewer=(int)$_SESSION['user_id'];$up=$conn->prepare('UPDATE profile_change_requests SET status=?, reviewed_by=?, reviewed_at=NOW() WHERE id=?');$up->bind_param('sii',$decision,$reviewer,$rid);$up->execute();$up->close();$notice='Request '.$decision.'.';
  }
 }
}
$rows=$conn->query("SELECT r.id,r.user_id,r.requested_fields,r.created_at,u.first_name,u.middle_name,u.last_name,u.email,u.role FROM profile_change_requests r JOIN users u ON u.id=r.user_id WHERE r.status='pending' ORDER BY r.created_at ASC");
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Profile Change Requests - Webyte</title><link rel="stylesheet" href="style.css"><style>body{font-family:Arial,sans-serif;background:#f3f7f4;padding:24px}.panel{max-width:1000px;margin:auto;background:white;padding:24px;border-radius:16px;box-shadow:0 8px 28px #0001}.request{padding:16px 0;border-bottom:1px solid #ddd}.btn{padding:9px 14px;border:0;border-radius:7px;color:#fff;cursor:pointer}.approve{background:#108a00}.reject{background:#b42318}pre{white-space:pre-wrap;overflow-wrap:anywhere}</style></head><body><div class="panel"><h2>Pending Profile Change Requests</h2><p><a href="admin_dashboard.php">Back to Admin Dashboard</a></p><?php if($notice):?><p style="color:green"><?=htmlspecialchars($notice)?></p><?php endif;?><?php if($error):?><p style="color:#b42318"><?=htmlspecialchars($error)?></p><?php endif;?><?php if($rows):while($row=$rows->fetch_assoc()):?><section class="request"><h3><?=htmlspecialchars(trim($row['first_name'].' '.$row['middle_name'].' '.$row['last_name']))?> (<?=htmlspecialchars($row['role'])?>)</h3><p><?=htmlspecialchars($row['email'])?> · Submitted <?=htmlspecialchars($row['created_at'])?></p><pre><?=htmlspecialchars(json_encode(json_decode($row['requested_fields'],true),JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE))?></pre><form method="post" style="display:flex;gap:10px"><input type="hidden" name="request_id" value="<?=$row['id']?>"><button class="btn approve" name="decision" value="approved">Approve and Apply Changes</button><button class="btn reject" name="decision" value="rejected">Reject</button></form></section><?php endwhile;else:?><p>No pending requests.</p><?php endif;?></div></body></html>