<?php
require_once 'config.php';
checkAdmin();
$id = (int)$_GET['id'];
$db = getDB();
$stmt = $db->prepare("SELECT * FROM users WHERE id=?");
$stmt->execute([$id]);
$user = $stmt->fetch();
if(!$user) die("用户不存在");

$nodes = $db->query("SELECT * FROM nodes WHERE status=1 ORDER BY id ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>用户详情 - 连接信息</title>
    <link rel="stylesheet" href="https://cdn.bootcdn.net/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
    *{margin:0;padding:0;box-sizing:border-box;font-family:"Microsoft Yahei",sans-serif;}
    :root{--body-bg:#f8fafc;--card-bg:#fff;--text-primary:#1e293b;--border-color:#e2e8f0;--primary:#64748b;--secondary:#f1f5f9;}
    [data-theme="dark"]{--body-bg:#0f172a;--card-bg:#1e293b;--text-primary:#f1f5f9;--border-color:#334155;--primary:#4f46e5;--secondary:#334155;}
    body{background:var(--body-bg);color:var(--text-primary);padding:20px;}
    .container{max-width:800px;margin:20px auto;}
    .card{background:var(--card-bg);border-radius:12px;box-shadow:0 2px 12px rgba(0,0,0,0.05);padding:25px;margin-bottom:20px;}
    .card h2{margin-bottom:15px;display:flex;align-items:center;gap:8px;}
    .info-item{margin-bottom:10px;font-size:14px;}
    .qrcode{margin:15px 0;text-align:center;}
    .link-box{padding:10px;background:var(--secondary);border-radius:6px;margin:10px 0;font-family:monospace;word-break:break-all;}
    .back{display:inline-block;margin-bottom:20px;color:var(--primary);text-decoration:none;}
    </style>
</head>
<body>
<div class="container">
    <a href="user.php" class="back"><i class="fas fa-arrow-left"></i> 返回用户列表</a>
    <div class="card">
        <h2><i class="fas fa-user"></i> 用户信息</h2>
        <div class="info-item">ID：<?=$user['id']?></div>
        <div class="info-item">用户名：<?=e($user['username'])?></div>
        <div class="info-item">配额：<?=$user['traffic_quota']?> MB / 已用：<?=$user['traffic_used']?> MB</div>
        <div class="info-item">状态：<?=$user['status']?'启用':'禁用'?></div>
    </div>

    <div class="card">
        <h2><i class="fas fa-qrcode"></i> 节点连接配置 & 二维码</h2>
        <?php if(empty($nodes)): ?>
            <p>暂无启用节点</p>
        <?php else: ?>
            <?php foreach($nodes as $node): 
                $link = "{$node['protocol']}://{$user['username']}@{$node['host']}:{$node['port']}";
            ?>
            <div class="card" style="margin-bottom:15px;">
                <h3 style="font-size:16px;margin-bottom:10px;"><?=e($node['name'])?> (<?=e($node['protocol'])?>)</h3>
                <div class="link-box"><?=e($link)?></div>
                <div class="qrcode" id="qrcode_<?=$node['id']?>"></div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.1/build/qrcode.min.js"></script>
<?php foreach($nodes as $node): 
    $link = e("{$node['protocol']}://{$user['username']}@{$node['host']}:{$node['port']}");
?>
<script>
QRCode.toCanvas(document.getElementById('qrcode_<?=$node['id']?>'), '<?=$link?>', {width:160,margin:1}, (err)=>{});
</script>
<?php endforeach; ?>
</body>
</html>
