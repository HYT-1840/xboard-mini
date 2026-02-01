<h2>多节点管理</h2>
<!-- 添加节点 -->
<form method="post">
    节点名:<input name="name">
    地址:<input name="host">
    端口:<input name="port">
    协议:<input name="protocol" value="vless">
    备注:<input name="remark">
    <button type="submit" name="add">添加节点</button>
</form>
<?php
if (isset($_POST['add'])) {
    $name = $_POST['name'];
    $host = $_POST['host'];
    $port = (int)$_POST['port'];
    $proto = $_POST['protocol'];
    $remark = $_POST['remark'];
    $db->exec("INSERT INTO nodes (name,host,port,protocol,remark) VALUES ('$name','$host',$port,'$proto','$remark')");
    header("Location: /node/list");
}
// 节点列表
$rs = $db->query("SELECT id,name,host,port,protocol,remark,status FROM nodes");
while ($row = $rs->fetchArray(SQLITE3_ASSOC)) {
    echo "{$row['name']} | {$row['host']}:{$row['port']} | {$row['protocol']} | 备注:{$row['remark']} | 状态:{$row['status']}<br>";
}
?>
<br><a href="/admin">返回</a>
