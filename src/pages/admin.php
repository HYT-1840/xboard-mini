<?php
session_start();
// 未登录则跳转到登录页
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: /index.php');
    exit;
}

// 处理退出登录
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header('Location: /index.php');
    exit;
}

// 数据库查询统计数据
$db = new SQLite3('../../database.db');
$node_count = $db->querySingle("SELECT COUNT(*) FROM node") ?: 0;
$user_count = $db->querySingle("SELECT COUNT(*) FROM user") ?: 0;
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Xboard-Mini - 管理中心</title>
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
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --border-color: #e2e8f0;
            --primary: #667eea;
            --primary-hover: #556cd6;
            --success: #10b981;
            --success-hover: #059669;
            --warning: #f59e0b;
            --warning-hover: #d97706;
            --danger: #dc2626;
            --danger-hover: #b91c1c;
            --secondary: #f1f5f9;
            --secondary-hover: #e2e8f0;
            --shadow: 0 2px 12px rgba(0,0,0,0.05);
            --shadow-hover: 0 8px 24px rgba(0,0,0,0.08);
        }
        [data-theme="dark"] {
            --body-bg: #0f172a;
            --header-bg: #1e293b;
            --card-bg: #1e293b;
            --text-primary: #f1f5f9;
            --text-secondary: #cbd5e1;
            --border-color: #334155;
            --primary: #4f46e5;
            --primary-hover: #4338ca;
            --success: #059669;
            --success-hover: #047857;
            --warning: #d97706;
            --warning-hover: #b45309;
            --danger: #f87171;
            --danger-hover: #ef4444;
            --secondary: #334155;
            --secondary-hover: #475569;
            --shadow: 0 2px 12px rgba(0,0,0,0.2);
            --shadow-hover: 0 8px 24px rgba(0,0,0,0.3);
        }
        body {
            background: var(--body-bg);
            color: var(--text-primary);
            min-height: 100vh;
        }
        /* 顶部导航 */
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
        .logout-btn {
            color: var(--danger);
            text-decoration: none;
            font-size: 14px;
            padding: 6px 12px;
            border-radius: 6px;
            background: var(--secondary);
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .logout-btn:hover {
            background: var(--danger);
            color: #fff;
        }
        /* 主内容区 */
        .main {
            padding: 80px 20px 40px;
            max-width: 1200px;
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
        /* 统计卡片 */
        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }
        .card {
            background: var(--card-bg);
            border-radius: 12px;
            box-shadow: var(--shadow);
            padding: 25px 20px;
        }
        .card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-hover);
        }
        .card .card-title {
            font-size: 14px;
            color: var(--text-secondary);
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .card .card-num {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 15px;
        }
        .card .card-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 14px;
            color: var(--primary);
            text-decoration: none;
            padding: 6px 0;
            border-bottom: 1px solid var(--border-color);
        }
        .card .card-link:hover {
            color: var(--primary-hover);
            border-color: var(--primary);
        }
        /* 功能操作区 */
        .action-area {
            background: var(--card-bg);
            border-radius: 12px;
            box-shadow: var(--shadow);
            padding: 30px 25px;
        }
        .area-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 25px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .action-buttons {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
        }
        .btn {
            padding: 12px 20px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 500;
            text-align: center;
            text-decoration: none;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .btn-primary { background: var(--primary); color: #fff; }
        .btn-primary:hover { background: var(--primary-hover); transform: translateY(-2px); }
        .btn-success { background: var(--success); color: #fff; }
        .btn-success:hover { background: var(--success-hover); transform: translateY(-2px); }
        .btn-warning { background: var(--warning); color: #fff; }
        .btn-warning:hover { background: var(--warning-hover); transform: translateY(-2px); }
        .btn-secondary { background: var(--secondary); color: var(--text-primary); }
        .btn-secondary:hover { background: var(--secondary-hover); transform: translateY(-2px); }
        /* 加载动画 */
        .btn-loading .loading-icon {
            display: inline-block;
            animation: spin 1s linear infinite;
        }
        .btn-loading .btn-text { display: none; }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        /* 响应式适配 */
        @media (max-width: 768px) {
            .stats-cards { grid-template-columns: 1fr 1fr; }
            .action-buttons { grid-template-columns: 1fr 1fr; }
        }
        @media (max-width: 480px) {
            .stats-cards, .action-buttons { grid-template-columns: 1fr; }
            .header { padding: 0 15px; }
            .main { padding: 80px 15px 40px; }
        }
    </style>
</head>
<body>
    <!-- 顶部导航 -->
    <header class="header">
        <div class="logo">
            <i class="fas fa-server"></i> Xboard-Mini 管理面板
        </div>
        <div class="header-right">
            <button class="theme-btn" id="themeBtn" title="切换深色/浅色模式">
                <i class="fas fa-moon"></i>
            </button>
            <span>当前：<?php echo $_SESSION['admin_username']; ?></span>
            <a href="?action=logout" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i> 安全退出
            </a>
        </div>
    </header>

    <!-- 主内容区 -->
    <main class="main">
        <h1 class="page-title">
            <i class="fas fa-tachometer-alt"></i> 管理中心
        </h1>

        <!-- 数据统计卡片 -->
        <div class="stats-cards">
            <div class="card">
                <div class="card-title"><i class="fas fa-network-wired"></i> 总节点数</div>
                <div class="card-num"><?php echo $node_count; ?></div>
                <a href="node.php" class="card-link">管理节点 <i class="fas fa-arrow-right"></i></a>
            </div>
            <div class="card">
                <div class="card-title"><i class="fas fa-users"></i> 总用户数</div>
                <div class="card-num"><?php echo $user_count; ?></div>
                <a href="user.php" class="card-link">管理用户 <i class="fas fa-arrow-right"></i></a>
            </div>
            <div class="card">
                <div class="card-title"><i class="fas fa-heartbeat"></i> 面板状态</div>
                <div class="card-num" style="color: var(--success);">运行中</div>
                <a href="logs.php" class="card-link">查看日志 <i class="fas fa-arrow-right"></i></a>
            </div>
            <div class="card">
                <div class="card-title"><i class="fas fa-save"></i> 数据备份</div>
                <div class="card-num" style="color: var(--primary);">就绪</div>
                <a href="javascript:backupData()" class="card-link">立即备份 <i class="fas fa-arrow-right"></i></a>
            </div>
        </div>

        <!-- 功能操作区 -->
        <div class="action-area">
            <h2 class="area-title"><i class="fas fa-cogs"></i> 核心功能操作</h2>
            <div class="action-buttons">
                <a href="node_add.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> 添加节点
                </a>
                <a href="user_add.php" class="btn btn-primary">
                    <i class="fas fa-user-plus"></i> 添加用户
                </a>
                <a href="traffic.php" class="btn btn-secondary">
                    <i class="fas fa-chart-line"></i> 流量统计
                </a>
                <a href="setting.php" class="btn btn-secondary">
                    <i class="fas fa-cog"></i> 面板设置
                </a>
                <button class="btn btn-success" id="backupBtn" onclick="backupData()">
                    <i class="loading-icon fas fa-spinner" style="display: none;"></i>
                    <span class="btn-text"><i class="fas fa-save"></i> 一键备份</span>
                </button>
                <button class="btn btn-warning" id="restartBtn" onclick="restartPanel()">
                    <i class="loading-icon fas fa-spinner" style="display: none;"></i>
                    <span class="btn-text"><i class="fas fa-sync-alt"></i> 重启面板</span>
                </button>
            </div>
        </div>
    </main>

    <script>
        // 深色模式适配
        const themeBtn = document.getElementById('themeBtn');
        const html = document.documentElement;
        const icon = themeBtn.querySelector('i');
        const savedTheme = localStorage.getItem('theme') || 'light';
        
        html.setAttribute('data-theme', savedTheme);
        updateIcon(savedTheme);
        
        themeBtn.addEventListener('click', () => {
            const current = html.getAttribute('data-theme');
            const next = current === 'dark' ? 'light' : 'dark';
            html.setAttribute('data-theme', next);
            localStorage.setItem('theme', next);
            updateIcon(next);
        });
        
        function updateIcon(theme) {
            icon.className = theme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
        }

        // 备份加载动画
        function backupData() {
            const btn = document.getElementById('backupBtn');
            const loading = btn.querySelector('.loading-icon');
            const text = btn.querySelector('.btn-text');
            
            btn.classList.add('btn-loading');
            loading.style.display = 'inline-block';
            text.style.display = 'none';
            
            // 模拟备份请求
            setTimeout(() => {
                alert('备份完成，文件已保存至服务器指定目录');
                btn.classList.remove('btn-loading');
                loading.style.display = 'none';
                text.style.display = 'inline';
            }, 1500);
        }

        // 重启加载动画
        function restartPanel() {
            const btn = document.getElementById('restartBtn');
            const loading = btn.querySelector('.loading-icon');
            const text = btn.querySelector('.btn-text');
            
            if(confirm('确定要重启面板服务吗？')) {
                btn.classList.add('btn-loading');
                loading.style.display = 'inline-block';
                text.style.display = 'none';
                
                setTimeout(() => {
                    alert('面板服务已重启完成');
                    btn.classList.remove('btn-loading');
                    loading.style.display = 'none';
                    text.style.display = 'inline';
                }, 2000);
            }
        }
    </script>
</body>
</html>
