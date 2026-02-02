<?php
ignore_user_abort(true);
set_time_limit(0);
header('Content-Type: application/json;charset=utf-8');

$host = '127.0.0.1';
$dbname = 'xboard_mini';
$user = 'xboard';
$pass = '你的数据库密码';

$username = $_GET['user'] ?? '';
$upload = (int)($_GET['up'] ?? 0);
$download = (int)($_GET['down'] ?? 0);
$total = $upload + $download;

if(empty($username) || $total <= 0) {
    die(json_encode(['success'=>false]));
}

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4",$user,$pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $stmt = $pdo->prepare("UPDATE users SET traffic_used = traffic_used + ? WHERE username=? LIMIT 1");
    $stmt->execute([$total, $username]);
    
    die(json_encode(['success'=>true]));
} catch(Exception $e) {
    die(json_encode(['success'=>false,'msg'=>$e->getMessage()]));
}
