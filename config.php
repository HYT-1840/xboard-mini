<?php
session_start();

// 数据库配置
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'xboard_mini');
define('DB_USER', 'xboard');
define('DB_PASS', '你的面板数据库密码');

// 数据库连接
function getDB() {
    static $db = null;
    if ($db === null) {
        try {
            $db = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            die("数据库连接失败：" . $e->getMessage());
        }
    }
    return $db;
}

// 管理员登录校验
function checkAdmin() {
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        header("Location: /index.php");
        exit;
    }
}

// 安全输出
function e($str) {
    echo htmlspecialchars($str ?? '', ENT_QUOTES);
}
?>
