<?php
session_start();
define('ROOT', dirname(__DIR__));
$db = new SQLite3(ROOT . '/database.db');
$db->busyTimeout(5000);

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
$public_routes = ['/', '/login'];

if (!isset($_SESSION['admin']) && !in_array($path, $public_routes)) {
    header('Location: /');
    exit;
}

switch ($path) {
    case '/':
    case '/login':
        include ROOT . '/pages/login.php';
        break;
    case '/admin':
        include ROOT . '/pages/admin.php';
        break;
    case '/user/list':
        include ROOT . '/pages/user.php';
        break;
    case '/node/list':
        include ROOT . '/pages/node.php';
        break;
    default:
        http_response_code(404);
        echo '404 Not Found';
        break;
}
