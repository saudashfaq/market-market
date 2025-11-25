<?php
require_once '../../config.php';
require_once '../bidding/BiddingSystem.php';

// Check authentication
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../auth/login.php');
    exit;
}

$biddingSystem = new BiddingSystem($pdo);
$userId = $_SESSION['user_id'];

// Get item ID from URL
$itemId = $_GET['item_id'] ?? null;
if (!$itemId) {
    header('Location: ../../dashboard.php');
    exit;
}

// Get item details
$stmt = $pdo->prepare("SELECT i.*, u.username as seller_name FROM items i JOIN users u ON i.seller_id = u.id WHERE i.id = ?");
$stmt->execute([$itemId]);
$item = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$item) {
    header('Location: ../../dashboard.php');
    exit;
}

// Handle bid placement with enhanced system
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_bid'])) {
    $bidAmount = floatval($_POST['bid_amount']);
    $downPaymentPercentage = floatval($_POST['down_payment_percentage']);
    
    // Use enhanced bidding system if available
    if (class_exists('EnhancedBiddingSystem')) {
        require_once '../bidding/EnhancedBiddingSystem.php';
        $enhancedBiddingSystem = new EnhancedBiddingSystem($pdo);
        $result = $enhancedBiddingSystem->placeBid($itemId, $userId, $bidAmount, $downPaymentPercentage);
    } else {
        // Fallback to original system with manual validation
        
        // Validate bid increment using system settings
        $currentBid = $biddingSystem->getCurrentHighestBid($itemId);
        if ($currentBid) {
            $stmt = $pdo->prepare("SELECT setting_value, setting_key FROM system_settings WHERE setting_key IN ('bid_increment_type', 'bid_increment_fixed', 'bid_increment_percentage')");
            $stmt->execute();
            $settings = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
            
            $incrementType = $settings['bid_increment_type'] ?? 'fixed';
            if ($incrementType === 'percentage') {
                $requiredIncrement = ($currentBid['bid_amount'] * floatval($settings['bid_increment_percentage'] ?? 5)) / 100;
            } else {
                $requiredIncrement = floatval($settings['bid_increment_fixed'] ?? 1);
            }
            
            $minimumBid = $currentBid['bid_amount'] + $requiredIncrement;
            
            if ($bidAmount < $minimumBid) {
                $result = [
                    'success' => false,
                    'message' => "Bid must be at least $" . number_format($minimumBid, 2) . " (minimum increment: $" . number_format($requiredIncrement, 2) . ")"
                ];
            } else {
                $result = $biddingSystem->placeBid($itemId, $userId, $bidAmount, $downPaymentPercentage);
            }
        } else {
            $result = $biddingSystem->placeBid($itemId, $userId, $bidAmount, $downPaymentPercentage);
        }
    }
    
    $message = $result['message'];
    $messageType = $result['success'] ? 'success' : 'error';
    
    // Show additional info if available
    if (isset($result['triggered_extension']) && $result['triggered_extension']) {
        $message .= ' (Auction extended due to late bid)';
    }
    if (isset($result['down_payment_warning']) && $result['down_payment_warning']) {
        $message .= ' (Low down payment flagged)';
    }
}

// Get current highest bid
$currentBid = $biddingSystem->getCurrentHighestBid($itemId);

// Get user's bids on this item
$stmt = $pdo->prepare("
    SELECT * FROM bids 
    WHERE item_id = ? AND bidder_id = ? 
    ORDER BY created_at DESC
");
$stmt->execute([$itemId, $userId]);
$userBids = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get bid history (limited for buyers)
$bidHistory = $biddingSystem->getBidHistory($itemId, 10);

// Get system settings for bidding
$systemSettings = [];
$stmt = $pdo->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('default_down_payment', 'bid_increment_type', 'bid_increment_fixed', 'bid_increment_percentage', 'min_down_payment', 'max_down_payment')");
$stmt->execute();
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $systemSettings[$row['setting_key']] = $row['setting_value'];
}

$defaultDownPayment = $systemSettings['default_down_payment'] ?? 50;
$incrementType = $systemSettings['bid_increment_type'] ?? 'fixed';
$incrementFixed = floatval($systemSettings['bid_increment_fixed'] ?? 1);
$incrementPercentage = floatval($systemSettings['bid_increment_percentage'] ?? 5);
$minDownPayment = floatval($systemSettings['min_down_payment'] ?? 1);
$maxDownPayment = floatval($systemSettings['max_down_payment'] ?? 100);

// Calculate minimum next bid using system settings
if ($currentBid) {
    if ($incrementType === 'percentage') {
        $increment = ($currentBid['bid_amount'] * $incrementPercentage) / 100;
    } else {
        $increment = $incrementFixed;
    }
    $minNextBid = $currentBid['bid_amount'] + $increment;
} else {
    $minNextBid = $item['reserved_amount'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bid on <?= htmlspecialchars($item['title']) ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; padding: 20px; }
        .header { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .section { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .section h2 { margin-bottom: 15px; color: #1f2937; }
        .current-bid { background: linear-gradient(135deg, #059669, #10b981); color: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; text-align: center; }
        .current-bid h3 { margin-bottom: 10px; }
        .bid-amount { font-size: 2.5em; font-weight: bold; margin: 10px 0; }
        .reserved-amount { background: #f3f4f6; padding: 15px; border-radius: 6px; margin-bottom: 20px; }
        .bid-form { background: #f8fafc; padding: 20px; border-radius: 8px; border: 2px solid #e5e7eb; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #374151; }
        .form-group input { width: 100%; padding: 12px; border: 2px solid #d1d5db; border-radius: 6px; font-size: 1.1em; }
        .form-group input:focus { outline: none; border-color: #2563eb; }
        .payment-breakdown { background: white; padding: 15px; border-radius: 6px; margin-top: 10px; border: 1px solid #e5e7eb; }
        .payment-row { display: flex; justify-content: space-between; margin-bottom: 8px; }
        .payment-row.total { font-weight: bold; border-top: 1px solid #e5e7eb; padding-top: 8px; }
        .btn { padding: 12px 24px; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 1.1em; }
        .btn-primary { background: #2563eb; color: white; }
        .btn-primary:hover { background: #1d4ed8; }
        .btn-primary:disabled { background: #9ca3af; cursor: not-allowed; }
        .btn-secondary { background: #6b7280; color: white; }
        .btn-secondary:hover { background: #4b5563; }
        .table { width: 100%; border-collapse: collapse; }
        .table th, .table td { padding: 12px; text-align: left; border-bottom: 1px solid #e5e7eb; }
        .table th { background: #f9fafb; font-weight: 600; }
        .badge { padding: 4px 8px; border-radius: 4px; font-size: 0.875em; font-weight: 500; }
        .badge-success { background: #d1fae5; color: #065f46; }
        .badge-warning { background: #fef3c7; color: #92400e; }
        .badge-danger { background: #fee2e2; color: #991b1b; }
        .message { padding: 12px; border-radius: 4px; margin-bottom: 20px; }
        .message.success { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .message.error { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
        .status-badge { padding: 6px 12px; border-radius: 20px; font-size: 0.875em; font-weight: 500; }
        .status-active { background: #d1fae5; color: #065f46; }
        .status-ended { background: #f3f4f6; color: #374151; }
        .status-sold { background: #dbeafe; color: #1e40af; }
        .warning-box { background: #fef3c7; border: 1px solid #f59e0b; padding: 15px; border-radius: 6px; margin-bottom: 20px; }
        .info-box { background: #dbeafe; border: 1px solid #3b82f6; padding: 15px; border-radius: 6px; margin-bottom: 20px; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        @media (max-width: 768px) { .grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><?= htmlspecialchars($item['title']) ?></h1>
            <p>Seller: <?= htmlspecialchars($item['seller_name']) ?></p>
            <span class="status-badge status-<?= $item['status'] === 'active_bidding' ? 'active' : ($item['status'] === 'sold' ? 'sold' : 'ended') ?>">
                <?= ucwords(str_replace('_', ' ', $item['status'])) ?>
            </span>
        </div>

        <?php if (isset($message)): ?>
            <div class="message <?= $messageType ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <?php if ($item['seller_id'] == $userId): ?>
            <div class="warning-box">
                <strong>Notice:</strong> This is your own item. You cannot place bids on items you're selling.
                <a href="seller_bidding_view.php?item_id=<?= $itemId ?>" style="color: #2563eb; text-decoration: none; font-weight: 500;">
                    Manage this auction →
                </a>
            </div>
        <?php endif; ?>

        <!-- Current Bid Status -->
        <?php if ($currentBid): ?>
            <div class="current-bid">
                <h3>Current Highest Bid</h3>
                <div class="bid-amount">$<?= number_format($currentBid['bid_amount'], 2) ?></div>
                <p>Down payment required: <?= $currentBid['down_payment_percentage'] ?>% ($<?= number_format($currentBid['down_payment_amount'], 2) ?>)</p>
                <?php if ($currentBid['bidder_id'] == $userId): ?>
                    <p style="background: rgba(255,255,255,0.2); padding: 8px; border-radius: 4px; margin-top: 10px;">
                        🎉 You are currently the highest bidder!
                    </p>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="current-bid">
                <h3>No Bids Yet</h3>
                <div class="bid-amount">$0.00</div>
                <p>Be the first to bid on this item!</p>
            </div>
        <?php endif; ?>

        <div class="reserved-amount">
            <strong>Reserved Amount:</strong> $<?= number_format($item['reserved_amount'], 2) ?>
            <br><small style="color: #6b7280;">This is the minimum amount the seller will accept</small>
        </div>

        <?php if ($item['status'] === 'active_bidding' && $item['seller_id'] != $userId): ?>
            <!-- Bidding Form -->
            <div class="section">
                <h2>Place Your Bid</h2>
                
                <form method="POST" class="bid-form" id="bidForm">
                    <div class="form-group">
                        <label for="bid_amount">Your Bid Amount ($)</label>
                        <input type="number" 
                               id="bid_amount" 
                               name="bid_amount" 
                               min="<?= $minNextBid ?>" 
                               step="0.01" 
                               placeholder="<?= number_format($minNextBid, 2) ?>"
                               required
                               oninput="calculatePayment()">
                        <small style="color: #6b7280;">
                            Minimum bid: $<?= number_format($minNextBid, 2) ?>
                            <?php if ($incrementType === 'percentage'): ?>
                                (<?= $incrementPercentage ?>% increment)
                            <?php else: ?>
                                (+$<?= number_format($incrementFixed, 2) ?> increment)
                            <?php endif; ?>
                        </small>
                    </div>

                    <div class="form-group">
                        <label for="down_payment_percentage">Down Payment Percentage (%)</label>
                        <input type="number" 
                               id="down_payment_percentage" 
                               name="down_payment_percentage" 
                               min="1" 
                               max="100" 
                               value="<?= $defaultDownPayment ?>"
                               step="0.01"
                               required
                               oninput="calculatePayment()">
                        <small style="color: #6b7280;">
                            Range: <?= $minDownPayment ?>% to <?= $maxDownPayment ?>% 
                            (Default: <?= $defaultDownPayment ?>%)
                        </small>
                    </div>

                    <div class="payment-breakdown" id="paymentBreakdown" style="display: none;">
                        <h4 style="margin-bottom: 10px;">Payment Breakdown</h4>
                        <div class="payment-row">
                            <span>Bid Amount:</span>
                            <span id="totalAmount">$0.00</span>
                        </div>
                        <div class="payment-row">
                            <span>Down Payment (<span id="percentageDisplay">50</span>%):</span>
                            <span id="downPaymentAmount">$0.00</span>
                        </div>
                        <div class="payment-row">
                            <span>Remaining Amount:</span>
                            <span id="remainingAmount">$0.00</span>
                        </div>
                        <div class="payment-row total">
                            <span>Pay Now if You Win:</span>
                            <span id="payNowAmount">$0.00</span>
                        </div>
                    </div>

                    <button type="submit" name="place_bid" class="btn btn-primary" style="width: 100%; margin-top: 20px;">
                        Place Bid
                    </button>
                </form>
            </div>
        <?php elseif ($item['status'] !== 'active_bidding'): ?>
            <div class="info-box">
                <strong>Auction Ended:</strong> 
                <?php if ($item['status'] === 'sold'): ?>
                    This item has been sold to the winning bidder.
                <?php else: ?>
                    This auction ended without meeting the reserved amount.
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="grid">
            <!-- Your Bids -->
            <div class="section">
                <h2>Your Bids on This Item</h2>
                <?php if (empty($userBids)): ?>
                    <p style="color: #6b7280; text-align: center; padding: 20px;">
                        You haven't placed any bids on this item yet.
                    </p>
                <?php else: ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Amount</th>
                                <th>Down Payment</th>
                                <th>Status</th>
                                <th>Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($userBids as $bid): ?>
                                <tr>
                                    <td>$<?= number_format($bid['bid_amount'], 2) ?></td>
                                    <td><?= $bid['down_payment_percentage'] ?>%</td>
                                    <td>
                                        <?php
                                        $badgeClass = 'badge-warning';
                                        switch ($bid['status']) {
                                            case 'active': 
                                            case 'winning': 
                                                $badgeClass = 'badge-success'; 
                                                break;
                                            case 'outbid': 
                                                $badgeClass = 'badge-warning'; 
                                                break;
                                        }
                                        ?>
                                        <span class="badge <?= $badgeClass ?>">
                                            <?= ucfirst($bid['status']) ?>
                                        </span>
                                    </td>
                                    <td><?= date('M j, g:i A', strtotime($bid['created_at'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <!-- Recent Bids -->
            <div class="section">
                <h2>Recent Bidding Activity</h2>
                <?php if (empty($bidHistory)): ?>
                    <p style="color: #6b7280; text-align: center; padding: 20px;">
                        No bids have been placed yet.
                    </p>
                <?php else: ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Amount</th>
                                <th>Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($bidHistory, 0, 5) as $bid): ?>
                                <tr>
                                    <td>
                                        $<?= number_format($bid['bid_amount'], 2) ?>
                                        <?php if ($bid['bidder_id'] == $userId): ?>
                                            <small style="color: #2563eb;">(You)</small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= date('M j, g:i A', strtotime($bid['created_at'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="section">
            <div style="display: flex; gap: 10px; justify-content: center;">
                <button class="btn btn-secondary" onclick="window.location.reload()">
                    Refresh Bids
                </button>
                <button class="btn btn-secondary" onclick="window.location.href='../../dashboard.php'">
                    Back to Dashboard
                </button>
            </div>
        </div>
    </div>

    <script>
        function calculatePayment() {
            const bidAmount = parseFloat(document.getElementById('bid_amount').value) || 0;
            const percentage = parseFloat(document.getElementById('down_payment_percentage').value) || 0;
            
            if (bidAmount > 0 && percentage > 0) {
                const downPayment = bidAmount * percentage / 100;
                const remaining = bidAmount - downPayment;
                
                document.getElementById('totalAmount').textContent = '$' + bidAmount.toFixed(2);
                document.getElementById('percentageDisplay').textContent = percentage.toFixed(1);
                document.getElementById('downPaymentAmount').textContent = '$' + downPayment.toFixed(2);
                document.getElementById('remainingAmount').textContent = '$' + remaining.toFixed(2);
                document.getElementById('payNowAmount').textContent = '$' + downPayment.toFixed(2);
                
                document.getElementById('paymentBreakdown').style.display = 'block';
            } else {
                document.getElementById('paymentBreakdown').style.display = 'none';
            }
        }

        // Auto-refresh every 15 seconds if auction is active
        <?php if ($item['status'] === 'active_bidding'): ?>
        setInterval(function() {
            if (document.hidden === false) {
                window.location.reload();
            }
        }, 15000);
        <?php endif; ?>

        // Initialize payment calculation
        document.addEventListener('DOMContentLoaded', function() {
            calculatePayment();
        });
    </script>
</body>
</html>