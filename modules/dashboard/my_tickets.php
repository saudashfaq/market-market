<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../middlewares/auth.php';

require_login();

$user = current_user();
$pdo = db();

// Get filter and pagination parameters
$status = $_GET['status'] ?? null;
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 10;
$offset = ($page - 1) * $perPage;

// Build WHERE clause
$whereClause = "WHERE user_id = ?";
$params = [$user['id']];

if ($status !== null) {
    $whereClause .= " AND status = ?";
    $params[] = $status;
}

// Get total count
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM tickets $whereClause");
$countStmt->execute($params);
$totalTickets = (int)$countStmt->fetchColumn();
$totalPages = ceil($totalTickets / $perPage);

// Get tickets - use direct values in query instead of binding LIMIT/OFFSET
$query = "SELECT * FROM tickets $whereClause ORDER BY updated_at DESC LIMIT $perPage OFFSET $offset";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Add message count to each ticket
foreach ($tickets as &$ticket) {
    $msgStmt = $pdo->prepare("SELECT COUNT(*) FROM ticket_messages WHERE ticket_id = ?");
    $msgStmt->execute([$ticket['id']]);
    $ticket['message_count'] = (int)$msgStmt->fetchColumn();
    
    // Get last message
    $lastMsgStmt = $pdo->prepare("SELECT message, created_at FROM ticket_messages WHERE ticket_id = ? ORDER BY created_at DESC LIMIT 1");
    $lastMsgStmt->execute([$ticket['id']]);
    $lastMsg = $lastMsgStmt->fetch(PDO::FETCH_ASSOC);
    $ticket['last_message'] = $lastMsg['message'] ?? null;
    $ticket['last_message_time'] = $lastMsg['created_at'] ?? null;
}

// Get ticket stats
$statsStmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) as open,
        SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) as closed
    FROM tickets
    WHERE user_id = ?
");
$statsStmt->execute([$user['id']]);
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
$stats = [
    'total' => (int)($stats['total'] ?? 0),
    'open' => (int)($stats['open'] ?? 0),
    'closed' => (int)($stats['closed'] ?? 0)
];

// Helper function for time ago
function timeAgo($datetime) {
    $timestamp = strtotime($datetime);
    $diff = time() - $timestamp;
    
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . ' minutes ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hours ago';
    if ($diff < 604800) return floor($diff / 86400) . ' days ago';
    return date('M d, Y', $timestamp);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Support Tickets</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .brand-color { color: #170835; }
        .brand-bg { background-color: #170835; }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    
    <div class="max-w-7xl mx-auto px-4 py-8">
        
        <!-- Header -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
            <div>
                <h1 class="text-3xl font-bold brand-color flex items-center">
                    <i class="fas fa-ticket mr-3"></i>
                    My Support Tickets
                </h1>
                <p class="text-gray-600 mt-2">Track and manage your support requests</p>
            </div>
            <a href="<?= url('index.php?p=dashboard&page=create_ticket') ?>" class="brand-bg text-white px-6 py-3 rounded-lg font-semibold hover:opacity-90 transition flex items-center">
                <i class="fas fa-plus mr-2"></i>
                Create New Ticket
            </a>
        </div>
        
        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-blue-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 text-sm font-medium">Total Tickets</p>
                        <p class="text-3xl font-bold text-gray-900 mt-1"><?= $stats['total'] ?></p>
                    </div>
                    <div class="bg-blue-100 p-4 rounded-full">
                        <i class="fas fa-ticket text-blue-600 text-2xl"></i>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-green-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 text-sm font-medium">Open Tickets</p>
                        <p class="text-3xl font-bold text-gray-900 mt-1"><?= $stats['open'] ?></p>
                    </div>
                    <div class="bg-green-100 p-4 rounded-full">
                        <i class="fas fa-circle-dot text-green-600 text-2xl"></i>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-gray-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 text-sm font-medium">Closed Tickets</p>
                        <p class="text-3xl font-bold text-gray-900 mt-1"><?= $stats['closed'] ?></p>
                    </div>
                    <div class="bg-gray-100 p-4 rounded-full">
                        <i class="fas fa-circle-check text-gray-600 text-2xl"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Filter Tabs -->
        <div class="bg-white rounded-xl shadow-md mb-6">
            <div class="flex border-b border-gray-200">
                <a href="?<?= http_build_query(array_merge($_GET, ['status' => null, 'page' => 1])) ?>" 
                   class="px-6 py-4 font-semibold <?= $status === null ? 'brand-color border-b-2 border-purple-600' : 'text-gray-600 hover:text-gray-900' ?>">
                    <i class="fas fa-list mr-2"></i>All Tickets
                </a>
                <a href="?<?= http_build_query(array_merge($_GET, ['status' => 'open', 'page' => 1])) ?>" 
                   class="px-6 py-4 font-semibold <?= $status === 'open' ? 'brand-color border-b-2 border-purple-600' : 'text-gray-600 hover:text-gray-900' ?>">
                    <i class="fas fa-circle-dot mr-2"></i>Open
                </a>
                <a href="?<?= http_build_query(array_merge($_GET, ['status' => 'closed', 'page' => 1])) ?>" 
                   class="px-6 py-4 font-semibold <?= $status === 'closed' ? 'brand-color border-b-2 border-purple-600' : 'text-gray-600 hover:text-gray-900' ?>">
                    <i class="fas fa-circle-check mr-2"></i>Closed
                </a>
            </div>
        </div>
        
        <!-- Tickets List -->
        <?php if (empty($tickets)): ?>
            <!-- Empty State -->
            <div class="bg-white rounded-xl shadow-md p-12 text-center">
                <div class="w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-6">
                    <i class="fas fa-ticket text-gray-400 text-4xl"></i>
                </div>
                <h3 class="text-2xl font-bold text-gray-800 mb-3">No Tickets Found</h3>
                <p class="text-gray-600 mb-6 max-w-md mx-auto">
                    <?php if ($status === 'open'): ?>
                        You don't have any open tickets at the moment.
                    <?php elseif ($status === 'closed'): ?>
                        You don't have any closed tickets yet.
                    <?php else: ?>
                        You haven't created any support tickets yet. Need help? Create your first ticket!
                    <?php endif; ?>
                </p>
                <a href="<?= url('index.php?p=dashboard&page=create_ticket') ?>" class="brand-bg text-white px-8 py-3 rounded-lg font-semibold hover:opacity-90 transition inline-flex items-center">
                    <i class="fas fa-plus mr-2"></i>
                    Create Your First Ticket
                </a>
            </div>
        <?php else: ?>
            <!-- Tickets Grid -->
            <div class="grid grid-cols-1 gap-6">
                <?php foreach ($tickets as $ticket): ?>
                    <div class="bg-white rounded-xl shadow-md hover:shadow-lg transition p-6">
                        <div class="flex flex-col md:flex-row justify-between items-start gap-4">
                            <!-- Ticket Info -->
                            <div class="flex-1">
                                <div class="flex items-start gap-3 mb-3">
                                    <div class="brand-bg text-white px-3 py-1 rounded-lg text-sm font-semibold">
                                        #<?= $ticket['id'] ?>
                                    </div>
                                    <?php if ($ticket['status'] === 'open'): ?>
                                        <span class="px-3 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-700 flex items-center">
                                            <i class="fas fa-circle-dot mr-1"></i>Open
                                        </span>
                                    <?php else: ?>
                                        <span class="px-3 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-700 flex items-center">
                                            <i class="fas fa-circle-check mr-1"></i>Closed
                                        </span>
                                    <?php endif; ?>
                                </div>
                                
                                <h3 class="text-xl font-bold text-gray-900 mb-2">
                                    <?= htmlspecialchars($ticket['subject']) ?>
                                </h3>
                                
                                <?php if ($ticket['last_message']): ?>
                                    <p class="text-gray-600 text-sm mb-3 line-clamp-2">
                                        <i class="fas fa-message mr-2"></i>
                                        <?= htmlspecialchars(substr($ticket['last_message'], 0, 100)) ?><?= strlen($ticket['last_message']) > 100 ? '...' : '' ?>
                                    </p>
                                <?php endif; ?>
                                
                                <div class="flex flex-wrap items-center gap-4 text-sm text-gray-500">
                                    <span>
                                        <i class="fas fa-calendar mr-1"></i>
                                        Created: <?= date('M d, Y', strtotime($ticket['created_at'])) ?>
                                    </span>
                                    <span>
                                        <i class="fas fa-clock mr-1"></i>
                                        Updated: <?= timeAgo($ticket['updated_at']) ?>
                                    </span>
                                    <span>
                                        <i class="fas fa-comments mr-1"></i>
                                        <?= $ticket['message_count'] ?> message<?= $ticket['message_count'] != 1 ? 's' : '' ?>
                                    </span>
                                </div>
                            </div>
                            
                            <!-- Action Button -->
                            <div class="flex items-center">
                                <a href="<?= url('index.php?p=dashboard&page=view_ticket&id=' . $ticket['id']) ?>" 
                                   class="brand-bg text-white px-6 py-3 rounded-lg font-semibold hover:opacity-90 transition flex items-center whitespace-nowrap">
                                    <i class="fas fa-eye mr-2"></i>
                                    View Ticket
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="mt-8 flex justify-center items-center gap-2">
                    <?php if ($page > 1): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" 
                           class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" 
                           class="px-4 py-2 rounded-lg font-semibold transition <?= $i === $page ? 'brand-bg text-white' : 'border border-gray-300 hover:bg-gray-50' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" 
                           class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
                
                <p class="text-center text-gray-600 text-sm mt-4">
                    Showing page <?= $page ?> of <?= $totalPages ?> (<?= $totalTickets ?> total tickets)
                </p>
            <?php endif; ?>
        <?php endif; ?>
        
    </div>
    
</body>
</html>
