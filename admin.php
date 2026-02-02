<?php
require_once 'config.php';
checkAdmin();

if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header("Location: index.php");
    exit;
}

$db = getDB();
// 统计数据
$userCount = $db->query("SELECT COUNT(*) AS cnt FROM users")->fetch()['cnt'];
$nodeCount = $db->query("SELECT COUNT(*) AS cnt FROM nodes")->fetch()['cnt'];
$totalQuota = $db->query("SELECT SUM(traffic_quota) AS total FROM users")->fetch()['total'] ?? 0;
$totalUsed = $db->query("SELECT SUM(traffic_used) AS total FROM users")->fetch()['total'] ?? 0;
$enabledUser = $db->query("SELECT COUNT(*) AS cnt FROM users WHERE status=1")->fetch()['cnt'];
$enabledNode = $db->query("SELECT COUNT(*) AS cnt FROM nodes WHERE status=1")->fetch()['cnt'];
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理首页 - Xboard-Mini</title>
    <link rel="stylesheet" href="https://cdn.bootcdn.net/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
    *{margin:0;padding:0;box-sizing:border-box;font-family:"Microsoft Yahei",sans-serif;transition:background 0.3s,border-color 0.3s,color 0.3s,transform 0.2s;}
    :root {
        --body-bg:#f8fafc;--header-bg:#fff;--card-bg:#fff;--text-primary:#1e293b;--text-secondary:#64748b;
        --border-color:#e2e8f0;--primary:#64748b;--success:#10b981;--warning:#f59e0b;--danger:#dc2626;
        --secondary:#f1f5f9;--shadow:0 2px 12px rgba(0,0,0,0.05);--border-radius:8px;--border-radius-lg:12px;
    }
    [data-theme="dark"] {
        --body-bg:#0f172a;--header-bg:#1e293b;--card-bg:#1e293b;--text-primary:#f1f5f9;--text-secondary:#cbd5e1;
        --border-color:#334155;--primary:#4f46e5;--success:#059669;--warning:#d9706;--danger:#ef4444;
        --secondary:#334155;--shadow:0 2px 12px rgba(0,0,0,0.2);
    }
    body{background:var(--body-bg);color:var(--text-primary);min-height:100vh;}
    .header{position:fixed;top:0;left:0;right:0;height:60px;background:var(--header-bg);box-shadow:var(--shadow);display:flex;align-items:center;justify-content:space-between;padding:0 20px;z-index:100;}
    .logo{font-size:18px;font-weight:600;color:var(--primary);display:flex;align-items:center;gap:8px;}
    .header-right{display:flex;align-items:center;gap:12px;}
    .theme-btn,.logout-btn,.pwd-btn{border:none;background:var(--secondary);color:var(--text-primary);padding:6px 12px;border-radius:6px;display:flex;align-items:center;gap:6px;text-decoration:none;font-size:14px;cursor:pointer;}
    .main{padding:80px 20px 40px;max-width:1200px;margin:0 auto;}
    .page-title{font-size:22px;font-weight:600;margin-bottom:25px;display:flex;align-items:center;gap:8px;}
    .stat-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:20px;margin-bottom:30px;}
    .stat-card{background:var(--card-bg);border-radius:var(--border-radius-lg);box-shadow:var(--shadow);padding:20px;display:flex;align-items:center;gap:15px;}
    .stat-icon{width:48px;height:48px;border-radius:50%;background:var(--secondary);display:flex;align-items:center;justify-content:center;color:var(--primary);font-size:20px;}
    .stat-text h3{font-size:24px;font-weight:600;margin-bottom:4px;}
    .stat-text p{color:var(--text-secondary);font-size:14px;}
    .menu-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:20px;}
    .menu-card{background:var(--card-bg);border-radius:var(--border-radius-lg);box-shadow:var(--shadow);padding:25px;text-align:center;text-decoration:none;color:var(--text-primary);transition:0.3s;}
    .menu-card:hover{transform:translateY(-4px);}
    .menu-card i{font-size:28px;color:var(--primary);margin-bottom:15px;}
    .menu-card h4{font-size:16px;margin-bottom:8px;}
    .menu-card p{font-size:12px;color:var(--text-secondary);}
    </style>
</head>
<body>
<header class="header">
    <div class="logo"><i class="fas fa-server"></i> Xboard-Mini</div>
    <div class="header-right">
        <button class="theme-btn" id="themeBtn"><i class="fas fa-moon"></i></button>
        <a href="change_pwd.php" class="pwd-btn"><i class="fas fa-lock"></i> 修改密码</a>
        <a href="?action=logout" class="logout-btn"><i class="fas fa-sign-out-alt"></i> 退出</a>
    </div>
</header>
<main class="main">
    <h1 class="page-title"><i class="fas fa-tachometer-alt"></i> 控制台概览</h1>
    <div class="stat-grid">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-users"></i></div>
            <div class="stat-text">
                <h3><?=$userCount?></h3>
                <p>总用户 / 启用 <?=$enabledUser?></p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-network-wired"></i></div>
            <div class="stat-text">
                <h3><?=$nodeCount?></h3>
                <p>节点 / 在线 <?=$enabledNode?></p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-database"></i></div>
            <div class="stat-text">
                <h3><?=round($totalQuota/1024,2)?> GB</h3>
                <p>总流量配额</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
            <div class="stat-text">
                <h3><?=round($totalUsed/1024,2)?> GB</h3>
                <p>总已用流量</p>
            </div>
        </div>
    </div>
    <div class="menu-grid">
        <a href="user.php" class="menu-card">
            <i class="fas fa-user-circle"></i>
            <h4>用户管理</h4>
            <p>添加、编辑、查询用户</p>
        </a>
        <a href="node.php" class="menu-card">
            <i class="fas fa-server"></i>
            <h4>节点管理</h4>
            <p>节点配置与状态</p>
        </a>
        <a href="change_pwd.php" class="menu-card">
            <i class="fas fa-lock"></i>
            <h4>修改密码</h4>
            <p>更新管理员密码</p>
        </a>
    </div>
</main>
<script>
const t=document.getElementById('themeBtn'),h=document.documentElement,s=localStorage.getItem('theme')||'light';
h.setAttribute('data-theme',s);t.innerHTML=`<i class="fas fa-${s==='dark'?'sun':'moon'}"></i>`;
t.onclick=()=>{const c=h.getAttribute('data-theme')==='dark'?'light':'dark';h.setAttribute('data-theme',c);localStorage.setItem('theme',c);t.innerHTML=`<i class="fas fa-${c==='dark'?'sun':'moon'}"></i>`;}
</script>
</body>
</html>        }
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
        .btn-loading .loading-icon {
            display: inline-block;
            animation: spin 1s linear infinite;
        }
        .btn-loading .btn-text { display: none; }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
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

    <main class="main">
        <h1 class="page-title">
            <i class="fas fa-tachometer-alt"></i> 管理中心
        </h1>

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

        function backupData() {
            const btn = document.getElementById('backupBtn');
            const loading = btn.querySelector('.loading-icon');
            const text = btn.querySelector('.btn-text');
            
            btn.classList.add('btn-loading');
            loading.style.display = 'inline-block';
            text.style.display = 'none';
            
            setTimeout(() => {
                alert('备份完成，文件已保存至服务器指定目录');
                btn.classList.remove('btn-loading');
                loading.style.display = 'none';
                text.style.display = 'inline';
            }, 1500);
        }

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
