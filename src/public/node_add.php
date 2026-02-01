<?php
session_start();
// 权限验证：未登录跳转到登录页
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: /index.php');
    exit;
}

$success = '';
$error = '';
// 处理添加节点请求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $host = trim($_POST['host'] ?? ''); // 修正：字段名从address改为host（匹配数据库）
    $port = intval($_POST['port'] ?? 0);
    $protocol = trim($_POST['protocol'] ?? ''); // 新增：处理数据库必选的protocol字段
    $remark = trim($_POST['remark'] ?? '');
    $status = intval($_POST['status'] ?? 1); // 默认启用节点

    // 表单验证（保留原始逻辑，新增协议非空验证）
    if (empty($name) || empty($host) || $port <= 0 || $port > 65535 || empty($protocol)) {
        $error = '节点名称、地址、协议不能为空，端口必须为1-65535的有效数字';
    } else {
        try {
            // 连接数据库，保持原有路径
            $db = new SQLite3('../database.db');
            // 开启外键约束，与数据库初始化脚本、其他页面保持一致
            $db->exec("PRAGMA foreign_keys = ON;");

            // 核心修正1：表名从node改为nodes（匹配数据库实际表名）
            // 核心修正2：字段完全匹配nodes表结构，替换/补充必选字段
            $stmt = $db->prepare("INSERT INTO nodes (name, host, port, protocol, remark, status, created_at) 
                                  VALUES (:name, :host, :port, :protocol, :remark, :status, CURRENT_TIMESTAMP)");
            $stmt->bindValue(':name', $name, SQLITE3_TEXT);
            $stmt->bindValue(':host', $host, SQLITE3_TEXT);
            $stmt->bindValue(':port', $port, SQLITE3_INTEGER);
            $stmt->bindValue(':protocol', $protocol, SQLITE3_TEXT);
            $stmt->bindValue(':remark', $remark, SQLITE3_TEXT);
            $stmt->bindValue(':status', $status, SQLITE3_INTEGER);

            if ($stmt->execute()) {
                $success = '节点添加成功！3秒后将返回节点管理页';
                echo '<script>setTimeout(() => { window.location.href = "node.php"; }, 3000);</script>';
            } else {
                $error = '节点添加失败，该节点地址+端口可能已存在';
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
    <title>Xboard-Mini - 添加节点</title>
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
        .form-switch {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 5px;
        }
        .form-switch input {
            width: 40px;
            height: 20px;
            appearance: none;
            background: var(--secondary);
            border-radius: 10px;
            position: relative;
            cursor: pointer;
        }
        .form-switch input:checked {
            background: var(--success);
        }
        .form-switch input::after {
            content: '';
            position: absolute;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            background: #fff;
            top: 2px;
            left: 2px;
            transition: left 0.3s ease;
        }
        .form-switch input:checked::after {
            left: 22px;
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
            <a href="node.php" class="back-btn">
                <i class="fas fa-arrow-left"></i> 返回节点管理
            </a>
        </div>
    </header>

    <main class="main">
        <h1 class="page-title">
            <i class="fas fa-plus-circle"></i> 添加新节点
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

            <form method="post" action="" id="addNodeForm">
                <div class="form-row">
                    <div class="form-group">
                        <label for="name"><i class="fas fa-tag"></i> 节点名称 <span style="color: var(--danger);">*</span></label>
                        <input type="text" class="form-control" id="name" name="name" placeholder="请输入节点名称（如：北京节点-联通）" required>
                        <div class="form-tip">节点标识名称，方便后续管理区分</div>
                    </div>
                    <!-- 修正：输入框name从address改为host（匹配数据库字段），标签文字不变不影响使用 -->
                    <div class="form-group">
                        <label for="host"><i class="fas fa-globe"></i> 节点地址 <span style="color: var(--danger);">*</span></label>
                        <input type="text" class="form-control" id="host" name="host" placeholder="请输入节点IP/域名" required>
                        <div class="form-tip">支持公网IP、内网IP或域名，请勿加http/https</div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="port"><i class="fas fa-portrait"></i> 节点端口 <span style="color: var(--danger);">*</span></label>
                        <input type="number" class="form-control" id="port" name="port" placeholder="请输入节点端口" min="1" max="65535" required>
                        <div class="form-tip">有效端口范围：1-65535，需确保节点服务器该端口已开放</div>
                    </div>
                    <!-- 新增：协议选择框（数据库必选字段，提供常用协议选项） -->
                    <div class="form-group">
                        <label for="protocol"><i class="fas fa-network-wired"></i> 节点协议 <span style="color: var(--danger);">*</span></label>
                        <select class="form-control" id="protocol" name="protocol" required>
                            <option value="">请选择节点协议</option>
                            <option value="TCP">TCP</option>
                            <option value="UDP">UDP</option>
                            <option value="HTTP">HTTP</option>
                            <option value="HTTPS">HTTPS</option>
                            <option value="WS">WS</option>
                            <option value="WSS">WSS</option>
                        </select>
                        <div class="form-tip">选择节点使用的通信协议，需与节点服务器配置一致</div>
                    </div>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-toggle-on"></i> 节点状态</label>
                    <div class="form-switch">
                        <input type="checkbox" id="status" name="status" checked value="1">
                        <label for="status" style="margin: 0; color: var(--text-secondary);">启用节点（取消则禁用）</label>
                    </div>
                    <div class="form-tip">禁用后用户将无法访问该节点</div>
                </div>

                <div class="form-group">
                    <label for="remark"><i class="fas fa-comment"></i> 节点备注</label>
                    <textarea class="form-control" id="remark" name="remark" rows="3" placeholder="请输入节点备注（选填）"></textarea>
                    <div class="form-tip">如：节点线路、带宽、到期时间等，方便后续管理</div>
                </div>

                <div class="form-btn-group">
                    <button type="submit" class="btn btn-primary" id="submitBtn">
                        <i class="loading-spin fas fa-spinner" id="loadingIcon"></i>
                        <span id="btnText"><i class="fas fa-save"></i> 保存节点</span>
                    </button>
                    <a href="node.php" class="btn btn-secondary">
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

        const addNodeForm = document.getElementById('addNodeForm');
        const submitBtn = document.getElementById('submitBtn');
        const loadingIcon = document.getElementById('loadingIcon');
        const btnText = document.getElementById('btnText');

        addNodeForm.addEventListener('submit', () => {
            submitBtn.disabled = true;
            loadingIcon.style.display = 'inline-block';
            btnText.innerHTML = '<i class="fas fa-spinner"></i> 保存中...';
        });
    </script>
</body>
</html>
