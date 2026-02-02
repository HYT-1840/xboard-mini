<?php
require_once 'config.php';
checkAdmin();
$id = (int)$_GET['id'];
$db = getDB();
$stmt = $db->prepare("SELECT * FROM users WHERE id=?");
$stmt->execute([$id]);
$user = $stmt->fetch();
if (!$user) die("用户不存在");

$nodes = $db->query("SELECT * FROM nodes WHERE status=1 ORDER BY id ASC")->fetchAll();

// 生成标准分享链接
function buildShareLink($node, $user) {
    $proto = strtolower(trim($node['protocol']));
    $host = $node['host'];
    $port = $node['port'];
    $uid = $user['username'];
    $name = rawurlencode("{$node['name']} - {$user['username']}");

    switch ($proto) {
        case 'vmess':
            $vmess = [
                "v" => "2",
                "ps" => $node['name'],
                "add" => $host,
                "port" => (string)$port,
                "id" => $uid,
                "aid" => "0",
                "scy" => "auto",
                "net" => "ws",
                "type" => "none",
                "host" => "",
                "path" => "/",
                "tls" => "none"
            ];
            return "vmess://" . base64_encode(json_encode($vmess, JSON_UNESCAPED_UNICODE));

        case 'vless':
            return "vless://{$uid}@{$host}:{$port}?type=ws&path=%2F&security=none#{$name}";

        case 'trojan':
            return "trojan://{$uid}@{$host}:{$port}?type=ws&path=%2F&security=none#{$name}";

        default:
            return "{$node['protocol']}://{$uid}@{$host}:{$port}#{$name}";
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>用户详情 - 连接配置</title>
    <link rel="stylesheet" href="https://cdn.bootcdn.net/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
    *{margin:0;padding:0;box-sizing:border-box;font-family:"Microsoft Yahei",sans-serif;}
    :root{--body-bg:#f8fafc;--card-bg:#fff;--text-primary:#1e293b;--border-color:#e2e8f0;--primary:#64748b;--secondary:#f1f5f9;--radius:8px;}
    [data-theme="dark"]{--body-bg:#0f172a;--card-bg:#1e293b;--text-primary:#f1f5f9;--border-color:#334155;--primary:#4f46e5;--secondary:#334155;}
    body{background:var(--body-bg);color:var(--text-primary);padding:20px;}
    .container{max-width:800px;margin:0 auto;}
    .card{background:var(--card-bg);border-radius:12px;box-shadow:0 2px 10px rgba(0,0,0,.05);padding:24px;margin-bottom:20px;}
    .card h2,.card h3{margin-bottom:16px;display:flex;align-items:center;gap:8px;}
    .row{margin-bottom:12px;font-size:14px;}
    .link-box{padding:12px;background:var(--secondary);border-radius:var(--radius);word-break:break-all;font-family:monospace;margin:10px 0;}
    .qrcode{text-align:center;margin:16px 0;}
    .back{display:inline-flex;align-items:center;gap:6px;color:var(--primary);text-decoration:none;margin-bottom:20px;}
    .copy-btn{padding:6px 10px;background:var(--primary);color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:12px;margin-left:8px;}
    </style>
</head>
<body>
<div class="container">
    <a href="user.php" class="back"><i class="fas fa-arrow-left"></i> 返回用户列表</a>

    <div class="card">
        <h2><i class="fas fa-user-circle"></i> 用户信息</h2>
        <div class="row">ID：<?=$user['id']?></div>
        <div class="row">用户名：<?=e($user['username'])?></div>
        <div class="row">流量配额：<?=$user['traffic_quota']?> MB</div>
        <div class="row">已用流量：<?=$user['traffic_used']?> MB</div>
        <div class="row">状态：<?=$user['status']?'<span style="color:#10b981">启用</span>':'<span style="color:#f59e0b">禁用</span>'?></div>
    </div>

    <div class="card">
        <h2><i class="fas fa-qrcode"></i> 可导入分享链接（VMess/VLESS/Trojan）</h2>
        <?php if (empty($nodes)): ?>
            <p>暂无启用节点</p>
        <?php else: ?>
            <?php foreach ($nodes as $node):
                $link = buildShareLink($node, $user);
                $qid = "qrcode_{$node['id']}";
            ?>
            <div class="card" style="border:1px solid var(--border-color);margin-bottom:16px;">
                <h3><i class="fas fa-server"></i> <?=e($node['name'])?> (<?=e($node['protocol'])?>)</h3>
                <div>
                    链接：
                    <button class="copy-btn" onclick="copyLink(this,'<?=e($link)?>')">复制</button>
                    <div class="link-box"><?=e($link)?></div>
                </div>
                <div class="qrcode" id="<?=$qid?>"></div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.1/build/qrcode.min.js"></script>
<script>
function copyLink(el,text){
    navigator.clipboard.writeText(text).then(()=>{
        const old=el.innerText;el.innerText="已复制";setTimeout(()=>el.innerText=old,800);
    }).catch(()=>alert("复制失败"));
}
<?php foreach ($nodes as $node): ?>
QRCode.toCanvas(document.getElementById('qrcode_<?=$node['id']?>'),`<?=e(buildShareLink($node,$user))?>`,{
    width:180,margin:1,color:{dark:"#2c3e50",light:"#ffffff"}
},err=>{});
<?php endforeach; ?>
</script>
</body>
</html>
