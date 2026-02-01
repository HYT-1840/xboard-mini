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

// 模拟数据（可根据实际业务修改）
$db = new SQLite3('../../xboard-mini/database.db');
// 统计节点数/用户数（示例，可根据实际表结构调整）
$node_count = $db->querySingle("SELECT COUNT(*) FROM node");
$user_count = $db->querySingle("SELECT COUNT(*) FROM user");
$node_count = $node_count ?: 0;
$user_count = $user_count ?: 0;
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Xboard-Mini - 管理中心</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: "Microsoft YaHei", Arial, sans-serif;
        }
        body {
            background: #f8fafc;
            color: #334155;
            min-height: 100vh;
        }
        /* 顶部导航栏 */
        .header {
            background: #fff;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
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
            color: #667eea;
        }
        .header .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .header .user-info span {
            font-size: 14px;
        }
        .header .user-info a {
            color: #dc2626;
            text-decoration: none;
            font-size: 14px;
            padding: 6px 12px;
            border-radius: 6px;
            background: #fef2f2;
            transition: all 0.3s ease;
        }
        .header .user-info a:hover {
            background: #fee2e2;
        }
        /* 主内容区 */
        .main {
            padding: 80px 20px 40px;
            max-width: 1200px;
            margin: 0 auto;
            width: 100%;
        }
        .main .page-title {
            font-size: 22px;
            font-weight: 600;
            margin-bottom: 30px;
            color: #1e293b;
        }
        /* 统计卡片 */
        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }
        .stats-cards .card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.05);
            padding: 25px 20px;
            transition: all 0.3s ease;
        }
        .stats-cards .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.08);
        }
        .stats-cards .card .card-title {
            font-size: 14px;
            color: #64748b;
            margin-bottom: 10px;
        }
        .stats-cards .card .card-num {
            font-size: 32px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 15px;
        }
        .stats-cards .card .card-btn {
            display: inline-block;
            font-size: 14px;
            color: #667eea;
            text-decoration: none;
            padding: 6px 0;
            border-bottom: 1px solid #e5e7eb;
            transition: all 0.3s ease;
        }
        .stats-cards .card .card-btn:hover {
            color: #556cd6;
            border-color: #667eea;
        }
        /* 功能操作区 */
        .action-area {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.05);
            padding: 30px 25px;
        }
        .action-area .area-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 25px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e2e8f0;
        }
        .action-buttons {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
        }
        .action-buttons .btn {
            padding: 12px 20px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 500;
            text-align: center;
            text-decoration: none;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .btn-primary {
            background: #667eea;
            color: #fff;
        }
        .btn-primary:hover {
            background: #556cd6;
            transform: translateY(-2px);
        }
        .btn-secondary {
            background: #f1f5f9;
            color: #334155;
        }
        .btn-secondary:hover {
            background: #e2e8f0;
            transform: translateY(-2px);
        }
        .btn-success {
            background: #10b981;
            color: #fff;
        }
        .btn-success:hover {
            background: #059669;
            transform: translateY(-2px);
        }
        .btn-warning {
            background: #f59e0b;
            color: #fff;
        }
        .btn-warning:hover {
            background: #d97706;
            transform: translateY(-2px);
        }
        /* 响应式适配 */
        @media (max-width: 768px) {
            .stats-cards {
                grid-template-columns: 1fr 1fr;
            }
            .action-buttons {
                grid-template-columns: 1fr 1fr;
            }
            .header .logo {
                font-size: 16px;
            }
            .main .page-title {
                font-size: 20px;
            }
        }
        @media (max-width: 480px) {
            .stats-cards {
                grid-template-columns: 1fr;
            }
            .action-buttons {
                grid-template-columns: 1fr;
            }
            .header {
                padding: 0 15px;
            }
            .main {
                padding: 80px 15px 40px;
            }
        }
    </style>
</head>
<body>
    <!-- 顶部导航 -->
    <header class="header">
        <div class="logo">Xboard-Mini 管理面板</div>
        <div class="user-info">
            <span>当前登录：<?php echo $_SESSION['admin_username']; ?></span>
            <a href="?action=logout">安全退出</a>
        </div>
    </header>

    <!-- 主内容区 -->
    <main class="main">
        <h1 class="page-title">管理中心</h1>

        <!-- 数据统计卡片 -->
        <div class="stats-cards">
            <div class="card">
                <div class="card-title">总节点数</div>
                <div class="card-num"><?php echo $node_count; ?></div>
                <a href="#" class="card-btn">管理节点 →</a>
            </div>
            <div class="card">
                <div class="card-title">总用户数</div>
                <div class="card-num"><?php echo $user_count; ?></div>
                <a href="#" class="card-btn">管理用户 →</a>
            </div>
            <div class="card">
                <div class="card-title">面板状态</div>
                <div class="card-num" style="color: #10b981;">运行中</div>
                <a href="#" class="card-btn">查看日志 →</a>
            </div>
            <div class="card">
                <div class="card-title">数据备份</div>
                <div class="card-num" style="color: #667eea;">最新</div>
                <a href="#" class="card-btn">立即备份 →</a>
            </div>
        </div>

        <!-- 功能操作区 -->
        <div class="action-area">
            <h2 class="area-title">核心功能操作</h2>
            <div class="action-buttons">
                <a href="#" class="btn btn-primary">📝 添加节点</a>
                <a href="#" class="btn btn-primary">👤 添加用户</a>
                <a href="#" class="btn btn-secondary">📊 流量统计</a>
                <a href="#" class="btn btn-secondary">⚙️ 面板设置</a>
                <a href="#" class="btn btn-success">💾 一键备份</a>
                <a href="#" class="btn btn-warning">🔄 重启面板</a>
            </div>
        </div>
    </main>
</body>
</html>
