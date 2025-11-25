<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/flash_helper.php';
$pdo = db();

if (isset($_GET['id'])) {
  $id = (int) $_GET['id'];
  $stmt = $pdo->prepare("DELETE FROM labels WHERE id = ?");
  $stmt->execute([$id]);
  setSuccessMessage("Label deleted successfully!");
}

header("Location: index.php?p=dashboard&page=categories");
exit;
?>
