<?php
require_once __DIR__ . "/../middlewares/auth.php";
if (!function_exists('log_action')) {
    /**
     * Record an action into the system logs
     *
     * @param string $action — Short title (e.g. "Add Listing", "Login", "Delete Offer")
     * @param string $details — Optional detailed message or description
     * @param string $type — Optional type (e.g. "auth", "listing", "system")
     * @param int|null $user_id — Optional manual user ID
     * @param string|null $role — Optional manual role
     */
    function log_action($action, $details = '', $type = 'system', $user_id = null, $role = null)
    {
        try {
            $pdo = db();

            // ✅ Always prefer middleware helper
            $user = current_user();
            if ($user) {
                $user_id = $user_id ?: $user['id'];
                $role = $role ?: $user['role'];
            } else {
                $user_id = $user_id ?: null;
                $role = $role ?: 'guest';
            }

            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

            $stmt = $pdo->prepare("
                INSERT INTO logs (user_id, role, action, details, ip_address, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$user_id, $role, $action, $details, $ip]);
        }catch (Throwable $e) {
            $fallbackDir = __DIR__ . '/../storage';
            if (!is_dir($fallbackDir)) {
                mkdir($fallbackDir, 0777, true);
            }
            $fallback = $fallbackDir . '/log_fallback.txt';
            $line = "[" . date('Y-m-d H:i:s') . "] {$action} - {$details} ({$e->getMessage()})\n";
            @file_put_contents($fallback, $line, FILE_APPEND);
        }
        
    }
}
