<?php
require_once __DIR__ . "/../../config.php";
require_once __DIR__ . "/../../includes/flash_helper.php";
$pdo = db();

$id = (int)$_GET['id'];
$pdo->prepare("DELETE FROM categories WHERE id = ?")->execute([$id]);
setSuccessMessage("Category deleted successfully!");
header("Location: index.php?p=dashboard&page=categories");
exit;

