<?php
require_once 'config.php';
checkAdmin();

if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header("Location: index.php");
    exit;
}

$db = getDB();
$search = trim($_GET['search'] ?? '');
$where = "1=1";
$params = [];
if (!empty($search)) {
    $where .= " AND username LIKE ?";
    $params[] = "%{$search}%";
}

// 批量操作处理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['batch_action'])) {
    $uids = $_POST['uids'] ?? [];
    $action = $_POST['batch_action'];
    if (!empty($uids) && is_array($uids)) {
        $placeholders = rtrim(str_repeat('?,', count($uids)), ',');
        switch ($action) {
            case 'disable':
                $stmt = $db->prepare("UPDATE users SET status=0 WHERE id IN ({$placeholders})");
                $stmt->execute($uids);
                break;
            case 'enable':
                $stmt = $db->prepare("UPDATE users SET status=1 WHERE id IN ({$placeholders})");
                $stmt->execute($uids);
                break;
            case 'reset_traffic':
                $stmt = $db->prepare("UPDATE users SET traffic_used=0 WHERE id IN ({$placeholders})");
                $stmt->execute($uids);
                break;
        }
        header("Location: user.php?search=" . urlencode($search));
        exit;
    }
}

// 查询用户列表
$stmt = $db->prepare("SELECT * FROM users WHERE {$where} ORDER BY id DESC");
$stmt->execute($params);
$users = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>用户管理 - Xboard-Mini</title>
    <link rel="stylesheet" href="https://cdn.bootcdn.net/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
        font-family: "Microsoft Yahei", sans-serif;
        transition: background 0.3s, border-color 0.3s, color 0.3s, transform 0.2s;
    }
    :root {
        --body-bg:#f8fafc;--header-bg:#fff;--card-bg:#fff;--table-bg:#fff;--table-hover:#f8fafc;
        --text-primary:#1e293b;--text-secondary:#64748b;--text-muted:#94a3b8;--border-color:#e2e8f0;
        --primary:#64748b;--primary-hover:#556879;--success:#10b981;--warning:#f59e0b;--danger:#dc2626;
        --secondary:#f1f5f9;--shadow:0 2px 12px rgba(0,0,0,0.05);
        --border-radius-sm:6px;--border-radius:8px;--border-radius-lg:12px;
    }
    [data-theme="dark"] {
        --body-bg:#0f172a;--header-bg:#1e293b;--card-bg:#1e293b;--table-bg:#1e293b;--table-hover:#27374d;
        --text-primary:#f1f5f9;--text-secondary:#cbd5e1;--text-muted:#94a3b8;--border-color:#334155;
        --primary:#4f46e5;--success:#059669;--warning:#d97706;--danger:#ef4444;
        --secondary:#334155;--shadow:0 2px 12px rgba(0,0,0,0.2);
    }
    body {background:var(--body-bg);color:var(--text-primary);min-height:100vh;}
    .header {position:fixed;top:0;left:0;right:0;height:60px;background:var(--header-bg);box-shadow:var(--shadow);display:flex;align-items:center;justify-content:space-between;padding:0 20px;z-index:100;}
    .logo {font-size:18px;font-weight:600;color:var(--primary);display:flex;align-items:center;gap:8px;}
    .header-right {display:flex;align-items:center;gap:15px;}
    .theme-btn,.back-btn,.logout-btn {border:none;background:var(--secondary);color:var(--text-primary);padding:6px 12px;border-radius:var(--border-radius-sm);display:flex;align-items:center;gap:6px;text-decoration:none;font-size:14px;cursor:pointer;}
    .logout-btn {color:var(--danger);}
    .logout-btn:hover {background:var(--danger);color:#fff;}
    .main {padding:80px 20px 40px;max-width:1400px;margin:0 auto;width:100%;}
    .page-header {display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:15px;}
    .page-title {font-size:22px;font-weight:600;display:flex;align-items:center;gap:8px;}
    .search-bar {display:flex;gap:10px;align-items:center;}
    .search-input {padding:8px 12px;border:1px solid var(--border-color);border-radius:var(--border-radius-sm);background:var(--secondary);color:var(--text-primary);width:240px;}
    .btn {padding:8px 16px;border-radius:var(--border-radius);font-size:14px;font-weight:500;text-align:center;text-decoration:none;border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:6px;background:var(--primary);color:#fff;}
    .btn-sm {padding:4px 10px;font-size:12px;}
    .btn-warning {background:var(--warning);}
    .btn-danger {background:var(--danger);}
    .btn-success {background:var(--success);}
    .card {background:var(--card-bg);border-radius:var(--border-radius-lg);box-shadow:var(--shadow);padding:20px;width:100%;}
    .batch-bar {display:flex;align-items:center;gap:10px;margin-bottom:15px;flex-wrap:wrap;}
    .batch-select {padding:6px 8px;border:1px solid var(--border-color);border-radius:var(--border-radius-sm);background:var(--secondary);color:var(--text-primary);}
    .table-container {overflow-x:auto;}
    .data-table {width:100%;border-collapse:collapse;background:var(--table-bg);border-radius:var(--border-radius);overflow:hidden;}
    .data-table thead {background:var(--secondary);}
    .data-table th,.data-table td {padding:10px 12px;text-align:left;border-bottom:1px solid var(--border-color);font-size:14px;}
    .data-table tbody tr:hover {background:var(--table-hover);}
    .text-muted {color:var(--text-muted);}
    .text-success {color:var(--success);}
    .text-warning {color:var(--warning);}
    .traffic-progress {width:100px;height:6px;background:var(--secondary);border-radius:3px;overflow:hidden;margin:4px 0;}
    .traffic-bar {height:100%;background:var(--success);}
    .traffic-bar.warning {background:var(--warning);}
    .traffic-bar.danger {background:var(--danger);}
    </style>
</head>
<body>
<header class="header">
    <div class="logo"><i class="fas fa-users"></i> Xboard-Mini 管理面板</div>
    <div class="header-right">
        <button class="theme-btn" id="themeBtn"><i class="fas fa-moon"></i></button>
        <a href="admin.php" class="back-btn"><i class="fas fa-arrow-left"></i> 返回首页</a>
        <a href="?action=logout" class="logout-btn"><i class="fas fa-sign-out-alt"></i> 退出</a>
    </div>
</header>
<main class="main">
    <div class="page-header">
        <h1 class="page-title"><i class="fas fa-user-circle"></i> 用户管理</h1>
        <div class="search-bar">
            <input type="text" class="search-input" name="search" placeholder="搜索用户名" value="<?=e($search)?>" onkeydown="if(event.key==='Enter')location.href='user.php?search='+encodeURIComponent(this.value)">
            <a href="javascript:;" class="btn btn-sm" onclick="location.href='user.php?search='+encodeURIComponent(document.querySelector('.search-input').value)">搜索</a>
            <?php if(!empty($search)): ?>
            <a href="user.php" class="btn btn-sm">清空</a>
            <?php endif; ?>
            <a href="user_add.php" class="btn btn-sm"><i class="fas fa-plus"></i> 新增</a>
        </div>
    </div>
    <div class="card">
        <form method="post" id="batchForm">
            <div class="batch-bar">
                <select name="batch_action" class="batch-select">
                    <option value="">批量操作</option>
                    <option value="enable">启用选中</option>
                    <option value="disable">禁用选中</option>
                    <option value="reset_traffic">重置流量</option>
                </select>
                <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('确认执行批量操作？')">执行</button>
                <label style="font-size:14px;"><input type="checkbox" id="checkAll"> 全选</label>
            </div>
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="checkAllHead"></th>
                            <th>ID</th>
                            <th>用户名</th>
                            <th>总流量(MB)</th>
                            <th>已用(MB)</th>
                            <th>使用率</th>
                            <th>剩余(MB)</th>
                            <th>状态</th>
                            <th>创建时间</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(count($users) > 0): ?>
                        <?php foreach($users as $user):
                            $quota = $user['traffic_quota'];
                            $used = $user['traffic_used'];
                            $left = max(0, $quota - $used);
                            $rate = $quota > 0 ? round(($used/$quota)*100, 2) : 0;
                            $barClass = $rate >= 90 ? 'danger' : ($rate >=70 ? 'warning' : '');
                        ?>
                        <tr>
                            <td><input type="checkbox" name="uids[]" value="<?=$user['id']?>" class="uidCheck"></td>
                            <td><?=$user['id']?></td>
                            <td><?=e($user['username'])?></td>
                            <td><?=$quota?></td>
                            <td>
                                <?=$used?>
                                <div class="traffic-progress">
                                    <div class="traffic-bar <?=$barClass?>" style="width:<?=$rate?>%"></div>
                                </div>
                                <small><?=$rate?>%</small>
                            </td>
                            <td><?=$left?></td>
                            <td><span class="<?=$user['status']?'text-success':'text-warning'?>"><?=$user['status']?'启用':'禁用'?></span></td>
                            <td class="text-muted"><?=$user['created_at']?></td>
                            <td style="display:flex;gap:6px;">
                                <a href="user_detail.php?id=<?=$user
    .theme-btn,
    .back-btn,
    .logout-btn {
        border: none;
        background: var(--secondary);
        color: var(--text-primary);
        padding: 6px 12px;
        border-radius: var(--border-radius-sm);
        display: flex;
        align-items: center;
        gap: 6px;
        text-decoration: none;
        font-size: 14px;
        cursor: pointer;
    }

    .logout-btn {
        color: var(--danger);
    }

    .logout-btn:hover {
        background: var(--danger);
        color: #fff;
    }

    .main {
        padding: 80px 20px 40px;
        max-width: 1400px;
        margin: 0 auto;
        width: 100%;
    }

    .page-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 30px;
        flex-wrap: wrap;
        gap: 15px;
    }

    .page-title {
        font-size: 22px;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .btn {
        padding: 10px 20px;
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
        background: var(--primary);
        color: #fff;
    }

    .btn:hover {
        background: var(--primary-hover);
        transform: translateY(-2px);
    }

    .btn-sm {
        padding: 4px 12px;
        font-size: 12px;
        border-radius: var(--border-radius-sm);
    }

    .btn-warning {
        background: var(--warning);
    }

    .btn-danger {
        background: var(--danger);
    }

    .card {
        background: var(--card-bg);
        border-radius: var(--border-radius-lg);
        box-shadow: var(--shadow);
        padding: 25px;
        width: 100%;
    }

    .table-container {
        overflow-x: auto;
        margin-top: 20px;
    }

    .data-table {
        width: 100%;
        border-collapse: collapse;
        background: var(--table-bg);
        border-radius: var(--border-radius);
        overflow: hidden;
    }

    .data-table thead {
        background: var(--secondary);
    }

    .data-table th,
    .data-table td {
        padding: 12px 15px;
        text-align: left;
        border-bottom: 1px solid var(--border-color);
        font-size: 14px;
    }

    .data-table tbody tr:hover {
        background: var(--table-hover);
    }

    .text-muted {
        color: var(--text-muted);
    }

    .text-success {
        color: var(--success);
    }

    .text-warning {
        color: var(--warning);
    }

    .text-danger {
        color: var(--danger);
    }

    .traffic-progress {
        width: 100%;
        height: 6px;
        background: var(--secondary);
        border-radius: 3px;
        margin: 4px 0;
        overflow: hidden;
    }

    .traffic-bar {
        height: 100%;
        background: var(--success);
        transition: width 0.3s;
    }

    .traffic-bar.warning {
        background: var(--warning);
    }

    .traffic-bar.danger {
        background: var(--danger);
    }
    </style>
</head>
<body>

<header class="header">
    <div class="logo"><i class="fas fa-users"></i> Xboard-Mini 管理面板</div>
    <div class="header-right">
        <button class="theme-btn" id="themeBtn"><i class="fas fa-moon"></i></button>
        <a href="admin.php" class="back-btn"><i class="fas fa-arrow-left"></i> 返回首页</a>
        <a href="?action=logout" class="logout-btn"><i class="fas fa-sign-out-alt"></i> 退出</a>
    </div>
</header>

<main class="main">
    <div class="page-header">
        <h1 class="page-title"><i class="fas fa-user-circle"></i> 用户管理</h1>
        <a href="user_add.php" class="btn"><i class="fas fa-plus"></i> 添加新用户</a>
    </div>

    <div class="card">
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>用户名</th>
                        <th>总流量(MB)</th>
                        <th>已用流量(MB)</th>
                        <th>使用率</th>
                        <th>剩余流量(MB)</th>
                        <th>状态</th>
                        <th>创建时间</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($users) > 0): ?>
                        <?php foreach ($users as $user): ?>
                            <?php
                            $quota = $user['traffic_quota'];
                            $used = $user['traffic_used'];
                                                        <?php
                            $left = max(0, $quota - $used);
                            $rate = $quota > 0 ? round(($used / $quota) * 100, 2) : 0;

                            if ($rate >= 90) {
                                $barClass = 'danger';
                            } elseif ($rate >= 70) {
                                $barClass = 'warning';
                            } else {
                                $barClass = '';
                            }
                            ?>
                            <tr>
                                <td><?= $user['id'] ?></td>
                                <td><?= e($user['username']) ?></td>
                                <td><?= $quota ?></td>
                                <td>
                                    <?= $used ?>
                                    <div class="traffic-progress">
                                        <div class="traffic-bar <?= $barClass ?>" style="width: <?= $rate ?>%"></div>
                                    </div>
                                    <small><?= $rate ?>%</small>
                                </td>
                                <td><?= $left ?></td>
                                <td>
                                    <span class="<?= $user['status'] ? 'text-success' : 'text-warning' ?>">
                                        <?= $user['status'] ? '<i class="fas fa-check"></i> 启用' : '<i class="fas fa-pause"></i> 禁用' ?>
                                    </span>
                                </td>
                                <td class="text-muted"><?= $user['created_at'] ?></td>
                                <td>
                                    <a href="user_edit.php?id=<?= $user['id'] ?>" class="btn btn-sm btn-warning">
                                        <i class="fas fa-edit"></i> 编辑
                                    </a>
                                    <a href="user_del.php?id=<?= $user['id'] ?>" class="btn btn-sm btn-danger"
                                       onclick="return confirm('确定删除该用户？操作不可恢复')">
                                        <i class="fas fa-trash"></i> 删除
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" align="center" style="padding: 30px; color: var(--text-muted);">
                                <i class="fas fa-inbox"></i> 暂无用户数据
                            </t        .header .logo {
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
        .back-btn, .logout-btn {
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
        .logout-btn {
            color: var(--danger);
        }
        .logout-btn:hover {
            background: var(--danger);
            color: #fff;
        }
        .main {
            padding: 80px 20px 40px;
            max-width: 1400px;
            margin: 0 auto;
            width: 100%;
        }
        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 15px;
        }
        .page-title {
            font-size: 22px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .btn {
            padding: 10px 20px;
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
        }
        .btn-primary { background: var(--primary); color: #fff; }
        .btn-primary:hover { background: var(--primary-hover); transform: translateY(-2px); }
        .btn-sm {
            padding: var(--btn-sm-padding);
            font-size: 12px;
            border-radius: var(--border-radius-sm);
        }
        .btn-success { background: var(--success); color: #fff; }
        .btn-success:hover { background: var(--success-hover); }
        .btn-warning { background: var(--warning); color: #fff; }
        .btn-warning:hover { background: var(--warning-hover); }
        .btn-danger { background: var(--danger); color: #fff; }
        .btn-danger:hover { background: var(--danger-hover); }
        .card {
            background: var(--card-bg);
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow);
            padding: 25px;
            width: 100%;
        }
        .table-container {
            overflow-x: auto;
            margin-top: 20px;
        }
        .data-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            background: var(--table-bg);
            border-radius: var(--border-radius);
            overflow: hidden;
        }
        .data-table thead {
            background: var(--secondary);
        }
        .data-table th, .data-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
            font-size: 14px;
        }
        .data-table th {
            font-weight: 600;
            color: var(--text-secondary);
        }
        .data-table tbody tr:hover {
            background: var(--table-hover);
        }
        .data-table tbody tr:last-child td {
            border-bottom: none;
        }
        .text-muted {
            color: var(--text-muted);
        }
        .text-success {
            color: var(--success);
        }
        .text-warning {
            color: var(--warning);
        }
        .text-ellipsis {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 150px;
        }
        .loading-spin {
            display: none;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        /* 流量进度条样式 */
        .traffic-progress {
            width: 100%;
            height: 6px;
            background: var(--secondary);
            border-radius: 3px;
            overflow: hidden;
            margin-top: 4px;
        }
        .traffic-bar {
            height: 100%;
            background: var(--primary);
            border-radius: 3px;
            transition: width 0.3s ease;
        }
        .traffic-bar.warning {
            background: var(--warning);
        }
        .traffic-bar.danger {
            background: var(--danger);
        }
        @media (max-width: 768px) {
            .main {
                padding: 80px 15px 40px;
            }
            .card {
                padding: 20px;
            }
            .page-title {
                font-size: 20px;
            }
            .btn {
                padding: 8px 15px;
                font-size: 13px;
            }
            .text-ellipsis {
                max-width: 100px;
            }
        }
        @media (max-width: 480px) {
            .header {
                padding: 0 10px;
            }
            .header .logo {
                font-size: 16px;
            }
            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }
            .text-ellipsis {
                max-width: 80px;
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
            <a href="admin.php" class="back-btn">
                <i class="fas fa-arrow-left"></i> 返回首页
            </a>
            <a href="?action=logout" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i> 退出
            </a>
        </div>
    </header>

    <main class="main">
        <div class="page-header">
            <h1 class="page-title">
                <i class="fas fa-users"></i> 用户管理
            </h1>
            <a href="user_add.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> 添加新用户
            </a>
        </div>

        <div class="card">
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <!-- 修正2：表头替换为users表实际字段，新增流量相关列 -->
                            <th>ID</th>
                            <th>用户名</th>
                            <th>流量配额(GB)</th>
                            <th>已用流量(GB)</th>
                            <th>剩余流量(GB)</th>
                            <th>状态</th>
                            <th>创建时间</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($users && $users->numRows() > 0): ?>
                            <?php while ($user = $users->fetchArray(SQLITE3_ASSOC)): ?>
                                <?php
                                // 计算剩余流量和使用率（贴合流量配额管理核心功能）
                                $quota = $user['traffic_quota'] ?? 0;
                                $used = $user['traffic_used'] ?? 0;
                                $left = max(0, $quota - $used);
                                $usageRate = $quota > 0 ? round(($used / $quota) * 100, 2) : 0;
                                // 流量进度条颜色判断
                                $barClass = '';
                                if ($usageRate >= 90) $barClass = 'danger';
                                elseif ($usageRate >= 70) $barClass = 'warning';
                                ?>
                                <tr>
                                    <td><?php echo $user['id']; ?></td>
                                    <td><?php echo htmlspecialchars($user['username'] ?? '未知用户'); ?></td>
                                    <td><?php echo $quota; ?></td>
                                    <td>
                                        <?php echo $used; ?>
                                        <div class="traffic-progress">
                                            <div class="traffic-bar <?php echo $barClass; ?>" style="width: <?php echo $usageRate; ?>%"></div>
                                        </div>
                                        <small class="text-muted"><?php echo $usageRate; ?>%</small>
                                    </td>
                                    <td class="<?php echo $left <= 0 ? 'text-danger' : ''; ?>">
                                        <?php echo $left; ?>
                                    </td>
                                    <td>
                                        <?php $status = $user['status'] ?? 0; ?>
                                        <span class="<?php echo $status ? 'text-success' : 'text-warning'; ?>">
                                            <?php echo $status ? '<i class="fas fa-check"></i> 启用' : '<i class="fas fa-pause"></i> 禁用'; ?>
                                        </span>
                                    </td>
                                    <!-- 修正3：创建时间字段从create_time改为created_at（匹配数据库） -->
                                    <td class="text-muted">
                                        <?php echo !empty($user['created_at']) ? date('Y-m-d H:i', strtotime($user['created_at'])) : '未知'; ?>
                                    </td>
                                    <td>
                                        <a href="user_edit.php?id=<?php echo $user['id']; ?>" class="btn btn-sm btn-warning">
                                            <i class="fas fa-edit"></i> 编辑
                                        </a>
                                        <a href="javascript:delUser(<?php echo $user['id']; ?>)" class="btn btn-sm btn-danger">
                                            <i class="fas fa-trash"></i> 删除
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <!-- 修正4：无数据时列数匹配表头（8列） -->
                                <td colspan="8" style="text-align: center; padding: 30px; color: var(--text-muted);">
                                    <i class="fas fa-user-slash"></i> 暂无用户数据，点击上方添加新用户
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
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

        function delUser(id) {
            if (confirm(`确定要删除ID为${id}的用户吗？此操作不可恢复！`)) {
                const targetBtn = event.target.closest('.btn-danger');
                const loading = document.createElement('span');
                loading.className = 'loading-spin fas fa-spinner';
                loading.style.display = 'inline-block';
                loading.style.marginLeft = '6px';
                targetBtn.appendChild(loading);
                targetBtn.disabled = true;
                
                // 模拟删除请求，实际项目可替换为AJAX请求
                setTimeout(() => {
                    alert(`用户${id}删除成功！`);
                    window.location.reload();
                }, 1000);
            }
        }
    </script>
</body>
</html>
