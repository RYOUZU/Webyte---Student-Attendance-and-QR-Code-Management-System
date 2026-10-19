<?php
session_start(); require_once 'db_connect.php'; header('Content-Type: application/json; charset=utf-8');
if (!isset($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['error'=>'Unauthorized']); exit(); }
$id=(int)$_SESSION['user_id'];$role=$_SESSION['role']??'';
if($role==='admin'){
 $a=$conn->query("SELECT COUNT(*) AS n FROM users WHERE role='student' AND COALESCE(is_deleted,0)=0")->fetch_assoc();
 $b=$conn->query("SELECT COUNT(*) AS n FROM users WHERE role IN ('teacher','faculty') AND COALESCE(is_deleted,0)=0")->fetch_assoc();
 echo json_encode(['labels'=>['Students','Teachers'],'values'=>[(int)$a['n'],(int)$b['n']]]);exit();
}
if($role==='faculty'){
 $stmt=$conn->prepare('SELECT COUNT(DISTINCT subject_name) AS subjects, COUNT(*) AS sections FROM teacher_subjects WHERE teacher_id=? AND COALESCE(is_deleted,0)=0');$stmt->bind_param('i',$id);$stmt->execute();$row=$stmt->get_result()->fetch_assoc();$stmt->close();
 echo json_encode(['labels'=>['Subjects','Assigned Sections'],'values'=>[(int)$row['subjects'],(int)$row['sections']]]);exit();
}
if($role==='student'){
 $counts=['Present'=>0,'Late'=>0,'Absent'=>0];$stmt=$conn->prepare('SELECT status,COUNT(*) AS n FROM attendance WHERE student_id=? GROUP BY status');$stmt->bind_param('i',$id);$stmt->execute();$res=$stmt->get_result();while($r=$res->fetch_assoc())if(isset($counts[$r['status']]))$counts[$r['status']]=(int)$r['n'];$stmt->close();
 echo json_encode(['labels'=>array_keys($counts),'values'=>array_values($counts)]);exit();
}
http_response_code(403);echo json_encode(['error'=>'Unsupported role']);
