<?php
session_start();
// 权限验证：未登录跳转到登录页
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: /index.php');
    exit;
}

$success = '';
$error = '';
// 处理添加用户请求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 获取表单所有字段（保留原始表单的email/expire_time/remark，新增流量配额）
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $expire_time = trim($_POST['expire_time'] ?? '');
    $remark = trim($_POST['remark'] ?? '');
    $traffic_quota = intval($_POST['traffic_quota'] ?? 10); // 流量配额，默认10GB

    // 表单验证（保留原始验证逻辑，新增流量配额非负验证）
    if (empty($username) || empty($password)) {
        $error = '用户名和密码不能为空';
    } elseif (strlen($username) < 3 || strlen($username) > 20) {
        $error = '用户名长度需在3-20位之间';
    } elseif (strlen($password) < 6) {
        $error = '密码长度不能少于6位';
    } elseif (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = '请输入有效的邮箱地址';
    } elseif ($traffic_quota < 0) {
        $error = '流量配额不能为负数';
    } else {
        try {
            // 连接数据库（路径保持你的原始配置）
            $db = new SQLite3('../database.db');
            // 开启外键约束（与数据库脚本保持一致）
            $db->exec("PRAGMA foreign_keys = ON;");
            // 密码加密（保留原始加密方式）
            $pwd_hash = password_hash($password, PASSWORD_DEFAULT);

            // 核心修正1：表名改为数据库实际的`users`（复数）
            // 核心修正2：字段完全匹配数据库`users`表结构，补充流量配额核心字段
            $stmt = $db->prepare("INSERT INTO users (username, password, traffic_quota, traffic_used, status, created_at) 
                                  VALUES (:username, :password, :traffic_quota, :traffic_used, :status, CURRENT_TIMESTAMP)");
            $stmt->bindValue(':username', $username, SQLITE3_TEXT);
            $stmt->bindValue(':password', $pwd_hash, SQLITE3_TEXT);
            $stmt->bindValue(':traffic_quota', $traffic_quota, SQLITE3_INTEGER);
            $stmt->bindValue(':traffic_used', 0, SQLITE3_INTEGER); // 已用流量默认0
            $stmt->bindValue(':status', 1, SQLITE3_INTEGER); // 状态默认启用

            if ($stmt->execute()) {
                $success = '用户添加成功！3秒后将返回用户管理页';
                echo '<script>setTimeout(() => { window.location.href = "user.php"; }, 3000);</script>';
            } else {
                // 精准提示：用户名唯一约束冲突
                $error = '用户添加失败，用户名已存在（用户名唯一）';
            }
        } catch (Exception $e) {
            $error = '服务器内部错误：' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Xboard-Mini - 添加用户</title>
    <link rel="stylesheet" href="https://cdn.bootcdn.net/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: "Microsoft YaHei", Arial, sans-serif;
            transition: background 0.3s ease, border-color 0.3s ease, color 0.3s ease, transform 0.2s ease;
        }
        :root {
            --body-bg: #f8fafc;
            --header-bg: #ffffff;
            --card-bg: #ffffff;
            --form-input-bg: #ffffff;
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --text-muted: #94a3b8;
            --border-color: #e2e8f0;
            --input-focus: #667eea;
            --primary: #667eea;
            --primary-hover: #556cd6;
            --success: #10b981;
            --success-hover: #059669;
            --danger: #dc2626;
            --danger-hover: #b91c1c;
            --secondary: #f1f5f9;
            --secondary-hover: #e2e8f0;
            --success-bg: #f0fdf4;
            --error-bg: #fef2f2;
            --shadow: 0 2px 12px rgba(0,0,0,0.05);
            --border-radius-sm: 6px;
            --border-radius: 8px;
            --border-radius-lg: 12px;
        }
        [data-theme="dark"] {
            --body-bg: #0f172a;
            --header-bg: #1e293b;
            --card-bg: #1e293b;
            --form-input-bg: #1e293b;
            --text-primary: #f1f5f9;
            --text-secondary: #cbd5e1;
            --text-muted: #94a3b8;
            --border-color: #334155;
            --input-focus: #818cf8;
            --primary: #4f46e5;
            --primary-hover: #4338ca;
            --success: #059669;
            --success-hover: #047857;
            --danger: #f87171;
            --danger-hover: #ef4444;
            --secondary: #334155;
            --secondary-hover: #475569;
            --success-bg: #0f172a;
            --error-bg: #450a0a;
            --shadow: 0 2px 12px rgba(0,0,0,0.2);
        }
        body {
            background: var(--body-bg);
            color: var(--text-primary);
            min-height: 100vh;
        }
        .header {
            background: var(--header-bg);
            box-shadow: var(--shadow);
            padding: 0 20px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 100;
        }
        .header .logo {
            font-size: 18px;
            font-weight: 600;
            color: var(--primary);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .header-right {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .theme-btn {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            border: none;
            background: var(--secondary);
            color: var(--text-primary);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .back-btn {
            color: var(--text-primary);
            text-decoration: none;
            font-size: 14px;
            padding: 6px 12px;
            border-radius: var(--border-radius-sm);
            background: var(--secondary);
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .back-btn:hover {
            background: var(--secondary-hover);
        }
        .main {
            padding: 80px 20px 40px;
            max-width: 800px;
            margin: 0 auto;
            width: 100%;
        }
        .page-title {
            font-size: 22px;
            font-weight: 600;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .card {
            background: var(--card-bg);
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow);
            padding: 30px;
            width: 100%;
        }
        .form-alert {
            padding: 12px 15px;
            border-radius: var(--border-radius-sm);
            font-size: 14px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .alert-success {
            background: var(--success-bg);
            color: var(--success);
            border: 1px solid var(--success);
        }
        .alert-error {
            background: var(--error-bg);
            color: var(--danger);
            border: 1px solid var(--danger);
        }
        .form-group {
            margin-bottom: 25px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-size: 14px;
            font-weight: 500;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .form-group .form-tip {
            font-size: 12px;
            color: var(--text-muted);
            margin-top: 4px;
        }
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            font-size: 14px;
            background: var(--form-input-bg);
            color: var(--text-primary);
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }
        .form-control:focus {
            outline: none;
            border-color: var(--input-focus);
            box-shadow: 0 0 0 3px rgba(102,126,234,0.1);
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        .btn {
            padding: 12px 25px;
            border-radius: var(--border-radius);
            font-size: 14px;
            font-weight: 500;
            text-align: center;
            text-decoration: none;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-right: 15px;
        }
        .btn-primary { background: var(--primary); color: #fff; }
        .btn-primary:hover { background: var(--primary-hover); transform: translateY(-2px); }
        .btn-secondary { background: var(--secondary); color: var(--text-primary); }
        .btn-secondary:hover { background: var(--secondary-hover); transform: translateY(-2px); }
        .loading-spin {
            display: none;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        @media (max-width: 768px) {
            .main {
                padding: 80px 15px 40px;
            }
            .card {
                padding: 25px;
            }
            .form-row {
                grid-template-columns: 1fr;
                gap: 25px;
            }
        }
        @media (max-width: 480px) {
            .header {
                padding: 0 10px;
            }
            .page-title {
                font-size: 20px;
            }
            .btn {
                padding: 10px 20px;
                margin-right: 10px;
                margin-bottom: 10px;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="logo">
            <i class="fas fa-server"></i> Xboard-Mini 管理面板
        </div>
        <div class="header-right">
            <button class="theme-btn" id="themeBtn" title="切换深色/浅色模式">
                <i class="fas fa-moon"></i>
            </button>
            <a href="user.php" class="back-btn">
                <i class="fas fa-arrow-left"></i> 返回用户管理
            </a>
        </div>
    </header>

    <main class="main">
        <h1 class="page-title">
            <i class="fas fa-user-plus"></i> 添加新用户
        </h1>

        <div class="card">
            <?php if ($success): ?>
                <div class="form-alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                </div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="form-alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <form method="post" action="" id="addUserForm">
                <div class="form-row">
                    <div class="form-group">
                        <label for="username"><i class="fas fa-user"></i> 用户名 <span style="color: var(--danger);">*</span></label>
                        <input type="text" class="form-control" id="username" name="username" placeholder="请输入3-20位用户名" required>
                        <div class="form-tip">用户名由字母、数字、下划线组成，长度3-20位</div>
                    </div>
                    <div class="form-group">
                        <label for="password"><i class="fas fa-lock"></i> 密码 <span style="color: var(--danger);">*</span></label>
                        <input type="password" class="form-control" id="password" name="password" placeholder="请输入至少6位密码" required>
                        <div class="form-tip">建议包含字母、数字，提高密码安全性</div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="email"><i class="fas fa-envelope"></i> 邮箱</label>
                        <input type="email" class="form-control" id="email" name="email" placeholder="请输入用户邮箱（选填）">
                        <div class="form-tip">用于接收用户通知、密码找回等</div>
                    </div>
                    <!-- 新增：流量配额字段（贴合项目核心功能，必填） -->
                    <div class="form-group">
                        <label for="traffic_quota"><i class="fas fa-tachometer-alt"></i> 流量配额(GB) <span style="color: var(--danger);">*</span></label>
                        <input type="number" class="form-control" id="traffic_quota" name="traffic_quota" placeholder="请输入流量配额" value="10" min="0" required>
                        <div class="form-tip">设置用户可用流量，0为无配额限制</div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="expire_time"><i class="fas fa-calendar"></i> 过期时间</label>
                        <input type="datetime-local" class="form-control" id="expire_time" name="expire_time" value="<?php echo date('Y-m-d\TH:i', strtotime('+30 days')); ?>">
                        <div class="form-tip">未填写默认30天后过期，永久有效请留空</div>
                    </div>
                    <div class="form-group">
                        <label for="remark"><i class="fas fa-comment"></i> 备注</label>
                        <input type="text" class="form-control" id="remark" name="remark" placeholder="请输入用户备注（选填）">
                        <div class="form-tip">如用户身份、用途等，方便后续管理</div>
                    </div>
                </div>

                <div class="form-btn-group">
                    <button type="submit" class="btn btn-primary" id="submitBtn">
                        <i class="loading-spin fas fa-spinner" id="loadingIcon"></i>
                        <span id="btnText"><i class="fas fa-save"></i> 保存用户</span>
                    </button>
                    <a href="user.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> 取消返回
                    </a>
                </div>
            </form>
        </div>
    </main>

    <script>
        const themeBtn = document.getElementById('themeBtn');
        const html = document.documentElement;
        const icon = themeBtn.querySelector('i');
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
            icon.className = theme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
        }

        const addUserForm = document.getElementById('addUserForm');
        const submitBtn = document.getElementById('submitBtn');
        const loadingIcon = document.getElementById('loadingIcon');
        const btnText = document.getElementById('btnText');

        addUserForm.addEventListener('submit', () => {
            submitBtn.disabled = true;
            loadingIcon.style.display = 'inline-block';
            btnText.innerHTML = '<i class="fas fa-spinner"></i> 保存中...';
        });
    </script>
</body>
</html>
