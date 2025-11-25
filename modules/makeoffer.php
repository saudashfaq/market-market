<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../middlewares/auth.php';
require_once __DIR__ . '/../includes/log_helper.php';
require_once __DIR__ . '/../includes/flash_helper.php';
require_once __DIR__ . '/../includes/validation_helper.php';

require_login();
$pdo = db();

// Get listing ID
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    echo "<div class='min-h-screen flex items-center justify-center bg-gradient-to-br from-gray-50 to-gray-100'>";
    echo "<div class='text-center p-8'>";
    echo "<div class='w-20 h-20 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-6 shadow-lg'>";
    echo "<i class='fas fa-exclamation-triangle text-2xl text-red-500'></i></div>";
    echo "<h2 class='text-2xl font-bold text-gray-800 mb-2'>Invalid Listing</h2>";
    echo "<p class='text-gray-600 mb-4'>The listing ID provided is not valid.</p>";
    echo "<a href='index.php?p=listing' class='bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg transition-colors'>Browse Listings</a>";
    echo "</div></div>";
    exit;
}

// Fetch listing with seller profile picture - INCLUDE BIDDING FIELDS
$stmt = $pdo->prepare("SELECT l.*, u.name AS seller_name, u.profile_pic AS seller_profile_pic FROM listings l
    LEFT JOIN users u ON u.id = l.user_id WHERE l.id = ?");
$stmt->execute([$id]);
$listing = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$listing) {
    echo "<div class='min-h-screen flex items-center justify-center bg-gradient-to-br from-gray-50 to-gray-100'>";
    echo "<div class='text-center p-8'>";
    echo "<div class='w-20 h-20 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-6 shadow-lg'>";
    echo "<i class='fas fa-search text-2xl text-blue-500'></i></div>";
    echo "<h2 class='text-2xl font-bold text-gray-800 mb-2'>Listing Not Found</h2>";
    echo "<p class='text-gray-600 mb-4'>The listing you're looking for doesn't exist or has been removed.</p>";
    echo "<a href='index.php?p=listing' class='bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg transition-colors'>Browse Listings</a>";
    echo "</div></div>";
    exit;
}

// Get bidding settings for display
$reservedAmount = floatval($listing['reserved_amount'] ?? 0);

// Get superadmin minimum offer percentage for UI display
$stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'min_offer_percentage'");
$stmt->execute();
$minOfferPercentage = floatval($stmt->fetchColumn() ?: 70); // Default 70% if not set
$askingPrice = floatval($listing['asking_price']);
$minOfferAmount = ($askingPrice * $minOfferPercentage) / 100;

// Get validation errors
$validationErrors = FormValidator::getStoredErrors();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF validation
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        setErrorMessage('Invalid request. Please try again.');
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
    }

    $user_id = current_user()['id'];
    $seller_id = $listing['user_id'];

    // Create validator with form data
    $validator = new FormValidator($_POST);
    
    // Validate offer fields
    $validator
        ->required('amount', 'Offer amount is required')
        ->custom('amount', function($value) {
            return is_numeric($value) && floatval($value) > 0;
        }, 'Please enter a valid offer amount greater than 0')
        ->custom('amount', function($value) use ($listing) {
            $amount = floatval($value);
            $askingPrice = floatval($listing['asking_price']);
            return $amount <= ($askingPrice * 10); // Max 10x asking price
        }, 'Offer amount seems unreasonably high');
    
    // ✅ SUPERADMIN MINIMUM OFFER VALIDATION
    // Get superadmin's minimum offer percentage setting
    $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'min_offer_percentage'");
    $stmt->execute();
    $minOfferPercentage = floatval($stmt->fetchColumn() ?: 70); // Default 70% if not set
    
    $askingPrice = floatval($listing['asking_price']);
    $minOfferAmount = ($askingPrice * $minOfferPercentage) / 100;
    
    $validator->custom('amount', function($value) use ($minOfferAmount, $minOfferPercentage) {
        return floatval($value) >= $minOfferAmount;
    }, 'Your offer must be at least ' . number_format($minOfferPercentage, 0) . '% of the asking price ($' . number_format($minOfferAmount, 2) . ') as set by system administrator');
    
    // ✅ SELLER'S RESERVED AMOUNT CHECK (if set)
    if ($reservedAmount > 0) {
        $validator->custom('amount', function($value) use ($reservedAmount) {
            return floatval($value) >= $reservedAmount;
        }, 'Your offer must be at least $' . number_format($reservedAmount, 2) . ' (seller\'s minimum price)');
    }

    // Check if user is trying to make offer on own listing
    if ($user_id == $seller_id) {
        $validator->custom('amount', function() { return false; }, 'You cannot make an offer on your own listing');
    }

    // If validation fails, store errors and reload
    if ($validator->fails()) {
        $validator->storeErrors();
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
    }

    $amount = floatval($_POST['amount']);
    $message = !empty(trim($_POST['message'])) ? trim($_POST['message']) : 'No message provided';
    $is_private = isset($_POST['is_private']) ? 1 : 0;
        try {
            $insert = $pdo->prepare("
                INSERT INTO offers (listing_id, user_id, seller_id, amount, message, is_private, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())
            ");
            $insert->execute([$id, $user_id, $seller_id, $amount, $message, $is_private]);

            $offer_id = $pdo->lastInsertId();

            log_action(
                "Offer Created",
                "User (ID: $user_id) made an offer (#$offer_id) of $amount for Listing ID: $id",
                "offers",
                $user_id
            );

            // Send email to seller in background
            register_shutdown_function(function() use ($pdo, $seller_id, $user_id, $amount, $message, $listing) {
                if (function_exists('fastcgi_finish_request')) {
                    fastcgi_finish_request();
                }
                
                try {
                    require_once __DIR__ . '/../includes/email_helper.php';
                    
                    // Get seller details
                    $stmt = $pdo->prepare("SELECT email, name FROM users WHERE id = ?");
                    $stmt->execute([$seller_id]);
                    $seller = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    // Get buyer details
                    $stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
                    $stmt->execute([$user_id]);
                    $buyer = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($seller && $buyer) {
                        $offerData = [
                            'buyer_name' => $buyer['name'],
                            'amount' => $amount,
                            'message' => $message
                        ];
                        
                        $listingData = [
                            'title' => $listing['name'],
                            'price' => $listing['asking_price']
                        ];
                        
                        $emailSent = sendOfferReceivedEmail($seller['email'], $seller['name'], $offerData, $listingData);
                        
                        if ($emailSent) {
                            error_log("✅ Offer received email sent to seller: {$seller['email']}");
                        } else {
                            error_log("❌ Failed to send offer received email to seller: {$seller['email']}");
                        }
                    }
                } catch (Exception $e) {
                    error_log("❌ Error sending offer email: " . $e->getMessage());
                }
            });

            setSuccessMessage("Offer submitted successfully! The seller will be notified.");
            header("Location: " . $_SERVER['REQUEST_URI']);
            exit;
        } catch (Exception $e) {
            setErrorMessage("Error submitting offer: " . $e->getMessage());
            header("Location: " . $_SERVER['REQUEST_URI']);
            exit;
        }
}

// Get validation errors after POST processing
$validationErrors = FormValidator::getStoredErrors();
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Make Offer - <?= htmlspecialchars($listing['name']) ?> - Marketplace</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#eff6ff',
                            100: '#dbeafe',
                            500: '#3b82f6',
                            600: '#2563eb',
                            700: '#1d4ed8',
                        }
                    },
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    },
                }
            }
        }
    </script>
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
        .gradient-bg {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        }
        .metric-card {
            transition: all 0.3s ease;
        }
        .metric-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        .sticky-purchase-card {
            position: sticky;
            top: 2rem;
        }
        .tab-active {
            border-bottom: 2px solid #3b82f6;
            color: #3b82f6;
            font-weight: 600;
        }
        .fade-in {
            animation: fadeIn 0.5s ease-in-out;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .field-error {
            color: #dc2626; /* red-600 */
            font-size: 0.875rem;
            margin-top: 0.375rem;
        }
    </style>
</head>
<body class="bg-gray-50 text-gray-800">

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Left Column - Main Content -->
            <div class="lg:col-span-2 space-y-8">
                <!-- Title & Status -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
                    <div class="flex flex-wrap items-center gap-2 mb-4">
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                            <i class="fas fa-handshake mr-1"></i> Make Offer
                        </span>
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                            <?= ucfirst($listing['type']) ?>
                        </span>
                    </div>
                    
                    <h1 class="text-3xl font-bold text-gray-900 mb-4">Submit Your Offer for <?= htmlspecialchars($listing['name']) ?></h1>
                </div>

                <!-- Offer Form -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
                    <div class="border-b border-gray-200">
                        <nav class="flex -mb-px">
                            <button class="tab-active py-4 px-6 text-center border-transparent font-medium text-sm">
                                <i class="fas fa-handshake mr-2"></i>Make Offer
                            </button>
                        </nav>
                    </div>

                    <!-- Tab Content -->
                    <div class="p-6">
                        <div class="fade-in">
                            <h2 class="text-xl font-bold text-gray-900 mb-4">Submit Your Offer</h2>
                            
                            <form method="POST" class="space-y-6" id="offerForm">
                                <?php csrfTokenField(); ?>

                                <!-- Hidden total price for JS -->
                                <input type="hidden" id="totalPrice" value="<?= htmlspecialchars($listing['asking_price']) ?>">

                                <!-- Offer Amount -->
                                <div class="mb-6">
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Your Offer Amount ($) <span class="text-red-500">*</span>
                                    </label>
                                    <?php 
                                    // Get superadmin's minimum offer percentage
                                    $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'min_offer_percentage'");
                                    $stmt->execute();
                                    $minOfferPercentage = floatval($stmt->fetchColumn() ?: 70);
                                    $minOfferAmount = ($listing['asking_price'] * $minOfferPercentage) / 100;
                                    ?>
                                    <div class="text-sm text-gray-500 mb-2">
                                        Asking Price: <span class="font-semibold text-gray-900">$<?= number_format($listing['asking_price'], 2) ?></span>
                                        <br>
                                    </div>
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                            <span class="text-gray-500 text-lg font-semibold">$</span>
                                        </div>
                                        <input type="number" 
                                               name="amount" 
                                               id="offerAmount"
                                               required 
                                               step="0.01"
                                               min="<?= max($minOfferAmount, $reservedAmount) ?>"
                                               placeholder="Enter your offer amount" 
                                               value="<?= oldValue('amount') ?>"
                                               class="<?= inputErrorClass('amount', $validationErrors, 'w-full pl-8 pr-4 py-3 text-lg font-semibold border-2 border-gray-200 rounded-xl focus:border-blue-500 focus:ring-4 focus:ring-blue-100 transition-all duration-200 bg-gray-50 focus:bg-white', 'border-red-500 focus:border-red-500 focus:ring-red-100') ?>" />
                                    </div>

                                    <!-- Only show System Requirement - No validation errors -->
                                    <div class="mt-2 bg-blue-50 border border-blue-200 rounded-lg p-3">
                                        <p class="text-xs text-blue-800">
                                            <i class="fas fa-info-circle"></i> 
                                            <strong>System Requirement:</strong> Offers must be at least <?= number_format($minOfferPercentage, 0) ?>% of asking price ($<?= number_format($minOfferAmount, 2) ?>)
                                        </p>
                                    </div>
                                </div>

                                <!-- Message (Optional) -->
                                <div class="mb-6">
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Message (Optional)
                                    </label>
                                    <textarea name="message" 
                                              rows="4" 
                                              placeholder="Add a message to your offer..."
                                              class="w-full p-4 border-2 border-gray-200 rounded-xl focus:border-blue-500 focus:ring-4 focus:ring-blue-100 transition-all duration-200 bg-gray-50 focus:bg-white resize-none"><?= oldValue('message') ?></textarea>
                                </div>

                                <!-- Action Buttons -->
                                <div class="flex gap-4">
                                    <button type="submit"
                                            class="flex-1 bg-primary-600 hover:bg-primary-700 text-white font-semibold py-3 px-4 rounded-xl transition-colors flex items-center justify-center">
                                        <i class="fas fa-paper-plane mr-2"></i>
                                        Submit Offer
                                    </button>
                                    
                                    <a href="index.php?p=listingDetail&id=<?= $listing['id'] ?>" 
                                       class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold py-3 px-4 rounded-xl transition-colors flex items-center justify-center">
                                        <i class="fas fa-arrow-left mr-2"></i>
                                        Cancel
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column - Sidebar -->
            <div class="lg:col-span-1">
                <div class="sticky-purchase-card">
                    <!-- Purchase Card -->
                    <div class="bg-white rounded-2xl shadow-lg border border-gray-200 overflow-hidden mb-6">
                        <div class="p-6 border-b border-gray-200">
                            <div class="text-center mb-4">
                                <div class="text-sm font-medium text-gray-500 mb-1">Asking Price</div>
                                <div class="text-3xl font-bold text-gray-900">
                                    $<?= number_format($listing['asking_price']) ?>
                                </div>
                                <?php if ($listing['monthly_revenue'] > 0): ?>
                                    <div class="text-sm text-gray-500 mt-1"><?= round($listing['asking_price'] / ($listing['monthly_revenue'] * 12), 1) ?>x annual revenue</div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="flex items-center mb-4">
                                <?php 
                                // Generate seller profile picture or initials
                                $seller_name = !empty($listing['seller_name']) ? $listing['seller_name'] : 'Anonymous Seller';
                                $seller_profile_pic = $listing['seller_profile_pic'] ?? '';
                                
                                if (!empty($seller_profile_pic) && $seller_profile_pic !== 'null') {
                                    $image_url = strpos($seller_profile_pic, 'http') === 0 ? $seller_profile_pic : url($seller_profile_pic);
                                    echo '<div class="w-12 h-12 rounded-full overflow-hidden mr-4 shadow-lg ring-2 ring-white bg-gradient-to-br from-blue-500 to-purple-600 relative">';
                                    echo '<img src="' . htmlspecialchars($image_url) . '" alt="' . htmlspecialchars($seller_name) . '" class="w-full h-full object-cover absolute inset-0" onerror="this.style.display=\'none\'; this.nextElementSibling.style.display=\'flex\';" onload="this.nextElementSibling.style.display=\'none\';">';
                                    echo '<div class="w-full h-full flex items-center justify-center absolute inset-0 text-white font-bold text-lg">';
                                } else {
                                    echo '<div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-purple-600 rounded-full flex items-center justify-center text-white font-bold text-lg mr-4 shadow-lg ring-2 ring-white">';
                                }
                                
                                // Generate initials
                                $initials = '';
                                if (!empty($listing['seller_name'])) {
                                    $names = explode(' ', $listing['seller_name']);
                                    $initials = strtoupper(substr($names[0], 0, 1));
                                    if (count($names) > 1) {
                                        $initials .= strtoupper(substr($names[1], 0, 1));
                                    }
                                } else {
                                    $initials = 'U';
                                }
                                echo $initials;
                                
                                if (!empty($seller_profile_pic) && $seller_profile_pic !== 'null') {
                                    echo '</div></div>';
                                } else {
                                    echo '</div>';
                                }
                                ?>
                                <div>
                                    <div class="font-semibold text-gray-900">
                                        <?= htmlspecialchars($seller_name) ?>
                                    </div>
                                    <div class="text-sm text-gray-500">Seller</div>
                                </div>
                            </div>
                            
                            <div class="space-y-3 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-gray-500">Monthly Revenue</span>
                                    <span class="font-medium">$<?= number_format($listing['monthly_revenue']) ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-500">Business Type</span>
                                    <span class="font-medium"><?= ucfirst($listing['type']) ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-500">Multiple</span>
                                    <span class="font-medium"><?= $listing['monthly_revenue'] > 0 ? round($listing['asking_price'] / ($listing['monthly_revenue'] * 12), 1) . 'x' : 'N/A' ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        document.addEventListener("DOMContentLoaded", function () {
            const amountInput = document.getElementById('offerAmount');
            const amountError = document.getElementById('amountError');
            const form = document.getElementById('offerForm');
            const minOfferAmount = <?= $minOfferAmount ?>;
            const minOfferPercentage = <?= $minOfferPercentage ?>;
            const askingPrice = <?= $listing['asking_price'] ?>;
            const reservedAmount = <?= $reservedAmount ?: 0 ?>;

            if (amountInput) {
                amountInput.addEventListener('input', function(e) {
                    let value = e.target.value;
                    // Remove any non-numeric characters except decimal point
                    value = value.replace(/[^0-9.]/g, '');
                    // Ensure only one decimal point
                    const parts = value.split('.');
                    if (parts.length > 2) {
                        value = parts[0] + '.' + parts.slice(1).join('');
                    }
                    e.target.value = value;

                    const amount = parseFloat(value) || 0;
                    
                    // Clear previous errors
                    amountError.textContent = '';
                    
                    // Real-time validation feedback
                    if (amount > 0) {
                        if (amount < minOfferAmount) {
                            amountError.textContent = `Offer must be at least ${minOfferPercentage}% of asking price ($${minOfferAmount.toLocaleString()}).`;
                            e.target.style.borderColor = '#ef4444';
                            e.target.style.backgroundColor = '#fef2f2';
                        } else if (reservedAmount > 0 && amount < reservedAmount) {
                            amountError.textContent = `Offer must be at least $${reservedAmount.toLocaleString()} (seller's minimum).`;
                            e.target.style.borderColor = '#f59e0b';
                            e.target.style.backgroundColor = '#fffbeb';
                        } else {
                            e.target.style.borderColor = '#10b981';
                            e.target.style.backgroundColor = '#f0fdf4';
                        }
                    } else {
                        e.target.style.borderColor = '#d1d5db';
                        e.target.style.backgroundColor = '#f9fafb';
                    }
                });
            }

            if (form) {
                form.addEventListener('submit', function (e) {
                    const amount = parseFloat(amountInput.value);

                    if (isNaN(amount) || amount <= 0) {
                        e.preventDefault();
                        amountError.textContent = "Please enter a valid offer amount.";
                        amountInput.focus();
                        return;
                    }

                    // Check superadmin minimum percentage
                    if (amount < minOfferAmount) {
                        e.preventDefault();
                        amountError.textContent = `Your offer must be at least ${minOfferPercentage}% of the asking price ($${minOfferAmount.toLocaleString()}).`;
                        amountInput.focus();
                        return;
                    }

                    // Check seller's reserved amount
                    if (reservedAmount > 0 && amount < reservedAmount) {
                        e.preventDefault();
                        amountError.textContent = `Your offer must be at least $${reservedAmount.toLocaleString()} (seller's minimum price).`;
                        amountInput.focus();
                        return;
                    }

                    // Optional: warn if offer is much higher than asking (but allow)
                    if (amount > askingPrice * 2) {
                        if (!confirm('Your offer is significantly higher than the asking price. Are you sure you want to proceed?')) {
                            e.preventDefault();
                            return;
                        }
                    }

                    // disable submit button to prevent double submits
                    const submitBtn = form.querySelector('button[type="submit"]');
                    if (submitBtn) {
                        submitBtn.disabled = true;
                        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Submitting Offer...';
                    }
                });
            }
        });

        // Auto-hide success/error messages after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.bg-green-50, .bg-red-50');
            alerts.forEach(function(alert) {
                alert.style.transition = 'opacity 0.5s ease-out';
                alert.style.opacity = '0';
                setTimeout(function() {
                    alert.remove();
                }, 500);
            });
        }, 5000);
    </script>
</body>
</html>
