<?php
require_once 'config.php';
checkAdmin();

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $traffic_quota = (int)$_POST['traffic_quota'];
    $status = (int)$_POST['status'];

    if (empty($username)) {
        $msg = '<span style="color: #dc2626;">用户名不能为空</span>';
    } elseif ($traffic_quota < 0) {
        $msg = '<span style="color: #dc2626;">流量配额不能为负数</span>';
    } else {
        try {
            $db = getDB();
            // 检查用户名重复
            $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->rowCount() > 0) {
                $msg = '<span style="color: #dc2626;">用户名已存在</span>';
            } else {
                $insert = $db->prepare("INSERT INTO users (username, traffic_quota, traffic_used, status) 
                                        VALUES (?, ?, 0, ?)");
                $insert->execute([$username, $traffic_quota, $status]);
                $msg = '<span style="color: #10b981;">用户添加成功，3秒后跳转到用户列表</span>';
                echo "<meta http-equiv='refresh' content='3;url=user.php'>";
            }
        } catch (PDOException $e) {
            $msg = '<span style="color: #dc2626;">数据库错误：' . $e->getMessage() . '</span>';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>添加用户 - Xboard-Mini</title>
    <link rel="stylesheet" href="https://cdn.bootcdn.net/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
        font-family: "Microsoft Yahei", sans-serif;
        transition: background 0.3s, border-color 0.3s, color 0.3s;
    }

    :root {
        --body-bg: #f8fafc;
        --card-bg: #fff;
        --text-primary: #1e293b;
        --text-secondary: #64748b;
        --border-color: #e2e8f0;
        --primary: #64748b;
        --secondary: #f1f5f9;
        --border-radius: 8px;
        --shadow: 0 2px 12px rgba(0, 0, 0, 0.05);
    }

    [data-theme="dark"] {
        --body-bg: #0f172a;
        --card-bg: #1e293b;
        --text-primary: #f1f5f9;
        --text-secondary: #cbd5e1;
        --border-color: #334155;
        --primary: #4f46e5;
        --secondary: #334155;
    }

    body {
        background: var(--body-bg);
        color: var(--text-primary);
        padding: 20px;
    }

    .container {
        max-width: 600px;
        margin: 60px auto;
        background: var(--card-bg);
        padding: 30px;
        border-radius: 12px;
        box-shadow: var(--shadow);
    }

    h2 {
        margin-bottom: 24px;
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 20px;
    }

    .form-group {
        margin-bottom: 18px;
    }

    label {
        display: block;
        margin-bottom: 6px;
        font-weight: 500;
        color: var(--text-secondary);
    }

    input,
    select {
        width: 100%;
        padding: 10px 12px;
        border: 1px solid var(--border-color);
        border-radius: var(--border-radius);
        background: var(--secondary);
        color: var(--text-primary);
        font-size: 14px;
        outline: none;
    }

    input:focus,
    select:focus {
        border-color: var(--primary);
    }

    .btn-submit {
        width: 100%;
        padding: 10px;
        background: var(--primary);
        color: #fff;
        border: none;
        border-radius: var(--border-radius);
        font-size: 15px;
        cursor: pointer;
    }

    .btn-submit:hover {
        opacity: 0.9;
    }

    .msg {
        padding: 10px 12px;
        border-radius: var(--border-radius);
        margin-bottom: 16px;
        font-size: 14px;
    }

    .back-link {
        display: inline-block;
        margin-top: 16px;
        color: var(--primary);
        text-decoration: none;
    }

    .back-link:hover {
        text-decoration: underline;
    }
    </style>
</head>
<body>
<div class="container">
    <h2><i class="fas fa-user-plus"></i> 添加新用户</h2>

    <?php if ($msg): ?>
        <div class="msg"><?= $msg ?></div>
    <?php endif; ?>

    <form method="post" autocomplete="off">
        <div class="form-group">
            <label>用户名</label>
            <input type="text" name="username" required placeholder="请输入唯一用户名">
        </div>

        <div class="form-group">
            <label>流量配额（MB）</label>
            <input type="number" name="traffic_quota" min="0" value="1024" required placeholder="单位：MB">
        </div>

        <div class="form-group">
            <label>用户状态</label>
            <select name="status">
                <option value="1">启用</option>
                <option value="0">禁用</option>
            </select>
        </div>

        <button type="submit" class="btn-submit">创建用户</button>
    </form>

    <a href="user.php" class="back-link"><i class="fas fa-arrow-left"></i> 返回用户列表</a>
</div>
</body>
</html>            --card-bg: #1e293b;
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
