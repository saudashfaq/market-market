<?php
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

try {
    $pdo = db();
    
    // Get minimum offer percentage from system settings
    $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'min_offer_percentage'");
    $stmt->execute();
    $percentage = floatval($stmt->fetchColumn() ?: 70); // Default 70%
    
    echo json_encode([
        'success' => true,
        'percentage' => $percentage
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'percentage' => 70 // Fallback
    ]);
}
?>