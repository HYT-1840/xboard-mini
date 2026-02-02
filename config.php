<?php
session_start();
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'xboard_mini');
define('DB_USER', 'xboard_user');
define('DB_PASS', '');

function getDB() {
    try {
        return new PDO(
            "mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4",
            DB_USER, DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
    } catch (Exception $e) {
        die("数据库连接失败");
    }
}
