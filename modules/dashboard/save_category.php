<?php
require_once __DIR__ . "/../../config.php";
require_once __DIR__ . "/../../includes/flash_helper.php";
require_once __DIR__ . "/../../includes/validation_helper.php";
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF validation
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        setErrorMessage('Invalid request. Please try again.');
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
    }

    // Create validator with form data
    $validator = new FormValidator($_POST);
    
    // Validate category fields
    $validator
        ->required('name', 'Category name is required')
        ->custom('name', function($value) {
            return strlen(trim($value)) >= 2;
        }, 'Category name must be at least 2 characters long')
        ->custom('name', function($value) {
            return strlen(trim($value)) <= 50;
        }, 'Category name must not exceed 50 characters');

    // Check for duplicate category name
    if ($validator->passes()) {
        $name = trim($_POST['name']);
        $stmt = $pdo->prepare("SELECT id FROM categories WHERE name = ?");
        $stmt->execute([$name]);
        if ($stmt->fetch()) {
            $validator->custom('name', function() { return false; }, 'A category with this name already exists');
        }
    }

    // If validation fails, store errors and redirect
    if ($validator->fails()) {
        $validator->storeErrors();
        header("Location: index.php?p=dashboard&page=categories");
        exit;
    }

    $name = trim($_POST['name']);
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $name), '-'));

    $stmt = $pdo->prepare("INSERT INTO categories (name, slug) VALUES (?, ?)");
    $stmt->execute([$name, $slug]);
    
    FormValidator::clearOldInput();
    setSuccessMessage("Category created successfully!");
    header("Location: index.php?p=dashboard&page=categories");
    exit;
}