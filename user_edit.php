<?php require_once 'config.php'; checkAdmin();
$id = (int)$_GET['id'];
$db = getDB();
$stmt = $db->prepare("SELECT * FROM users WHERE id=?");
$stmt->execute([$id]);
$user = $stmt->fetch();
if(!$user)die("用户不存在");

$msg = '';
if($_SERVER['REQUEST_METHOD']==='POST'){
    $username=trim($_POST['username']);
    $traffic_quota=(int)$_POST['traffic_quota'];
    $status=(int)$_POST['status'];
    if(!$username || $traffic_quota<0){$msg='<span style="color:red">参数错误</span>';}
    else{
        $s=$db->prepare("UPDATE users SET username=?,traffic_quota=?,status=? WHERE id=?");
        $s->execute([$username,$traffic_quota,$status,$id]);
        $msg='<span style="color:green">保存成功</span>';
        echo "<meta http-equiv='refresh' content='2;url=user.php'>";
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>编辑用户</title>
    <link rel="stylesheet" href="https://cdn.bootcdn.net/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
    *{margin:0;padding:0;box-sizing:border-box;font-family:"Microsoft Yahei",sans-serif;transition:background 0.3s,border-color 0.3s,color 0.3s;}
    :root{--body-bg:#f8fafc;--card-bg:#fff;--text-primary:#1e293b;--border-color:#e2e8f0;--primary:#64748b;--secondary:#f1f5f9;--border-radius:8px;--shadow:0 2px 12px rgba(0,0,0,0.05);}
    [data-theme="dark"]{--body-bg:#0f172a;--card-bg:#1e293b;--text-primary:#f1f5f9;--border-color:#334155;--primary:#4f46e5;--secondary:#334155;}
    body{background:var(--body-bg);color:var(--text-primary);padding:20px;}
    .container{max-width:600px;margin:40px auto;background:var(--card-bg);padding:30px;border-radius:12px;box-shadow:var(--shadow);}
    h2{margin-bottom:20px;}
    .form-group{margin-bottom:15px;}
    label{display:block;margin-bottom:6px;}
    input{width:100%;padding:10px;border:1px solid var(--border-color);border-radius:var(--border-radius);background:var(--secondary);color:var(--text-primary);}
    button{background:var(--primary);color:#fff;border:none;padding:10px 20px;border-radius:var(--border-radius);cursor:pointer;}
    .msg{margin:10px 0;}
    </style>
</head>
<body>
<div class="container">
    <h2>编辑用户</h2>
    <?php if($msg)echo "<div class='msg'>$msg</div>";?>
    <form method="post">
        <div class="form-group"><label>用户名</label><input name="username" value="<?=e($user['username'])?>" required></div>
        <div class="form-group"><label>流量配额(MB)</label><input name="traffic_quota" type="number" min="0" value="<?=$user['traffic_quota']?>" required></div>
        <div class="form-group"><label>状态</label>
            <select name="status">
                <option value="1" <?=$user['status']?'selected':''?>>启用</option>
                <option value="0" <?=$user['status']?'':'selected'?>>禁用</option>
            </select>
        </div>
        <button type="submit">保存</button>
    </form>
    <br>
    <a href="user.php">返回用户列表</a>
</div>
</body>
</html>
