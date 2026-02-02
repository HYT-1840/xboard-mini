<?php
ignore_user_abort(true);
set_time_limit(0);
header('Content-Type: application/json;charset=utf-8');

$host = '127.0.0.1';
$dbname = 'xboard_mini';
$user = 'xboard';
$pass = '你的数据库密码';

$username = $_GET['user'] ?? '';
if(empty($username)) die(json_encode(['success'=>false,'msg'=>'empty user']));

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4",$user,$pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $stmt = $pdo->prepare("SELECT id,status,traffic_quota,traffic_used FROM users WHERE username=? LIMIT 1");
    $stmt->execute([$username]);
    $u = $stmt->fetch();
    
    if(!$u) die(json_encode(['success'=>false,'msg'=>'not found']));
    if($u['status'] != 1) die(json_encode(['success'=>false,'msg'=>'banned']));
    
    $left = $u['traffic_quota'] - $u['traffic_used'];
    if($left <= 0) die(json_encode(['success'=>false,'msg'=>'traffic full']));
    
    die(json_encode([
        'success' => true,
        'uid' => $u['id'],
        'username' => $username,
        'traffic_left' => $left
    ]));
} catch(Exception $e) {
    die(json_encode(['success'=>false,'msg'=>'db error']));
}
