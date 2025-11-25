<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/validation_helper.php';
require_once __DIR__ . '/../../includes/flash_helper.php';
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

  if (!empty($name)) {
    // Generate slug
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $name), '-'));

    if ($id) {
      // Update existing label
      $stmt = $pdo->prepare("UPDATE labels SET name = ?, slug = ? WHERE id = ?");
      $stmt->execute([$name, $slug, $id]);
    } else {
      // Insert new label
      $stmt = $pdo->prepare("INSERT INTO labels (name, slug, created_at) VALUES (?, ?, NOW())");
      $stmt->execute([$name, $slug]);
    }
  }

  // Redirect after save
  header("Location: index.php?p=dashboard&page=categories");
  exit;
}
?>
