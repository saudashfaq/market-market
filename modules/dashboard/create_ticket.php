<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../middlewares/auth.php';
require_once __DIR__ . '/../../includes/validation_helper.php';
require_once __DIR__ . '/../../includes/ticket_helper.php';

require_login();

$user = current_user();
$pdo = db();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['error'] = 'Invalid security token. Please try again.';
        header('Location: ' . url('index.php?p=dashboard&page=create_ticket'));
        exit;
    }
    
    // Sanitize input
    $input = sanitizeTicketInput($_POST);
    
    // Validate input
    $errors = validateTicketData($input);
    
    if (empty($errors)) {
        // Create ticket
        $ticketId = createTicket($user['id'], $input['subject'], $input['message']);
        
        if ($ticketId) {
            // Get SuperAdmin email
            $adminStmt = $pdo->query("SELECT email, name FROM users WHERE role = 'superadmin' LIMIT 1");
            $admin = $adminStmt->fetch(PDO::FETCH_ASSOC);
            
            // Send email notification to admin (if email helper is available)
            if ($admin && file_exists(__DIR__ . '/../../includes/email_helper.php')) {
                try {
                    require_once __DIR__ . '/../../includes/email_helper.php';
                    $ticketData = [
                        'id' => $ticketId,
                        'subject' => $input['subject'],
                        'message' => $input['message']
                    ];
                    $userData = [
                        'name' => $user['name'],
                        'email' => $user['email']
                    ];
                    sendTicketCreatedEmail($admin['email'], $ticketData, $userData);
                } catch (Exception $e) {
                    error_log("Email notification failed: " . $e->getMessage());
                }
            }
            
            // Create in-app notification for all admins/superadmins
            if (file_exists(__DIR__ . '/../../includes/notification_helper.php')) {
                try {
                    require_once __DIR__ . '/../../includes/notification_helper.php';
                    notifyAdminsNewTicket($ticketId, $input['subject'], $user['name']);
                } catch (Exception $e) {
                    error_log("In-app notification failed: " . $e->getMessage());
                }
            }
            
            $_SESSION['success'] = 'Ticket created successfully! We will respond to you soon.';
            header('Location: ' . url('index.php?p=dashboard&page=my_tickets'));
            exit;
        } else {
            $_SESSION['error'] = 'Failed to create ticket. Please try again.';
        }
    } else {
        $_SESSION['error'] = implode('<br>', $errors);
    }
}

// Generate CSRF token
$csrf_token = getCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Support Ticket</title>
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
    
    <div class="max-w-4xl mx-auto px-4 py-8">
        
        <!-- Header -->
        <div class="mb-8">
            <a href="<?= url('index.php?p=dashboard&page=my_tickets') ?>" class="inline-flex items-center text-gray-600 hover:text-gray-900 mb-4">
                <i class="fas fa-arrow-left mr-2"></i> Back to My Tickets
            </a>
            <h1 class="text-3xl font-bold brand-color flex items-center">
                <i class="fas fa-headset mr-3"></i>
                Support Center
            </h1>
            <p class="text-gray-600 mt-2">Need help? Create a support ticket or chat with us live.</p>
        </div>
        
        <!-- Live Chat Info -->
        <div class="bg-gradient-to-r from-purple-50 to-blue-50 border-l-4 border-purple-600 rounded-xl p-6 mb-6">
            <div class="flex items-start">
                <i class="fas fa-comments text-purple-600 text-2xl mr-4 mt-1"></i>
                <div>
                    <h3 class="text-lg font-bold text-gray-900 mb-2">Need Immediate Help?</h3>
                    <p class="text-gray-700 mb-3">
                        Our live chat support is available now! Click the chat widget in the bottom-right corner to start an instant conversation with our support team.
                    </p>
                    <div class="flex items-center text-sm text-gray-600">
                        <i class="fas fa-clock mr-2"></i>
                        <span>Average response time: Under 2 minutes</span>
                    </div>
                </div>
            </div>
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
        
        <!-- Create Ticket Form -->
        <div class="bg-white rounded-xl shadow-lg p-8">
            <form method="POST" action="" id="createTicketForm">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                
                <!-- Subject Field -->
                <div class="mb-6">
                    <label for="subject" class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-tag mr-2 brand-color"></i>Subject *
                    </label>
                    <input 
                        type="text" 
                        id="subject" 
                        name="subject" 
                        required
                        minlength="5"
                        maxlength="255"
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent transition"
                        placeholder="Brief description of your issue"
                        value="<?= htmlspecialchars($_POST['subject'] ?? '') ?>"
                    >
                    <p class="text-xs text-gray-500 mt-1">
                        <span id="subjectCount">0</span>/255 characters (minimum 5)
                    </p>
                </div>
                
                <!-- Message Field -->
                <div class="mb-6">
                    <label for="message" class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-message mr-2 brand-color"></i>Message *
                    </label>
                    <textarea 
                        id="message" 
                        name="message" 
                        required
                        minlength="10"
                        maxlength="5000"
                        rows="8"
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent transition resize-none"
                        placeholder="Please describe your issue in detail..."
                    ><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>
                    <p class="text-xs text-gray-500 mt-1">
                        <span id="messageCount">0</span>/5000 characters (minimum 10)
                    </p>
                </div>
                
                <!-- Info Box -->
                <div class="bg-blue-50 border-l-4 border-blue-500 p-4 mb-6 rounded-lg">
                    <div class="flex items-start">
                        <i class="fas fa-info-circle text-blue-500 mr-3 mt-1"></i>
                        <div class="text-sm text-blue-700">
                            <p class="font-semibold mb-1">What happens next?</p>
                            <ul class="list-disc list-inside space-y-1">
                                <li>Your ticket will be sent to our support team</li>
                                <li>You'll receive email notifications for all replies</li>
                                <li>You can track your ticket status in "My Tickets"</li>
                                <li>Live chat will be available for open tickets</li>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <!-- Submit Button -->
                <div class="flex items-center justify-between">
                    <a href="<?= url('index.php?p=dashboard&page=my_tickets') ?>" class="text-gray-600 hover:text-gray-900 font-medium">
                        <i class="fas fa-times mr-2"></i>Cancel
                    </a>
                    <button 
                        type="submit" 
                        class="brand-bg text-white px-8 py-3 rounded-lg font-semibold hover:opacity-90 transition flex items-center"
                    >
                        <i class="fas fa-paper-plane mr-2"></i>
                        Create Ticket
                    </button>
                </div>
            </form>
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
        
        // Set custom attributes for user context
        Tawk_API.onLoad = function(){
            Tawk_API.setAttributes({
                'name': '<?= htmlspecialchars($user['name']) ?>',
                'email': '<?= htmlspecialchars($user['email']) ?>',
                'page': 'create_ticket'
            });
        };
    </script>
    <!--End of Tawk.to Script-->
    
    <script>
        // Character counters
        const subjectInput = document.getElementById('subject');
        const messageInput = document.getElementById('message');
        const subjectCount = document.getElementById('subjectCount');
        const messageCount = document.getElementById('messageCount');
        
        subjectInput.addEventListener('input', function() {
            subjectCount.textContent = this.value.length;
        });
        
        messageInput.addEventListener('input', function() {
            messageCount.textContent = this.value.length;
        });
        
        // Initialize counts
        subjectCount.textContent = subjectInput.value.length;
        messageCount.textContent = messageInput.value.length;
        
        // Form validation
        document.getElementById('createTicketForm').addEventListener('submit', function(e) {
            const subject = subjectInput.value.trim();
            const message = messageInput.value.trim();
            
            if (subject.length < 5 || subject.length > 255) {
                e.preventDefault();
                alert('Subject must be between 5 and 255 characters.');
                return false;
            }
            
            if (message.length < 10 || message.length > 5000) {
                e.preventDefault();
                alert('Message must be between 10 and 5000 characters.');
                return false;
            }
        });
    </script>
    
</body>
</html>
