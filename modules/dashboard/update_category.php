<?php
require_once __DIR__ . "/../../config.php";
require_once __DIR__ . "/../../includes/validation_helper.php";
require_once __DIR__ . "/../../includes/flash_helper.php";
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // CSRF validation
  if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
    setErrorMessage('Invalid request. Please try again.');
    header("Location: " . $_SERVER['REQUEST_URI']);
    exit;
  }
  $id = $_POST['id'] ?? null;
  $name = trim($_POST['name'] ?? '');
  
  if ($id && $name) {
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $name), '-'));

    $stmt = $pdo->prepare("UPDATE categories SET name = ?, slug = ? WHERE id = ?");
    $stmt->execute([$name, $slug, $id]);
  }
}

 header("Location: index.php?p=dashboard&page=categories");
  exit;
?>
