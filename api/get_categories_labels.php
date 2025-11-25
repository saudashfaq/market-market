<?php
/**
 * API to fetch categories and labels dynamically
 * Used for real-time updates in listing forms
 */

require_once __DIR__ . '/../../config.php';

header('Content-Type: application/json');

try {
    $pdo = db();
    
    // Fetch categories
    $categoriesStmt = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC");
    $categories = $categoriesStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch labels
    $labelsStmt = $pdo->query("SELECT id, name FROM labels ORDER BY name ASC");
    $labels = $labelsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'categories' => $categories,
        'labels' => $labels,
        'timestamp' => time()
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to fetch data',
        'message' => $e->getMessage()
    ]);
}
