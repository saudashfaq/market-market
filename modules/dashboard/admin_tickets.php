<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../middlewares/auth.php';

require_role('superadmin');

$user = current_user();
$pdo = db();

// Get filter and pagination parameters
$status = $_GET['status'] ?? null;
$search = trim($_GET['search'] ?? '');
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Build query
$sql = "SELECT 
    t.id,
    t.subject,
    t.status,
    t.created_at,
    t.updated_at,
    u.name as user_name,
    u.email as user_email,
    (SELECT COUNT(*) FROM ticket_messages WHERE ticket_id = t.id) as message_count,
    (SELECT message FROM ticket_messages WHERE ticket_id = t.id ORDER BY created_at DESC LIMIT 1) as last_message
FROM tickets t
LEFT JOIN users u ON t.user_id = u.id
WHERE 1=1";

$params = [];

if ($status) {
    $sql .= " AND t.status = ?";
    $params[] = $status;
}

if ($search) {
    $sql .= " AND (t.subject LIKE ? OR u.name LIKE ? OR u.email LIKE ?)";
    $searchParam = '%' . $search . '%';
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

// Get total count
$countSql = "SELECT COUNT(*) as total FROM tickets t LEFT JOIN users u ON t.user_id = u.id WHERE 1=1";
$countParams = [];
if ($status) {
    $countSql .= " AND t.status = ?";
    $countParams[] = $status;
}
if ($search) {
    $countSql .= " AND (t.subject LIKE ? OR u.name LIKE ? OR u.email LIKE ?)";
    $countParams[] = $searchParam;
    $countParams[] = $searchParam;
    $countParams[] = $searchParam;
}

$countStmt = $pdo->prepare($countSql);
$countStmt->execute($countParams);
$totalTickets = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
$totalPages = ceil($totalTickets / $perPage);

// Get tickets
$sql .= " ORDER BY t.updated_at DESC LIMIT " . intval($perPage) . " OFFSET " . intval($offset);

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get ticket stats
$statsStmt = $pdo->query("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) as open,
    SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) as closed
FROM tickets");
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

// Helper function for time ago
if (!function_exists('timeAgo')) {
    function timeAgo($datetime)
    {
        $timestamp = strtotime($datetime);
        $diff = time() - $timestamp;

        if ($diff < 60) return 'Just now';
        if ($diff < 3600) return floor($diff / 60) . ' min ago';
        if ($diff < 86400) return floor($diff / 3600) . ' hours ago';
        if ($diff < 604800) return floor($diff / 86400) . ' days ago';
        return date('M d, Y', $timestamp);
    }
}
?>

<div class="max-w-7xl mx-auto px-4 py-8">

    <!-- Header -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-[#170835] flex items-center">
            <i class="fas fa-shield-halved mr-3"></i>
            Admin - Support Tickets
        </h1>
        <p class="text-gray-600 mt-2">Manage and respond to all user support requests</p>
    </div>

    <!-- Tawk.to Live Chat Info -->
    <div class="bg-gradient-to-r from-purple-50 to-blue-50 border-l-4 border-purple-600 rounded-xl p-6 mb-8">
        <div class="flex items-start">
            <i class="fas fa-comments text-purple-600 text-3xl mr-4"></i>
            <div class="flex-1">
                <h3 class="text-lg font-bold text-gray-900 mb-2">Live Chat Support via Tawk.to</h3>
                <p class="text-gray-700 mb-3">
                    Users can contact you through Tawk.to live chat. All conversations are managed in your Tawk.to dashboard.
                </p>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                    <div class="flex items-center text-gray-600">
                        <i class="fas fa-check-circle text-green-600 mr-2"></i>
                        <span>Real-time chat notifications</span>
                    </div>
                    <div class="flex items-center text-gray-600">
                        <i class="fas fa-check-circle text-green-600 mr-2"></i>
                        <span>Email alerts for new messages</span>
                    </div>
                    <div class="flex items-center text-gray-600">
                        <i class="fas fa-check-circle text-green-600 mr-2"></i>
                        <span>Mobile app available</span>
                    </div>
                    <div class="flex items-center text-gray-600">
                        <i class="fas fa-check-circle text-green-600 mr-2"></i>
                        <span>Chat history saved automatically</span>
                    </div>
                </div>
                <div class="mt-4 pt-4 border-t border-purple-200">
                    <a href="https://dashboard.tawk.to" target="_blank" class="inline-flex items-center bg-purple-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-purple-700 transition">
                        <i class="fas fa-external-link-alt mr-2"></i>
                        Open Tawk.to Dashboard
                    </a>
                    <span class="ml-4 text-sm text-gray-600">
                        <i class="fas fa-info-circle mr-1"></i>
                        Login to view and respond to user messages
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="bg-gradient-to-br from-blue-500 to-blue-600 text-white rounded-xl shadow-lg p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-blue-100 text-sm font-medium">Total Tickets</p>
                    <p class="text-4xl font-bold mt-2"><?= $stats['total'] ?></p>
                </div>
                <div class="bg-white bg-opacity-20 p-4 rounded-full">
                    <i class="fas fa-ticket text-3xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-gradient-to-br from-green-500 to-green-600 text-white rounded-xl shadow-lg p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-green-100 text-sm font-medium">Open Tickets</p>
                    <p class="text-4xl font-bold mt-2"><?= $stats['open'] ?></p>
                </div>
                <div class="bg-white bg-opacity-20 p-4 rounded-full">
                    <i class="fas fa-circle-dot text-3xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-gradient-to-br from-gray-500 to-gray-600 text-white rounded-xl shadow-lg p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-100 text-sm font-medium">Closed Tickets</p>
                    <p class="text-4xl font-bold mt-2"><?= $stats['closed'] ?></p>
                </div>
                <div class="bg-white bg-opacity-20 p-4 rounded-full">
                    <i class="fas fa-circle-check text-3xl"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters and Search -->
    <div class="bg-white rounded-xl shadow-md mb-6">
        <div class="p-6">
            <div class="flex flex-col md:flex-row gap-4">
                <!-- Search -->
                <div class="flex-1">
                    <form method="GET" action="" class="flex gap-2">
                        <input type="hidden" name="p" value="dashboard">
                        <input type="hidden" name="page" value="admin_tickets">
                        <?php if ($status): ?>
                            <input type="hidden" name="status" value="<?= htmlspecialchars($status) ?>">
                        <?php endif; ?>
                        <input
                            type="text"
                            name="search"
                            placeholder="Search by subject, user name, or email..."
                            value="<?= htmlspecialchars($search) ?>"
                            class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                        <button type="submit" class="bg-[#170835] text-white px-6 py-2 rounded-lg font-semibold hover:opacity-90 transition">
                            <i class="fas fa-search mr-2"></i>Search
                        </button>
                        <?php if ($search): ?>
                            <a href="<?= url('dashboard/admin_tickets' . ($status ? '?status=' . $status : '')) ?>" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                                <i class="fas fa-times"></i>
                            </a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>

        <!-- Filter Tabs -->
        <div class="flex border-t border-gray-200">
            <a href="<?= url('dashboard/admin_tickets' . ($search ? '?search=' . urlencode($search) : '')) ?>"
                class="px-6 py-4 font-semibold <?= $status === null ? 'text-[#170835] border-b-2 border-purple-600' : 'text-gray-600 hover:text-gray-900' ?>">
                <i class="fas fa-list mr-2"></i>All Tickets
            </a>
            <a href="<?= url('dashboard/admin_tickets?status=open' . ($search ? '&search=' . urlencode($search) : '')) ?>"
                class="px-6 py-4 font-semibold <?= $status === 'open' ? 'text-[#170835] border-b-2 border-purple-600' : 'text-gray-600 hover:text-gray-900' ?>">
                <i class="fas fa-circle-dot mr-2"></i>Open
            </a>
            <a href="<?= url('dashboard/admin_tickets?status=closed' . ($search ? '&search=' . urlencode($search) : '')) ?>"
                class="px-6 py-4 font-semibold <?= $status === 'closed' ? 'text-[#170835] border-b-2 border-purple-600' : 'text-gray-600 hover:text-gray-900' ?>">
                <i class="fas fa-circle-check mr-2"></i>Closed
            </a>
        </div>
    </div>

    <!-- Tickets Table -->
    <?php if (empty($tickets)): ?>
        <!-- Empty State -->
        <div class="bg-white rounded-xl shadow-md p-12 text-center">
            <div class="w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-6">
                <i class="fas fa-ticket text-gray-400 text-4xl"></i>
            </div>
            <h3 class="text-2xl font-bold text-gray-800 mb-3">No Tickets Found</h3>
            <p class="text-gray-600">
                <?php if ($search): ?>
                    No tickets match your search criteria.
                <?php elseif ($status === 'open'): ?>
                    There are no open tickets at the moment.
                <?php elseif ($status === 'closed'): ?>
                    There are no closed tickets yet.
                <?php else: ?>
                    No support tickets have been created yet.
                <?php endif; ?>
            </p>
        </div>
    <?php else: ?>
        <!-- Tickets List -->
        <div class="bg-white rounded-xl shadow-md overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Ticket</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">User</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Messages</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Updated</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($tickets as $ticket): ?>
                            <tr class="hover:bg-gray-50 transition">
                                <td class="px-6 py-4">
                                    <div class="flex items-start gap-3">
                                        <div class="bg-[#170835] text-white px-3 py-1 rounded-lg text-sm font-bold">
                                            #<?= $ticket['id'] ?>
                                        </div>
                                        <div>
                                            <p class="font-semibold text-gray-900"><?= htmlspecialchars($ticket['subject']) ?></p>
                                            <?php if ($ticket['last_message']): ?>
                                                <p class="text-sm text-gray-500 mt-1">
                                                    <?= htmlspecialchars(substr($ticket['last_message'], 0, 60)) ?><?= strlen($ticket['last_message']) > 60 ? '...' : '' ?>
                                                </p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div>
                                        <p class="font-medium text-gray-900"><?= htmlspecialchars($ticket['user_name']) ?></p>
                                        <p class="text-sm text-gray-500"><?= htmlspecialchars($ticket['user_email']) ?></p>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <?php if ($ticket['status'] === 'open'): ?>
                                        <span class="px-3 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-700 inline-flex items-center">
                                            <i class="fas fa-circle-dot mr-1"></i>Open
                                        </span>
                                    <?php else: ?>
                                        <span class="px-3 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-700 inline-flex items-center">
                                            <i class="fas fa-circle-check mr-1"></i>Closed
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="text-gray-700 font-medium">
                                        <i class="fas fa-comments mr-1"></i>
                                        <?= $ticket['message_count'] ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="text-sm text-gray-600">
                                        <?= timeAgo($ticket['updated_at']) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <a href="<?= url('dashboard/admin_ticket_view?id=' . $ticket['id']) ?>"
                                        class="bg-[#170835] text-white px-4 py-2 rounded-lg text-sm font-semibold hover:opacity-90 transition inline-flex items-center">
                                        <i class="fas fa-eye mr-2"></i>View
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div class="mt-8 flex justify-center items-center gap-2">
                <?php
                $queryParams = [];
                if ($status) $queryParams['status'] = $status;
                if ($search) $queryParams['search'] = $search;
                ?>
                <?php if ($page > 1): ?>
                    <a href="<?= url('dashboard/admin_tickets?' . http_build_query(array_merge($queryParams, ['page' => $page - 1]))) ?>"
                        class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                <?php endif; ?>

                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                    <a href="<?= url('dashboard/admin_tickets?' . http_build_query(array_merge($queryParams, ['page' => $i]))) ?>"
                        class="px-4 py-2 rounded-lg font-semibold transition <?= $i === $page ? 'bg-[#170835] text-white' : 'border border-gray-300 hover:bg-gray-50' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>

                <?php if ($page < $totalPages): ?>
                    <a href="<?= url('dashboard/admin_tickets?' . http_build_query(array_merge($queryParams, ['page' => $page + 1]))) ?>"
                        class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>

</div>