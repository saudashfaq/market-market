<?php
require_once '../../config.php';
require_once '../bidding/BiddingSystem.php';

// Check admin authentication
if (!isset($_SESSION['user']) || !isset($_SESSION['user']['role'])) {
    header('Location: ' . url('index.php?p=login'));
    exit;
}

$userRole = $_SESSION['user']['role'];
if (!in_array($userRole, ['admin', 'superadmin', 'super_admin', 'superAdmin'])) {
    header('Location: ' . url('index.php?p=login'));
    exit;
}

$pdo = db();
$biddingSystem = new BiddingSystem($pdo);

// Get filter parameters
$actionType = $_GET['action_type'] ?? '';
$itemId = $_GET['item_id'] ?? '';
$limit = intval($_GET['limit'] ?? 100);
$page = intval($_GET['page'] ?? 1);
$offset = ($page - 1) * $limit;

// Get logs with filters
$logs = [];
$totalLogs = 0;

try {
    // Build query for logs
    $sql = "
        SELECT bl.*, 
               i.title as item_title,
               i.reserved_amount,
               u1.username as user_name,
               u1.email as user_email,
               u2.username as admin_name
        FROM bidding_logs bl
        LEFT JOIN items i ON bl.item_id = i.id
        LEFT JOIN users u1 ON bl.user_id = u1.id
        LEFT JOIN users u2 ON bl.admin_id = u2.id
        WHERE 1=1
    ";
    
    $params = [];
    
    if ($actionType) {
        $sql .= " AND bl.action_type = ?";
        $params[] = $actionType;
    }
    
    if ($itemId) {
        $sql .= " AND bl.item_id = ?";
        $params[] = $itemId;
    }
    
    // Get total count
    $countSql = str_replace("SELECT bl.*, i.title as item_title, i.reserved_amount, u1.username as user_name, u1.email as user_email, u2.username as admin_name", "SELECT COUNT(*)", $sql);
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $totalLogs = $countStmt->fetchColumn();
    
    // Get paginated results
    $sql .= " ORDER BY bl.created_at DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error = "Error fetching logs: " . $e->getMessage();
}

// Get available action types for filter
$actionTypes = [];
try {
    $stmt = $pdo->query("SELECT DISTINCT action_type FROM bidding_logs ORDER BY action_type");
    $actionTypes = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    // Ignore error
}

// Calculate pagination
$totalPages = ceil($totalLogs / $limit);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bidding System Logs - Critical Activity Monitor</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f5f5f5; }
        .container { max-width: 1400px; margin: 0 auto; padding: 20px; }
        .header { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .section { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .filters { background: #f8fafc; padding: 20px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #e5e7eb; }
        .filter-row { display: flex; gap: 15px; align-items: end; flex-wrap: wrap; }
        .filter-group { flex: 1; min-width: 200px; }
        .filter-group label { display: block; margin-bottom: 5px; font-weight: 500; color: #374151; }
        .filter-group select, .filter-group input { width: 100%; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 4px; }
        .btn { padding: 10px 20px; background: #2563eb; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: 500; }
        .btn:hover { background: #1d4ed8; }
        .btn-secondary { background: #6b7280; }
        .btn-secondary:hover { background: #4b5563; }
        .table { width: 100%; border-collapse: collapse; font-size: 0.9em; }
        .table th, .table td { padding: 10px; text-align: left; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
        .table th { background: #f9fafb; font-weight: 600; position: sticky; top: 0; }
        .table tbody tr:hover { background: #f9fafb; }
        .badge { padding: 3px 8px; border-radius: 4px; font-size: 0.8em; font-weight: 500; }
        .badge-success { background: #d1fae5; color: #065f46; }
        .badge-warning { background: #fef3c7; color: #92400e; }
        .badge-error { background: #fee2e2; color: #991b1b; }
        .badge-info { background: #dbeafe; color: #1e40af; }
        .badge-purple { background: #e9d5ff; color: #7c2d12; }
        .pagination { display: flex; justify-content: center; gap: 10px; margin-top: 20px; }
        .pagination a, .pagination span { padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 4px; text-decoration: none; color: #374151; }
        .pagination a:hover { background: #f3f4f6; }
        .pagination .current { background: #2563eb; color: white; border-color: #2563eb; }
        .log-details { font-size: 0.85em; color: #6b7280; }
        .json-data { background: #f3f4f6; padding: 8px; border-radius: 4px; font-family: monospace; font-size: 0.8em; max-width: 300px; overflow: auto; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px; }
        .stat-card { background: white; padding: 15px; border-radius: 6px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); text-align: center; }
        .stat-number { font-size: 1.8em; font-weight: bold; color: #2563eb; }
        .stat-label { color: #6b7280; margin-top: 5px; font-size: 0.9em; }
        .critical-action { border-left: 4px solid #dc2626; }
        .important-action { border-left: 4px solid #f59e0b; }
        .normal-action { border-left: 4px solid #10b981; }
        .system-action { border-left: 4px solid #7c3aed; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔍 Bidding System Logs - Critical Activity Monitor</h1>
            <p>Complete audit trail of all bidding system activities and critical operations</p>
        </div>

        <?php if (isset($error)): ?>
            <div class="section" style="background: #fee2e2; border: 1px solid #fca5a5;">
                <p style="color: #991b1b;"><?= htmlspecialchars($error) ?></p>
            </div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?= number_format($totalLogs) ?></div>
                <div class="stat-label">Total Log Entries</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= count(array_filter($logs, fn($log) => $log['action_type'] === 'bid_created')) ?></div>
                <div class="stat-label">Bids Created (This Page)</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= count(array_filter($logs, fn($log) => $log['action_type'] === 'commission_changed')) ?></div>
                <div class="stat-label">Commission Changes (This Page)</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= count(array_filter($logs, fn($log) => $log['action_type'] === 'auction_ended')) ?></div>
                <div class="stat-label">Auctions Ended (This Page)</div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters">
            <form method="GET">
                <div class="filter-row">
                    <div class="filter-group">
                        <label for="action_type">Action Type</label>
                        <select name="action_type" id="action_type">
                            <option value="">All Actions</option>
                            <?php foreach ($actionTypes as $type): ?>
                                <option value="<?= htmlspecialchars($type) ?>" <?= $actionType === $type ? 'selected' : '' ?>>
                                    <?= ucwords(str_replace('_', ' ', $type)) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="item_id">Item ID</label>
                        <input type="number" name="item_id" id="item_id" value="<?= htmlspecialchars($itemId) ?>" placeholder="Filter by item ID">
                    </div>
                    
                    <div class="filter-group">
                        <label for="limit">Records per page</label>
                        <select name="limit" id="limit">
                            <option value="50" <?= $limit === 50 ? 'selected' : '' ?>>50</option>
                            <option value="100" <?= $limit === 100 ? 'selected' : '' ?>>100</option>
                            <option value="200" <?= $limit === 200 ? 'selected' : '' ?>>200</option>
                            <option value="500" <?= $limit === 500 ? 'selected' : '' ?>>500</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <button type="submit" class="btn">Apply Filters</button>
                        <a href="?" class="btn btn-secondary">Clear</a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Logs Table -->
        <div class="section">
            <h2>Activity Logs (Page <?= $page ?> of <?= $totalPages ?>)</h2>
            
            <?php if (empty($logs)): ?>
                <p style="text-align: center; color: #6b7280; padding: 40px;">No logs found matching your criteria.</p>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>Action</th>
                                <th>Item</th>
                                <th>User</th>
                                <th>Old Value</th>
                                <th>New Value</th>
                                <th>Additional Data</th>
                                <th>IP Address</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $log): ?>
                                <?php
                                // Determine row class based on action criticality
                                $rowClass = 'normal-action';
                                switch ($log['action_type']) {
                                    case 'commission_changed':
                                    case 'auction_ended':
                                        $rowClass = 'critical-action';
                                        break;
                                    case 'bid_rejected':
                                    case 'reserve_changed':
                                        $rowClass = 'important-action';
                                        break;
                                    case 'bid_created':
                                    case 'bid_accepted':
                                        $rowClass = 'normal-action';
                                        break;
                                    default:
                                        $rowClass = 'system-action';
                                }
                                
                                // Badge class for action type
                                $badgeClass = 'badge-info';
                                switch ($log['action_type']) {
                                    case 'bid_created':
                                    case 'bid_accepted':
                                        $badgeClass = 'badge-success';
                                        break;
                                    case 'bid_rejected':
                                        $badgeClass = 'badge-error';
                                        break;
                                    case 'commission_changed':
                                    case 'reserve_changed':
                                        $badgeClass = 'badge-warning';
                                        break;
                                    case 'auction_ended':
                                        $badgeClass = 'badge-purple';
                                        break;
                                }
                                ?>
                                <tr class="<?= $rowClass ?>">
                                    <td>
                                        <strong><?= date('M j, Y', strtotime($log['created_at'])) ?></strong><br>
                                        <small><?= date('g:i:s A', strtotime($log['created_at'])) ?></small>
                                    </td>
                                    <td>
                                        <span class="badge <?= $badgeClass ?>">
                                            <?= ucwords(str_replace('_', ' ', $log['action_type'])) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($log['item_title']): ?>
                                            <strong><?= htmlspecialchars($log['item_title']) ?></strong><br>
                                            <small>ID: <?= $log['item_id'] ?></small>
                                            <?php if ($log['reserved_amount']): ?>
                                                <br><small>Reserved: $<?= number_format($log['reserved_amount'], 2) ?></small>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span style="color: #6b7280;">System-wide</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($log['user_name']): ?>
                                            <strong><?= htmlspecialchars($log['user_name']) ?></strong><br>
                                            <small><?= htmlspecialchars($log['user_email']) ?></small>
                                        <?php elseif ($log['admin_name']): ?>
                                            <strong>Admin: <?= htmlspecialchars($log['admin_name']) ?></strong>
                                        <?php else: ?>
                                            <span style="color: #6b7280;">System</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($log['old_value'] !== null): ?>
                                            $<?= number_format($log['old_value'], 2) ?>
                                        <?php else: ?>
                                            <span style="color: #9ca3af;">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($log['new_value'] !== null): ?>
                                            <strong>$<?= number_format($log['new_value'], 2) ?></strong>
                                        <?php else: ?>
                                            <span style="color: #9ca3af;">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($log['additional_data']): ?>
                                            <?php 
                                            $data = json_decode($log['additional_data'], true);
                                            if ($data): ?>
                                                <div class="json-data">
                                                    <?php foreach ($data as $key => $value): ?>
                                                        <div><strong><?= htmlspecialchars($key) ?>:</strong> <?= htmlspecialchars(is_array($value) ? json_encode($value) : $value) ?></div>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php else: ?>
                                                <div class="json-data"><?= htmlspecialchars($log['additional_data']) ?></div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span style="color: #9ca3af;">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($log['ip_address'] ?? 'N/A') ?><br>
                                        <?php if ($log['user_agent']): ?>
                                            <small style="color: #6b7280;" title="<?= htmlspecialchars($log['user_agent']) ?>">
                                                <?= htmlspecialchars(substr($log['user_agent'], 0, 30)) ?>...
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">← Previous</a>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <?php if ($i === $page): ?>
                            <span class="current"><?= $i ?></span>
                        <?php else: ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">Next →</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Action Buttons -->
        <div class="section">
            <div style="display: flex; gap: 10px; justify-content: center;">
                <button class="btn btn-secondary" onclick="window.location.reload()">
                    🔄 Refresh Logs
                </button>
                <a href="admin_bidding_dashboard.php" class="btn btn-secondary">
                    ← Back to Dashboard
                </a>
                <button class="btn" onclick="window.print()">
                    🖨️ Print Report
                </button>
            </div>
        </div>
    </div>

    <script>
        // Auto-refresh every 30 seconds
        setInterval(function() {
            if (document.hidden === false) {
                window.location.reload();
            }
        }, 30000);
    </script>
</body>
</html>