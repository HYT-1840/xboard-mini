<?php
if ($_POST) {
    $user = $_POST['user'];
    $pwd = $_POST['pwd'];
    $res = $db->querySingle("SELECT password FROM admin WHERE username='$user'", true);
    if ($res && password_verify($pwd, $res['password'])) {
        $_SESSION['admin'] = $user;
        header('Location: /admin');
        exit;
    }
    echo "账号密码错误";
}
?>
<form method="post">
    账号:<input name="user"><br>
    密码:<input type="password" name="pwd"><br>
    <button type="submit">登录</button>
</form>
