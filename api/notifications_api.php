<?php
// Prevent any output before JSON headers
ob_start();

try {
    require_once __DIR__ . '/../config.php';
    require_once __DIR__ . '/../middlewares/auth.php';
    
    // Clear any previous output
    ob_clean();
    
    // Set JSON headers
    header('Content-Type: application/json');
    header('Cache-Control: no-cache, must-revalidate');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST');
    header('Access-Control-Allow-Headers: Content-Type');
    
    $pdo = db();
    
    // Check authentication
    $user = current_user();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }
    
    $userId = $user['id'];
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    
    switch ($action) {
        case 'count':
            // Get unread notification count
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as count 
                FROM notifications 
                WHERE user_id = ? AND is_read = 0
            ");
            $stmt->execute([$userId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'count' => (int)$result['count']
            ]);
            break;
            
        case 'list':
            // Get notifications list
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
            $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
            
            // Use direct values in query instead of placeholders for LIMIT/OFFSET
            $stmt = $pdo->prepare("
                SELECT * FROM notifications 
                WHERE user_id = ? 
                ORDER BY created_at DESC 
                LIMIT {$limit} OFFSET {$offset}
            ");
            $stmt->execute([$userId]);
            $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'notifications' => $notifications
            ]);
            break;
            
        case 'mark_read':
            // Mark single notification as read
            $notificationId = $_POST['id'] ?? $_GET['id'] ?? 0;
            
            $stmt = $pdo->prepare("
                UPDATE notifications 
                SET is_read = 1 
                WHERE id = ? AND user_id = ?
            ");
            $stmt->execute([$notificationId, $userId]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Notification marked as read'
            ]);
            break;
            
        case 'mark_all_read':
            // Mark all notifications as read
            $stmt = $pdo->prepare("
                UPDATE notifications 
                SET is_read = 1 
                WHERE user_id = ? AND is_read = 0
            ");
            $stmt->execute([$userId]);
            
            echo json_encode([
                'success' => true,
                'message' => 'All notifications marked as read'
            ]);
            break;
            
        default:
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Invalid action'
            ]);
            break;
    }
    
} catch (Exception $e) {
    // Clear any previous output
    ob_clean();
    
    // Log the error
    error_log("Notifications API Error: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Server error occurred'
    ]);
} finally {
    // End output buffering
    ob_end_flush();
}
?>