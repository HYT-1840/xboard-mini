<?php
require_once 'config.php';
checkAdmin();

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old_pwd = trim($_POST['old_pwd']);
    $new_pwd = trim($_POST['new_pwd']);
    $confirm_pwd = trim($_POST['confirm_pwd']);

    if (strlen($new_pwd) < 6) {
        $msg = '<span style="color:red">新密码至少6位</span>';
    } elseif ($new_pwd !== $confirm_pwd) {
        $msg = '<span style="color:red">两次新密码不一致</span>';
    } else {
        $db = getDB();
        $stmt = $db->query("SELECT password FROM admins LIMIT 1");
        $admin = $stmt->fetch();
        if (password_verify($old_pwd, $admin['password'])) {
            $new_hash = password_hash($new_pwd, PASSWORD_DEFAULT);
            $update = $db->prepare("UPDATE admins SET password = ? LIMIT 1");
            $update->execute([$new_hash]);
            $msg = '<span style="color:green">密码修改成功，请重新登录</span>';
            session_destroy();
            echo "<meta http-equiv='refresh' content='2;url=index.php'>";
        } else {
            $msg = '<span style="color:red">原密码错误</span>';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>修改密码</title>
    <link rel="stylesheet" href="https://cdn.bootcdn.net/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
    *{margin:0;padding:0;box-sizing:border-box;font-family:"Microsoft Yahei",sans-serif;transition:background 0.3s,border-color 0.3s,color 0.3s;}
    :root{--body-bg:#f8fafc;--card-bg:#fff;--text-primary:#1e293b;--border-color:#e2e8f0;--primary:#64748b;--secondary:#f1f5f9;--border-radius:8px;--shadow:0 2px 12px rgba(0,0,0,0.05);}
    [data-theme="dark"]{--body-bg:#0f172a;--card-bg:#1e293b;--text-primary:#f1f5f9;--border-color:#334155;--primary:#4f46e5;--secondary:#334155;}
    body{background:var(--body-bg);color:var(--text-primary);padding:20px;}
    .container{max-width:500px;margin:60px auto;background:var(--card-bg);padding:30px;border-radius:12px;box-shadow:var(--shadow);}
    h2{margin-bottom:20px;display:flex;align-items:center;gap:8px;}
    .form-group{margin-bottom:15px;}
    label{display:block;margin-bottom:6px;font-weight:500;}
    input{width:100%;padding:10px;border:1px solid var(--border-color);border-radius:var(--border-radius);background:var(--secondary);color:var(--text-primary);}
    button{background:var(--primary);color:#fff;border:none;padding:10px 20px;border-radius:var(--border-radius);cursor:pointer;width:100%;margin-top:10px;}
    .msg{padding:10px;border-radius:6px;margin-bottom:15px;}
    .back{margin-top:15px;display:inline-block;color:var(--primary);text-decoration:none;}
    </style>
</head>
<body>
<div class="container">
    <h2><i class="fas fa-lock"></i> 修改管理员密码</h2>
    <?php if($msg) echo "<div class='msg'>$msg</div>"; ?>
    <form method="post">
        <div class="form-group">
            <label>原密码</label>
            <input type="password" name="old_pwd" required>
        </div>
        <div class="form-group">
            <label>新密码</label>
            <input type="password" name="new_pwd" required>
        </div>
        <div class="form-group">
            <label>确认新密码</label>
            <input type="password" name="confirm_pwd" required>
        </div>
        <button type="submit">保存修改</button>
    </form>
    <a href="admin.php" class="back">返回首页</a>
</div>
</body>
</html>
