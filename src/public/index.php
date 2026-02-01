<?php
session_start();
// 已登录则跳转到管理页
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: /pages/admin.php');
    exit;
}

$error = '';
// 处理登录请求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    
    if (empty($username) || empty($password)) {
        $error = '用户名和密码不能为空';
    } else {
        // 连接数据库验证账号
        $db = new SQLite3('../xboard-mini/database.db');
        $stmt = $db->prepare("SELECT password FROM admin WHERE username = :username");
        $stmt->bindValue(':username', $username, SQLITE3_TEXT);
        $result = $stmt->execute();
        $admin = $result->fetchArray(SQLITE3_ASSOC);
        
        if ($admin && password_verify($password, $admin['password'])) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_username'] = $username;
            header('Location: /pages/admin.php');
            exit;
        } else {
            $error = '用户名或密码错误';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Xboard-Mini - 管理员登录</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: "Microsoft YaHei", Arial, sans-serif;
        }
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .login-card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.15);
            padding: 40px 30px;
            width: 100%;
            max-width: 420px;
        }
        .login-card .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        .login-card .logo h2 {
            color: #333;
            font-size: 24px;
            font-weight: 600;
        }
        .login-card .form-group {
            margin-bottom: 20px;
        }
        .login-card .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #666;
            font-size: 14px;
        }
        .login-card .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            font-size: 16px;
            transition: all 0.3s ease;
        }
        .login-card .form-group input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102,126,234,0.1);
        }
        .login-card .error-tip {
            color: #dc2626;
            font-size: 13px;
            text-align: center;
            margin-bottom: 15px;
            padding: 8px;
            background: #fef2f2;
            border-radius: 6px;
            display: <?php echo $error ? 'block' : 'none'; ?>;
        }
        .login-card .login-btn {
            width: 100%;
            padding: 12px;
            background: #667eea;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .login-card .login-btn:hover {
            background: #556cd6;
            transform: translateY(-2px);
        }
        .login-card .copyright {
            text-align: center;
            margin-top: 25px;
            color: #9ca3af;
            font-size: 13px;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="logo">
            <h2>Xboard-Mini 管理面板</h2>
        </div>
        <?php if ($error): ?>
            <div class="error-tip"><?php echo $error; ?></div>
        <?php endif; ?>
        <form method="post" action="">
            <div class="form-group">
                <label for="username">管理员用户名</label>
                <input type="text" id="username" name="username" placeholder="请输入用户名" autofocus>
            </div>
            <div class="form-group">
                <label for="password">管理员密码</label>
                <input type="password" id="password" name="password" placeholder="请输入密码">
            </div>
            <button type="submit" class="login-btn">立即登录</button>
        </form>
        <div class="copyright">
            © 2026 Xboard-Mini 超精简版
        </div>
    </div>
</body>
</html>
