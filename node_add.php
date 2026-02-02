<?php require_once 'config.php'; checkAdmin();
$msg = '';
if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $name = trim($_POST['name']);
    $host = trim($_POST['host']);
    $port = (int)$_POST['port'];
    $protocol = trim($_POST['protocol']);
    $remark = trim($_POST['remark']);
    $status = (int)$_POST['status'];
    if(!$name || !$host || $port<1 || $port>65535){
        $msg = '<span style="color:red">参数不完整</span>';
    }else{
        $db = getDB();
        $stmt = $db->prepare("INSERT INTO nodes(name,host,port,protocol,remark,status) VALUES(?,?,?,?,?,?)");
        $stmt->execute([$name,$host,$port,$protocol,$remark,$status]);
        $msg = '<span style="color:green">添加成功，3秒后跳转...</span>';
        echo "<meta http-equiv='refresh' content='3;url=node.php'>";
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>添加节点</title>
    <link rel="stylesheet" href="https://cdn.bootcdn.net/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
    *{margin:0;padding:0;box-sizing:border-box;font-family:"Microsoft Yahei",sans-serif;transition:background 0.3s,border-color 0.3s,color 0.3s;}
    :root{--body-bg:#f8fafc;--card-bg:#fff;--text-primary:#1e293b;--border-color:#e2e8f0;--primary:#64748b;--secondary:#f1f5f9;--border-radius:8px;--shadow:0 2px 12px rgba(0,0,0,0.05);}
    [data-theme="dark"]{--body-bg:#0f172a;--card-bg:#1e293b;--text-primary:#f1f5f9;--border-color:#334155;--primary:#4f46e5;--secondary:#334155;}
    body{background:var(--body-bg);color:var(--text-primary);padding:20px;}
    .container{max-width:600px;margin:40px auto;background:var(--card-bg);padding:30px;border-radius:12px;box-shadow:var(--shadow);}
    h2{margin-bottom:20px;display:flex;align-items:center;gap:8px;}
    .form-group{margin-bottom:15px;}
    label{display:block;margin-bottom:6px;font-weight:500;}
    input,select,textarea{width:100%;padding:10px;border:1px solid var(--border-color);border-radius:var(--border-radius);background:var(--secondary);color:var(--text-primary);font-size:14px;}
    button{background:var(--primary);color:#fff;border:none;padding:10px 20px;border-radius:var(--border-radius);cursor:pointer;}
    .msg{margin:10px 0;}
    .back{margin-top:15px;display:inline-block;color:var(--primary);text-decoration:none;}
    </style>
</head>
<body>
<div class="container">
    <h2><i class="fas fa-plus"></i> 添加节点</h2>
    <?php if($msg)echo "<div class='msg'>$msg</div>";?>
    <form method="post">
        <div class="form-group"><label>节点名称</label><input name="name" required></div>
        <div class="form-group"><label>节点地址(IP/域名)</label><input name="host" required></div>
        <div class="form-group"><label>端口</label><input name="port" type="number" min="1" max="65535" required></div>
        <div class="form-group"><label>协议</label>
            <select name="protocol" required>
                <option value="TCP">TCP</option>
                <option value="WS">WS</option>
                <option value="WSS">WSS</option>
            </select>
        </div>
        <div class="form-group"><label>备注</label><textarea name="remark" rows="3"></textarea></div>
        <div class="form-group"><label>状态</label>
            <select name="status"><option value="1">启用</option><option value="0">禁用</option></select>
        </div>
        <button type="submit">保存节点</button>
    </form>
    <a href="node.php" class="back">返回节点列表</a>
</div>
</body>
</html>
