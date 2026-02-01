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
        $db = new SQLite3('../database.db');
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
    <!-- 引入轻量图标库 -->
    <link rel="stylesheet" href="https://cdn.bootcdn.net/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: "Microsoft YaHei", Arial, sans-serif;
            transition: background 0.3s ease, border-color 0.3s ease, color 0.3s ease;
        }
        :root {
            --bg-gradient-start: #667eea;
            --bg-gradient-end: #764ba2;
            --card-bg: #ffffff;
            --input-border: #e5e7eb;
            --input-focus: #667eea;
            --text-main: #333;
            --text-secondary: #666;
            --text-copyright: #9ca3af;
            --btn-primary: #667eea;
            --btn-hover: #556cd6;
            --error-bg: #fef2f2;
            --error-text: #dc2626;
        }
        [data-theme="dark"] {
            --bg-gradient-start: #1e293b;
            --bg-gradient-end: #0f172a;
            --card-bg: #1e293b;
            --input-border: #334155;
            --input-focus: #818cf8;
            --text-main: #f1f5f9;
            --text-secondary: #cbd5e1;
            --text-copyright: #64748b;
            --btn-primary: #4f46e5;
            --btn-hover: #4338ca;
            --error-bg: #450a0a;
            --error-text: #fca5a5;
        }
        body {
            background: linear-gradient(135deg, var(--bg-gradient-start) 0%, var(--bg-gradient-end) 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
        }
        /* 深色模式切换按钮 */
        .theme-toggle {
            position: absolute;
            top: 20px;
            right: 20px;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            border: none;
            background: var(--card-bg);
            color: var(--text-main);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .login-card {
            background: var(--card-bg);
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
            color: var(--text-main);
            font-size: 24px;
            font-weight: 600;
        }
        .login-card .form-group {
            margin-bottom: 20px;
        }
        .login-card .form-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-secondary);
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .login-card .form-group input {
            width: 100%;
            padding: 12px 15px 12px 40px;
            border: 1px solid var(--input-border);
            border-radius: 8px;
            font-size: 16px;
            background: var(--card-bg);
            color: var(--text-main);
        }
        .login-card .form-group input:focus {
            outline: none;
            border-color: var(--input-focus);
            box-shadow: 0 0 0 3px rgba(102,126,234,0.1);
        }
        .input-icon {
            position: relative;
        }
        .input-icon i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
        }
        .error-tip {
            color: var(--error-text);
            font-size: 13px;
            text-align: center;
            margin-bottom: 15px;
            padding: 8px;
            background: var(--error-bg);
            border-radius: 6px;
            display: <?php echo $error ? 'block' : 'none'; ?>;
        }
        .login-btn {
            width: 100%;
            padding: 12px;
            background: var(--btn-primary);
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .login-btn:hover {
            background: var(--btn-hover);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102,126,234,0.2);
        }
        /* 加载动画 */
        .loading-spin {
            display: none;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .login-card .copyright {
            text-align: center;
            margin-top: 25px;
            color: var(--text-copyright);
            font-size: 13px;
        }
    </style>
</head>
<body>
    <!-- 深色模式切换 -->
    <button class="theme-toggle" id="themeBtn" title="切换深色/浅色模式">
        <i class="fas fa-moon"></i>
    </button>

    <div class="login-card">
        <div class="logo">
            <h2><i class="fas fa-server"></i> Xboard-Mini 管理面板</h2>
        </div>
        <?php if ($error): ?>
            <div class="error-tip"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
        <?php endif; ?>
        <form method="post" action="" id="loginForm">
            <div class="form-group">
                <label for="username"><i class="fas fa-user"></i> 管理员用户名</label>
                <div class="input-icon">
                    <i class="fas fa-user"></i>
                    <input type="text" id="username" name="username" placeholder="请输入用户名" autofocus>
                </div>
            </div>
            <div class="form-group">
                <label for="password"><i class="fas fa-lock"></i> 管理员密码</label>
                <div class="input-icon">
                    <i class="fas fa-lock"></i>
                    <input type="password" id="password" name="password" placeholder="请输入密码">
                </div>
            </div>
            <button type="submit" class="login-btn" id="submitBtn">
                <i class="loading-spin" id="loadingIcon"></i>
                <span id="btnText">立即登录</span>
            </button>
        </form>
        <div class="copyright">
            © 2026 Xboard-Mini 超精简版
        </div>
    </div>

    <script>
        // 深色模式切换
        const themeBtn = document.getElementById('themeBtn');
        const html = document.documentElement;
        const icon = themeBtn.querySelector('i');
        
        // 读取本地存储主题
        const savedTheme = localStorage.getItem('theme') || 'light';
        html.setAttribute('data-theme', savedTheme);
        updateThemeIcon(savedTheme);
        
        themeBtn.addEventListener('click', () => {
            const current = html.getAttribute('data-theme');
            const next = current === 'dark' ? 'light' : 'dark';
            html.setAttribute('data-theme', next);
            localStorage.setItem('theme', next);
            updateThemeIcon(next);
        });
        
        function updateThemeIcon(theme) {
            if (theme === 'dark') {
                icon.className = 'fas fa-sun';
            } else {
                icon.className = 'fas fa-moon';
            }
        }

        // 登录加载动画
        const loginForm = document.getElementById('loginForm');
        const submitBtn = document.getElementById('submitBtn');
        const loadingIcon = document.getElementById('loadingIcon');
        const btnText = document.getElementById('btnText');

        loginForm.addEventListener('submit', () => {
            submitBtn.disabled = true;
            loadingIcon.style.display = 'inline-block';
            btnText.textContent = '登录中...';
        });
    </script>
</body>
</html>
