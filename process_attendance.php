<?php
session_start();
require_once 'db_connect.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'student') {
    http_response_code(401);
    echo json_encode(['success'=>false,'status'=>'error','message'=>'Please sign in with a student account.']);
    exit();
}
$studentId=(int)$_SESSION['user_id'];
$input=json_decode(file_get_contents('php://input'),true) ?: [];
$qr=trim((string)($input['qr_code'] ?? $input['qr_data'] ?? $_POST['qr_data'] ?? $_POST['qr_code'] ?? ''));
if($qr===''){echo json_encode(['success'=>false,'status'=>'error','message'=>'No QR code data was received.']);exit();}
$subjectId=0;$sessionId='';
$decoded=json_decode($qr,true);
if(is_array($decoded)){$subjectId=(int)($decoded['class_id'] ?? $decoded['subject_id'] ?? 0);$sessionId=trim((string)($decoded['session_id'] ?? $decoded['qr_id'] ?? ''));}
else {if(preg_match('/^ATTENDANCE_SUBJ_(\d+)/',$qr,$m))$subjectId=(int)$m[1];elseif(ctype_digit($qr))$subjectId=(int)$qr;}
if($subjectId<1){echo json_encode(['success'=>false,'status'=>'error','message'=>'Invalid or unrecognized QR code.']);exit();}
$enroll=$conn->prepare('SELECT id FROM student_enrollments WHERE student_id=? AND subject_id=? AND COALESCE(is_deleted,0)=0');
$enroll->bind_param('ii',$studentId,$subjectId);$enroll->execute();$isEnrolled=$enroll->get_result()->fetch_assoc();$enroll->close();
if(!$isEnrolled){echo json_encode(['success'=>false,'status'=>'error','message'=>'You are not enrolled in this subject.']);exit();}
$generatedAt=null;
if($sessionId!==''){$q=$conn->prepare('SELECT created_at FROM teacher_qr_sessions WHERE id=? AND COALESCE(is_deleted,0)=0');if($q){$q->bind_param('s',$sessionId);$q->execute();$row=$q->get_result()->fetch_assoc();if($row)$generatedAt=$row['created_at'];$q->close();}}
if(!$generatedAt){$q=$conn->prepare('SELECT generated_at FROM qr_sessions WHERE qr_code_hash=? ORDER BY generated_at DESC LIMIT 1');if($q){$q->bind_param('s',$qr);$q->execute();$row=$q->get_result()->fetch_assoc();if($row)$generatedAt=$row['generated_at'];$q->close();}}
if(!$generatedAt){echo json_encode(['success'=>false,'status'=>'error','message'=>'The QR code session could not be verified. Please ask your instructor to generate a new code.']);exit();}
$now=date('Y-m-d H:i:s');$today=date('Y-m-d');
$dup=$conn->prepare('SELECT id FROM attendance WHERE student_id=? AND subject_id=? AND DATE(scanned_at)=? LIMIT 1');$dup->bind_param('iis',$studentId,$subjectId,$today);$dup->execute();$already=$dup->get_result()->fetch_assoc();$dup->close();
if($already){echo json_encode(['success'=>false,'status'=>'error','message'=>'Your attendance has already been recorded for today.']);exit();}
$minutes=(int)floor(max(0,time()-strtotime($generatedAt))/60);
$status=$minutes<=10?'Present':($minutes<=15?'Late':'Absent');
$ins=$conn->prepare('INSERT INTO attendance (student_id,subject_id,status,qr_generated_at,scanned_at) VALUES (?,?,?,?,?)');
$ins->bind_param('iisss',$studentId,$subjectId,$status,$generatedAt,$now);
if(!$ins->execute()){echo json_encode(['success'=>false,'status'=>'error','message'=>'A database error prevented attendance from being recorded.']);$ins->close();exit();}$ins->close();
$notice=$conn->prepare('INSERT INTO notifications (user_id,title,message,created_at) VALUES (?,?,?,NOW())');if($notice){$title='Attendance Recorded';$message='Your attendance was recorded as '.$status.'.';$notice->bind_param('iss',$studentId,$title,$message);$notice->execute();$notice->close();}
echo json_encode(['success'=>true,'status'=>'success','attendance_status'=>$status,'message'=>'Attendance recorded successfully: '.$status]);
?>
