<?php require_once 'config.php'; checkAdmin();
$id = (int)$_GET['id'];
$db = getDB();
$stmt = $db->prepare("DELETE FROM nodes WHERE id=?");
$stmt->execute([$id]);
header("Location: node.php");
exit;
?>
