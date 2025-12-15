<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../middlewares/auth.php';
require_once __DIR__ . '/../../includes/validation_helper.php';

require_role('superadmin');

$user = current_user();
$pdo = db();

// Get ticket ID (support both id and ticket_id params)
$ticketId = intval($_GET['id'] ?? $_GET['ticket_id'] ?? 0);

if (!$ticketId) {
    $_SESSION['error'] = 'Invalid ticket ID.';
    header('Location: ' . url('index.php?p=dashboard&page=admin_tickets'));
    exit;
}

// Get ticket details with user info
$stmt = $pdo->prepare("SELECT 
    t.id,
    t.subject,
    t.status,
    t.created_at,
    t.updated_at,
    u.id as user_id,
    u.name as user_name,
    u.email as user_email,
    (SELECT COUNT(*) FROM ticket_messages WHERE ticket_id = t.id) as message_count
FROM tickets t
LEFT JOIN users u ON t.user_id = u.id
WHERE t.id = ?");
$stmt->execute([$ticketId]);
$ticket = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$ticket) {
    $_SESSION['error'] = 'Ticket not found.';
    header('Location: ' . url('index.php?p=dashboard&page=admin_tickets'));
    exit;
}


// Handle status change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_status'])) {
    // Verify CSRF token
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['error'] = 'Invalid security token. Please try again.';
        header('Location: ' . url('index.php?p=dashboard&page=admin_ticket_view&id=' . $ticketId));
        exit;
    }

    $newStatus = $_POST['status'] ?? '';

    if (in_array($newStatus, ['open', 'closed'])) {
        try {
            $stmt = $pdo->prepare("UPDATE tickets SET status = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$newStatus, $ticketId]);

            // Send email if closing ticket
            if ($newStatus === 'closed' && file_exists(__DIR__ . '/../../includes/email_helper.php')) {
                try {
                    require_once __DIR__ . '/../../includes/email_helper.php';
                    $ticketData = [
                        'id' => $ticketId,
                        'subject' => $ticket['subject']
                    ];
                    sendTicketClosedEmail($ticket['user_email'], $ticket['user_name'], $ticketData);
                } catch (Exception $e) {
                    error_log("Email notification failed: " . $e->getMessage());
                }
            }

            $_SESSION['success'] = 'Ticket status updated to ' . $newStatus . '!';
            header('Location: ' . url('index.php?p=dashboard&page=admin_ticket_view&id=' . $ticketId));
            exit;
        } catch (Exception $e) {
            $_SESSION['error'] = 'Failed to update ticket status.';
            error_log("Status update error: " . $e->getMessage());
        }
    } else {
        $_SESSION['error'] = 'Invalid status value.';
    }
}

// Get all messages from database (including Tawk.to webhook messages)
$messagesStmt = $pdo->prepare("SELECT 
    tm.id,
    tm.message,
    tm.is_admin,
    tm.created_at,
    u.name as user_name
FROM ticket_messages tm
LEFT JOIN users u ON tm.user_id = u.id
WHERE tm.ticket_id = ?
ORDER BY tm.created_at ASC");
$messagesStmt->execute([$ticketId]);
$messages = $messagesStmt->fetchAll(PDO::FETCH_ASSOC);

// Generate CSRF token
$csrf_token = getCsrfToken();

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
        return date('M d, Y \a\t g:i A', $timestamp);
    }
}
?>

<div class="max-w-7xl mx-auto px-4 py-8">

    <!-- Header -->
    <div class="mb-6">
        <a href="<?= url('index.php?p=dashboard&page=admin_tickets') ?>" class="inline-flex items-center text-gray-600 hover:text-gray-900 mb-4">
            <i class="fas fa-arrow-left mr-2"></i> Back to All Tickets
        </a>
    </div>

    <!-- Error/Success Messages -->
    <?php if (isset($_SESSION['error'])): ?>
        <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6 rounded-lg">
            <div class="flex items-center">
                <i class="fas fa-exclamation-circle text-red-500 mr-3"></i>
                <p class="text-red-700"><?= $_SESSION['error'] ?></p>
            </div>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="bg-green-50 border-l-4 border-green-500 p-4 mb-6 rounded-lg">
            <div class="flex items-center">
                <i class="fas fa-check-circle text-green-500 mr-3"></i>
                <p class="text-green-700"><?= $_SESSION['success'] ?></p>
            </div>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Main Content -->
        <div class="lg:col-span-2">

            <!-- Ticket Header -->
            <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
                <div class="flex flex-col md:flex-row justify-between items-start gap-4 mb-4">
                    <div class="flex-1">
                        <div class="flex items-center gap-3 mb-3">
                            <div class="bg-[#170835] text-white px-4 py-2 rounded-lg text-lg font-bold">
                                #<?= $ticket['id'] ?>
                            </div>
                            <?php if ($ticket['status'] === 'open'): ?>
                                <span class="px-4 py-2 text-sm font-semibold rounded-full bg-green-100 text-green-700 flex items-center">
                                    <i class="fas fa-circle-dot mr-2"></i>Open
                                </span>
                            <?php else: ?>
                                <span class="px-4 py-2 text-sm font-semibold rounded-full bg-gray-100 text-gray-700 flex items-center">
                                    <i class="fas fa-circle-check mr-2"></i>Closed
                                </span>
                            <?php endif; ?>
                        </div>

                        <h1 class="text-2xl font-bold text-gray-900 mb-3">
                            <?= htmlspecialchars($ticket['subject']) ?>
                        </h1>

                        <div class="flex flex-wrap items-center gap-4 text-sm text-gray-600">
                            <span>
                                <i class="fas fa-calendar mr-2"></i>
                                Created: <?= date('M d, Y \a\t g:i A', strtotime($ticket['created_at'])) ?>
                            </span>
                            <span>
                                <i class="fas fa-clock mr-2"></i>
                                Updated: <?= timeAgo($ticket['updated_at']) ?>
                            </span>
                        </div>
                    </div>

                    <!-- Status Change Form -->
                    <div>
                        <form method="POST" action="" class="flex gap-2">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                            <input type="hidden" name="change_status" value="1">
                            <?php if ($ticket['status'] === 'open'): ?>
                                <button type="submit" name="status" value="closed" class="bg-gray-600 text-white px-4 py-2 rounded-lg font-semibold hover:bg-gray-700 transition flex items-center">
                                    <i class="fas fa-circle-check mr-2"></i>Close Ticket
                                </button>
                            <?php else: ?>
                                <button type="submit" name="status" value="open" class="bg-green-600 text-white px-4 py-2 rounded-lg font-semibold hover:bg-green-700 transition flex items-center">
                                    <i class="fas fa-circle-dot mr-2"></i>Reopen Ticket
                                </button>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Messages from Tawk.to -->
            <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
                <h2 class="text-xl font-bold text-gray-900 mb-4 flex items-center">
                    <i class="fas fa-comments mr-3 text-[#170835]"></i>
                    Conversation History
                    <span class="ml-3 text-sm font-normal text-gray-500">(Synced from Tawk.to)</span>
                </h2>

                <?php if (empty($messages)): ?>
                    <div class="bg-blue-50 border-l-4 border-blue-600 rounded-lg p-6 text-center">
                        <i class="fas fa-info-circle text-blue-600 text-2xl mb-3"></i>
                        <p class="text-gray-700 mb-2">No messages yet</p>
                        <p class="text-sm text-gray-600">Messages from Tawk.to will appear here automatically</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-4 max-h-[600px] overflow-y-auto">
                        <?php foreach ($messages as $msg): ?>
                            <?php if ($msg['is_admin']): ?>
                                <!-- Admin Message -->
                                <div class="flex justify-start">
                                    <div class="max-w-2xl">
                                        <div class="bg-purple-50 border-l-4 border-purple-600 rounded-lg p-4">
                                            <div class="flex items-center justify-between mb-2">
                                                <span class="font-semibold text-purple-700 flex items-center">
                                                    <i class="fas fa-shield-halved mr-2"></i>
                                                    <?= htmlspecialchars($msg['user_name'] ?? 'Admin') ?>
                                                </span>
                                                <span class="text-xs text-gray-500"><?= timeAgo($msg['created_at']) ?></span>
                                            </div>
                                            <p class="text-gray-700 whitespace-pre-wrap"><?= nl2br(htmlspecialchars($msg['message'])) ?></p>
                                        </div>
                                    </div>
                                </div>
                            <?php else: ?>
                                <!-- User Message -->
                                <div class="flex justify-end">
                                    <div class="max-w-2xl">
                                        <div class="bg-blue-50 border-l-4 border-blue-600 rounded-lg p-4">
                                            <div class="flex items-center justify-between mb-2">
                                                <span class="font-semibold text-blue-700 flex items-center">
                                                    <i class="fas fa-user mr-2"></i>
                                                    <?= htmlspecialchars($msg['user_name'] ?? 'User') ?>
                                                </span>
                                                <span class="text-xs text-gray-500"><?= timeAgo($msg['created_at']) ?></span>
                                            </div>
                                            <p class="text-gray-700 whitespace-pre-wrap"><?= nl2br(htmlspecialchars($msg['message'])) ?></p>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Live Chat Support Info -->
            <div class="bg-gradient-to-r from-purple-50 to-blue-50 border-l-4 border-purple-600 rounded-xl p-6">
                <div class="flex items-start">
                    <i class="fas fa-headset text-purple-600 text-2xl mr-4"></i>
                    <div>
                        <h3 class="text-lg font-bold text-gray-900 mb-2">Reply via Tawk.to</h3>
                        <p class="text-gray-700 mb-3">
                            Use the Tawk.to widget (bottom-right corner) or dashboard to reply to this user. Messages will automatically sync here.
                        </p>
                        <a href="https://dashboard.tawk.to" target="_blank" class="inline-flex items-center bg-purple-600 text-white px-4 py-2 rounded-lg font-semibold hover:bg-purple-700 transition text-sm">
                            <i class="fas fa-external-link-alt mr-2"></i>
                            Open Tawk.to Dashboard
                        </a>
                    </div>
                </div>
            </div>

        </div>

        <!-- Sidebar - User Info -->
        <div class="lg:col-span-1">
            <!-- Live Chat Info -->
            <div class="bg-gradient-to-r from-purple-50 to-blue-50 border-l-4 border-purple-600 rounded-xl p-6 mb-6">
                <h3 class="text-lg font-bold text-gray-900 mb-2 flex items-center">
                    <i class="fas fa-comments text-purple-600 mr-2"></i>
                    Live Chat
                </h3>
                <p class="text-sm text-gray-700 mb-3">
                    Chat with the user in real-time using the Tawk.to widget in the bottom-right corner.
                </p>
                <div class="flex items-center text-xs text-gray-600">
                    <i class="fas fa-info-circle mr-2"></i>
                    <span>All chat messages are synced with Tawk.to</span>
                </div>
            </div>

            <!-- User Info -->
            <div class="bg-white rounded-xl shadow-lg p-6 sticky top-8">
                <h3 class="text-lg font-bold text-gray-900 mb-4 flex items-center">
                    <i class="fas fa-user-circle mr-2 text-[#170835]"></i>
                    User Information
                </h3>

                <div class="space-y-4">
                    <div>
                        <p class="text-sm text-gray-600 mb-1">Name</p>
                        <p class="font-semibold text-gray-900"><?= htmlspecialchars($ticket['user_name']) ?></p>
                    </div>

                    <div>
                        <p class="text-sm text-gray-600 mb-1">Email</p>
                        <p class="font-semibold text-gray-900"><?= htmlspecialchars($ticket['user_email']) ?></p>
                    </div>

                    <div class="pt-4 border-t">
                        <p class="text-sm text-gray-600 mb-2">Quick Actions</p>
                        <a href="mailto:<?= htmlspecialchars($ticket['user_email']) ?>" class="block w-full text-center bg-blue-600 text-white px-4 py-2 rounded-lg font-semibold hover:bg-blue-700 transition mb-2">
                            <i class="fas fa-envelope mr-2"></i>Email User
                        </a>
                    </div>
                </div>
            </div>
        </div>

    </div>

</div>

<!-- Tawk.to Live Chat Widget for Admin -->
<!--Start of Tawk.to Script-->
<script type="text/javascript">
    var Tawk_API = Tawk_API || {},
        Tawk_LoadStart = new Date();
    (function() {
        var s1 = document.createElement("script"),
            s0 = document.getElementsByTagName("script")[0];
        s1.async = true;
        s1.src = 'https://embed.tawk.to/6913915e007b8c195b379e98/1j9q6vn2p';
        s1.charset = 'UTF-8';
        s1.setAttribute('crossorigin', '*');
        s0.parentNode.insertBefore(s1, s0);
    })();

    // Set custom attributes for admin viewing user ticket
    Tawk_API.onLoad = function() {
        Tawk_API.setAttributes({
            'ticket_id': '<?= $ticketId ?>',
            'ticket_subject': '<?= htmlspecialchars($ticket['subject']) ?>',
            'ticket_status': '<?= $ticket['status'] ?>',
            'user_name': '<?= htmlspecialchars($ticket['user_name']) ?>',
            'user_email': '<?= htmlspecialchars($ticket['user_email']) ?>',
            'admin_view': 'true',
            'admin_name': '<?= htmlspecialchars($user['name']) ?>'
        });
    };
</script>
<!--End of Tawk.to Script-->