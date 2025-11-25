<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../middlewares/auth.php';
require_once __DIR__ . '/../../includes/validation_helper.php';
require_once __DIR__ . '/../../includes/ticket_helper.php';

require_login();

$user = current_user();
$pdo = db();

// Get ticket ID
$ticketId = intval($_GET['id'] ?? 0);

if (!$ticketId) {
    $_SESSION['error'] = 'Invalid ticket ID.';
    header('Location: ' . url('index.php?p=dashboard&page=my_tickets'));
    exit;
}

// Get ticket details
$ticket = getTicketById($ticketId);

if (!$ticket) {
    $_SESSION['error'] = 'Ticket not found.';
    header('Location: ' . url('index.php?p=dashboard&page=my_tickets'));
    exit;
}

// Check access permission
if (!canAccessTicket($ticketId, $user['id'], $user['role'])) {
    $_SESSION['error'] = 'You do not have permission to view this ticket.';
    header('Location: ' . url('index.php?p=dashboard&page=my_tickets'));
    exit;
}



?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket #<?= $ticket['id'] ?> - <?= htmlspecialchars($ticket['subject']) ?></title>
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
    
    <div class="max-w-5xl mx-auto px-4 py-8">
        
        <!-- Header -->
        <div class="mb-6">
            <a href="<?= url('index.php?p=dashboard&page=my_tickets') ?>" class="inline-flex items-center text-gray-600 hover:text-gray-900 mb-4">
                <i class="fas fa-arrow-left mr-2"></i> Back to My Tickets
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
        
        <!-- Ticket Header -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
            <div class="flex flex-col md:flex-row justify-between items-start gap-4">
                <div class="flex-1">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="brand-bg text-white px-4 py-2 rounded-lg text-lg font-bold">
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
                            <i class="fas fa-user mr-2"></i>
                            <?= htmlspecialchars($ticket['user_name']) ?>
                        </span>
                        <span>
                            <i class="fas fa-calendar mr-2"></i>
                            Created: <?= date('M d, Y \a\t g:i A', strtotime($ticket['created_at'])) ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Live Chat Support -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
            <h2 class="text-xl font-bold text-gray-900 mb-4 flex items-center">
                <i class="fas fa-headset mr-3 brand-color"></i>
                Live Chat Support
            </h2>
            <div class="bg-blue-50 border-l-4 border-blue-600 rounded-lg p-6">
                <p class="text-gray-700 mb-3">
                    <i class="fas fa-info-circle mr-2 text-blue-600"></i>
                    Our support team is available to help you via live chat.
                </p>
                <p class="text-gray-600 text-sm">
                    Click the chat widget in the bottom-right corner to start a conversation with our support team.
                </p>
            </div>
        </div>
        
    </div>
    
    <!-- Tawk.to Live Chat Widget -->
    <!--Start of Tawk.to Script-->
    <script type="text/javascript">
        var Tawk_API=Tawk_API||{}, Tawk_LoadStart=new Date();
        (function(){
            var s1=document.createElement("script"),s0=document.getElementsByTagName("script")[0];
            s1.async=true;
            s1.src='https://embed.tawk.to/6913915e007b8c195b379e98/1j9q6vn2p';
            s1.charset='UTF-8';
            s1.setAttribute('crossorigin','*');
            s0.parentNode.insertBefore(s1,s0);
        })();
        
        // Set custom attributes for ticket context
        Tawk_API.onLoad = function(){
            Tawk_API.setAttributes({
                'ticket_id': '<?= $ticketId ?>',
                'ticket_subject': '<?= htmlspecialchars($ticket['subject']) ?>',
                'ticket_status': '<?= $ticket['status'] ?>',
                'name': '<?= htmlspecialchars($user['name']) ?>',
                'email': '<?= htmlspecialchars($user['email']) ?>'
            });
        };
    </script>
    <!--End of Tawk.to Script-->
    
</body>
</html>
