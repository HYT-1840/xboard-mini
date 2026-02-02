<?php
session_start();
require_once 'config.php';
$db = getDB();

// 退出登录
if (isset($_GET['act']) && $_GET['act'] === 'logout') {
    session_destroy();
    header("Location: index.php");
    exit;
}

$error = $success = '';

// 登录逻辑
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_SESSION['uid'])) {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    if (!$username || !$password) {
        $error = "账号密码不能为空";
    } else {
        $stmt = $db->prepare("SELECT * FROM users WHERE username = ? AND status = 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['uid'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            header("Location: index.php");
            exit;
        } else {
            $error = "账号/密码错误或已被禁用";
        }
    }
}

$isLogin = isset($_SESSION['uid']);
$isAdmin = $isLogin && $_SESSION['role'] === 'admin';
$userInfo = null;
if ($isLogin) {
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['uid']]);
    $userInfo = $stmt->fetch(PDO::FETCH_ASSOC);
}

// ====================== 管理员操作：用户增删改查 ======================
if ($isAdmin) {
    // 添加用户
    if (isset($_POST['add_user'])) {
        $uname = trim($_POST['username']);
        $pwd = trim($_POST['password']);
        $quota = intval($_POST['traffic_quota']);
        $role = trim($_POST['role']);
        $hash = password_hash($pwd, PASSWORD_DEFAULT);
        try {
            $stmt = $db->prepare("INSERT INTO users (username,password,traffic_quota,role) VALUES (?,?,?,?)");
            $stmt->execute([$uname, $hash, $quota, $role]);
            $success = "用户添加成功";
        } catch (Exception $e) {
            $error = "用户名已存在或参数错误";
        }
    }

    // 删除用户
    if (isset($_POST['del_user'])) {
        $id = intval($_POST['id']);
        if ($id != $_SESSION['uid']) {
            $db->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
            $success = "删除成功";
        } else {
            $error = "不能删除自己";
        }
    }

    // 修改用户状态/流量
    if (isset($_POST['update_user'])) {
        $id = intval($_POST['id']);
        $quota = intval($_POST['traffic_quota']);
        $status = intval($_POST['status']);
        $role = trim($_POST['role']);
        $db->prepare("UPDATE users SET traffic_quota=?,status=?,role=? WHERE id=?")->execute([$quota, $status, $role, $id]);
        $success = "更新成功";
    }

    // 添加节点
    if (isset($_POST['add_node'])) {
        $name = trim($_POST['name']);
        $host = trim($_POST['host']);
        $port = intval($_POST['port']);
        $proto = trim($_POST['protocol']);
        $db->prepare("INSERT INTO nodes (name,host,port,protocol) VALUES (?,?,?,?)")->execute([$name, $host, $port, $proto]);
        $success = "节点添加成功";
    }

    // 删除节点
    if (isset($_POST['del_node'])) {
        $id = intval($_POST['id']);
        $db->prepare("DELETE FROM nodes WHERE id = ?")->execute([$id]);
        $success = "节点删除成功";
    }

    // 统计数据
    $userCount = $db->query("SELECT COUNT(*) FROM users")->fetch()[0];
    $nodeCount = $db->query("SELECT COUNT(*) FROM nodes")->fetch()[0];
    $allUsers = $db->query("SELECT * FROM users ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
    $allNodes = $db->query("SELECT * FROM nodes ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
}

// 普通用户节点与链接
$userNodes = [];
if ($isLogin && !$isAdmin) {
    $userNodes = $db->query("SELECT * FROM nodes WHERE status = 1")->fetchAll(PDO::FETCH_ASSOC);
}

function buildShare($node, $user) {
    $data = [
        "v" => "2", "ps" => $node['name'], "add" => $node['host'],
        "port" => (string)$node['port'], "id" => $user['username'],
        "aid" => "0", "scy" => "auto", "net" => "ws",
        "type" => "none", "path" => "/", "tls" => "none"
    ];
    return "vmess://" . base64_encode(json_encode($data, JSON_UNESCAPED_UNICODE));
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Xboard-Mini 统一面板</title>
    <style>
        *{margin:0;padding:0;box-sizing:border-box;font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif}
        body{background:#f8fafc;color:#1e293b;line-height:1.6;padding:12px}
        .container{max-width:900px;margin:0 auto}
        .card{background:#fff;border-radius:12px;box-shadow:0 2px 12px rgba(0,0,0,0.06);padding:16px;margin-bottom:14px}
        .title{font-size:18px;font-weight:600;margin-bottom:12px;color:#0f172a}
        .tip{font-size:13px;color:#64748b;margin-bottom:10px}
        .form-grid{display:grid;grid-template-columns:1fr;gap:10px;margin-bottom:12px}
        @media(min-width:640px){.form-grid{grid-template-columns:repeat(2,1fr)}}
        input,select{padding:10px 12px;border:1px solid #e2e8f0;border-radius:6px;width:100%;font-size:14px}
        .btn{padding:10px 14px;border:none;border-radius:6px;cursor:pointer;font-size:14px;background:#4f46e5;color:#fff;display:inline-flex;align-items:center;justify-content:center}
        .btn-sm{padding:6px 10px;font-size:13px}
        .btn-success{background:#059669}
        .btn-warning{background:#d97706}
        .btn-danger{background:#dc2626}
        .btn-block{width:100%}
        .header{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:12px}
        .badge{padding:4px 8px;border-radius:6px;font-size:13px;background:#e0e7ff;color:#4f46e5}
        .badge-user{background:#f0fdf4;color:#059669}
        .grid-stat{display:grid;grid-template-columns:repeat(2,1fr);gap:10px;margin:10px 0}
        .stat{padding:12px;background:#f1f5f9;border-radius:8px;text-align:center}
        .item{padding:12px;border:1px solid #e2e8f0;border-radius:8px;margin-bottom:10px}
        .item-foot{display:flex;gap:8px;flex-wrap:wrap;margin-top:10px}
        .link{word-break:break-all;background:#f8fafc;padding:8px;border-radius:6px;font-size:13px;margin:8px 0;color:#334155}
        .progress{height:8px;background:#e2e8f0;border-radius:4px;margin:8px 0}
        .bar{height:100%;background:#10b981;border-radius:4px}
        .qrcode{margin:10px 0;display:flex;justify-content:center}
        .login-box{max-width:400px;margin:40px auto}
        .msg-success{color:#059669;font-size:14px;margin-bottom:10px}
        .msg-error{color:#dc2626;font-size:14px;margin-bottom:10px}
    </style>
    <script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.1/build/qrcode.min.js"></script>
</head>
<body>
<div class="container">
    <?php if (!$isLogin): ?>
    <div class="card login-box">
        <h2 class="title">账号登录</h2>
        <?php if($error):?><div class="msg-error"><?=$error?></div><?php endif;?>
        <form method="post">
            <div class="form-grid">
                <input type="text" name="username" placeholder="用户名" required>
                <input type="password" name="password" placeholder="密码" required>
            </div>
            <button class="btn btn-block">登录</button>
        </form>
    </div>
    <?php else: ?>
    <div class="card">
        <div class="header">
            <h2 class="title">Xboard-Mini 面板</h2>
            <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
                <span class="badge"><?=$_SESSION['username']?></span>
                <span class="badge <?= $isAdmin ? '' : 'badge-user' ?>"><?= $isAdmin ? '管理员' : '普通用户' ?></span>
                <a href="?act=logout" class="btn btn-sm btn-danger">退出</a>
            </div>
        </div>
        <?php if($success):?><div class="msg-success"><?=$success?></div><?php endif;?>
        <?php if($error):?><div class="msg-error"><?=$error?></div><?php endif;?>
    </div>

    <?php if ($isAdmin): ?>
    <!-- 管理员视图 -->
    <div class="card">
        <h3 class="title">控制台概览</h3>
        <div class="grid-stat">
            <div class="stat">总用户：<?=$userCount?></div>
            <div class="stat">总节点：<?=$nodeCount?></div>
        </div>
    </div>

    <div class="card">
        <h3 class="title">添加用户</h3>
        <form method="post">
            <div class="form-grid">
                <input type="text" name="username" placeholder="用户名" required>
                <input type="password" name="password" placeholder="密码" required>
                <input type="number" name="traffic_quota" value="1024" placeholder="流量配额(MB)" required>
                <select name="role" required>
                    <option value="user">普通用户</option>
                    <option value="admin">管理员</option>
                </select>
            </div>
            <button type="submit" name="add_user" class="btn btn-success">添加用户</button>
        </form>
    </div>

    <div class="card">
        <h3 class="title">用户管理</h3>
        <?php foreach($allUsers as $u): ?>
        <div class="item">
            <div><strong>ID:</strong> <?=$u['id']?> | <?=$u['username']?> | <?=$u['role']?> | 状态:<?=$u['status']?'正常':'禁用'?></div>
            <div>流量:<?=$u['traffic_used']?> / <?=$u['traffic_quota']?> MB</div>
            <div class="item-foot">
                <form method="post" style="display:contents">
                    <input type="hidden" name="id" value="<?=$u['id']?>">
                    <select name="role" class="btn-sm" style="width:auto">
                        <option value="user" <?=$u['role']=='user'?'selected':''?>>user</option>
                        <option value="admin" <?=$u['role']=='admin'?'selected':''?>>admin</option>
                    </select>
                    <select name="status" class="btn-sm" style="width:auto">
                        <option value="1" <?=$u['status']==1?'selected':''?>>启用</option>
                        <option value="0" <?=$u['status']==0?'selected':''?>>禁用</option>
                    </select>
                    <input type="number" name="traffic_quota" value="<?=$u['traffic_quota']?>" style="width:80px">
                    <button type="submit" name="update_user" class="btn btn-sm btn-warning">保存</button>
                    <button type="submit" name="del_user" class="btn btn-sm btn-danger" onclick="return confirm('确定删除？')">删除</button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="card">
        <h3 class="title">添加节点</h3>
        <form method="post">
            <div class="form-grid">
                <input type="text" name="name" placeholder="节点名称(如:香港-01)" required>
                <input type="text" name="host" placeholder="IP/域名" required>
                <input type="number" name="port" placeholder="端口" required>
                <select name="protocol" required>
                    <option value="vmess">vmess</option>
                    <option value="vless">vless</option>
                    <option value="trojan">trojan</option>
                </select>
            </div>
            <button type="submit" name="add_node" class="btn btn-success">添加节点</button>
        </form>
    </div>

    <div class="card">
        <h3 class="title">节点管理</h3>
        <?php foreach($allNodes as $nd): ?>
        <div class="item">
            <div><?=$nd['name']?> | <?=$nd['host']?>:<?=$nd['port']?> | <?=$nd['protocol']?></div>
            <div class="item-foot">
                <form method="post" style="display:contents">
                    <input type="hidden" name="id" value="<?=$nd['id']?>">
                    <button type="submit" name="del_node" class="btn btn-sm btn-danger" onclick="return confirm('确定删除节点？')">删除</button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php else: ?>
    <!-- 普通用户视图 -->
    <div class="card">
        <h3 class="title">我的信息</h3>
        <div class="grid-stat">
            <div class="stat">用户：<?=$userInfo['username']?></div>
            <div class="stat">状态：正常</div>
            <div class="stat">总量：<?=$userInfo['traffic_quota']?>MB</div>
            <div class="stat">已用：<?=$userInfo['traffic_used']?>MB</div>
        </div>
        <?php
        $total = $userInfo['traffic_quota'];
        $used = $userInfo['traffic_used'];
        $pct = $total > 0 ? round(($used / $total) * 100, 1) : 0;
        ?>
        <div>使用率：<?=$pct?>%</div>
        <div class="progress"><div class="bar" style="width:<?=$pct?>%"></div></div>
    </div>

    <div class="card">
        <h3 class="title">可用节点</h3>
        <?php if(empty($userNodes)): ?>
        <div class="tip">暂无可用节点</div>
        <?php else: ?>
        <?php foreach($userNodes as $node):
            $link = buildShare($node, $userInfo);
            $qid = "q{$node['id']}";
        ?>
        <div class="item">
            <div><strong><?=$node['name']?></strong> (<?=$node['protocol']?>)</div>
            <div class="link" id="<?=$qid?>-txt"><?=$link?></div>
            <button class="btn btn-sm" onclick="copy('<?=$qid?>-txt')">复制链接</button>
            <div id="<?=$qid?>" class="qrcode"></div>
        </div>
        <script>
            QRCode.toCanvas(document.getElementById('<?=$qid?>'), '<?=$link?>', {width:160,margin:1})
        </script>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<script>
function copy(id){
    const txt = document.getElementById(id).textContent;
    navigator.clipboard.writeText(txt).then(()=>alert("复制成功")).catch(()=>alert("复制失败"));
}
</script>
</body>
</html>
