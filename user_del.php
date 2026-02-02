<?php require_once 'config.php'; checkAdmin();
$id = (int)$_GET['id'];
$db = getDB();
$stmt = $db->prepare("DELETE FROM users WHERE id=?");
$stmt->execute([$id]);
header("Location: user.php");
exit;
?>
