<h2>用户管理</h2>
<!-- 添加用户 -->
<form method="post">
    用户名:<input name="username">
    密码:<input name="password">
    配额(GB):<input name="traffic_quota" value="10">
    <button type="submit" name="add">添加</button>
</form>
<?php
if (isset($_POST['add'])) {
    $u = $_POST['username'];
    $p = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $q = (int)$_POST['traffic_quota'];
    $db->exec("INSERT INTO users (username,password,traffic_quota) VALUES ('$u','$p',$q)");
    header("Location: /user/list");
}
// 用户列表
$rs = $db->query("SELECT id,username,traffic_quota,traffic_used,status FROM users");
while ($row = $rs->fetchArray(SQLITE3_ASSOC)) {
    $used = round($row['traffic_used']/1024, 2);
    echo "{$row['username']} | 配额{$row['traffic_quota']}GB | 已用{$used}GB | 状态:{$row['status']}<br>";
}
?>
<br><a href="/admin">返回</a>
