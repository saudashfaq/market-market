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

if (!$item || $item['seller_id'] != $userId) {
    header('Location: ../../dashboard.php');
    exit;
}

$message = '';
$messageType = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_reserved_amount'])) {
        $newReservedAmount = floatval($_POST['reserved_amount']);
        $result = $biddingSystem->updateReservedAmount($itemId, $userId, $newReservedAmount);
        $message = $result['message'];
        $messageType = $result['success'] ? 'success' : 'error';
        
        // Refresh item data
        if ($result['success']) {
            $stmt = $pdo->prepare("SELECT i.*, u.username as seller_name FROM items i JOIN users u ON i.seller_id = u.id WHERE i.id = ?");
            $stmt->execute([$itemId]);
            $item = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    }
    
    if (isset($_POST['end_auction'])) {
        $result = $biddingSystem->endAuction($itemId, $userId);
        $message = $result['message'];
        $messageType = $result['success'] ? 'success' : 'error';
        
        // Refresh item data
        if ($result['success']) {
            $stmt = $pdo->prepare("SELECT i.*, u.username as seller_name FROM items i JOIN users u ON i.seller_id = u.id WHERE i.id = ?");
            $stmt->execute([$itemId]);
            $item = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    }
    
    if (isset($_POST['start_auction'])) {
        try {
            $stmt = $pdo->prepare("UPDATE items SET status = 'active_bidding' WHERE id = ? AND seller_id = ?");
            $stmt->execute([$itemId, $userId]);
            
            log_action('auction_started', "Started auction for item: {$item['title']}", 'bidding', $userId);
            
            $message = 'Auction started successfully!';
            $messageType = 'success';
            
            // Refresh item data
            $stmt = $pdo->prepare("SELECT i.*, u.username as seller_name FROM items i JOIN users u ON i.seller_id = u.id WHERE i.id = ?");
            $stmt->execute([$itemId]);
            $item = $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            $message = 'Error starting auction: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
}

// Get current highest bid
$currentBid = $biddingSystem->getCurrentHighestBid($itemId);

// Get all bids for this item
$bidHistory = $biddingSystem->getBidHistory($itemId, 50);

// Get commission percentage for calculations
$commissionPercentage = $biddingSystem->getCommissionPercentage();

// Calculate potential earnings
$potentialEarnings = 0;
$commissionAmount = 0;
if ($currentBid) {
    $commissionCalc = $biddingSystem->calculateCommission($currentBid['bid_amount']);
    $potentialEarnings = $commissionCalc['seller_amount'];
    $commissionAmount = $commissionCalc['commission_amount'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Auction: <?= htmlspecialchars($item['title']) ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f5f5f5; }
        .container { max-width: 1000px; margin: 0 auto; padding: 20px; }
        .header { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .section { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .section h2 { margin-bottom: 15px; color: #1f2937; }
        .current-bid { background: linear-gradient(135deg, #059669, #10b981); color: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; text-align: center; }
        .current-bid h3 { margin-bottom: 10px; }
        .bid-amount { font-size: 2.5em; font-weight: bold; margin: 10px 0; }
        .earnings-card { background: linear-gradient(135deg, #7c3aed, #a855f7); color: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; text-align: center; }
        .reserved-amount-form { background: #f8fafc; padding: 20px; border-radius: 8px; border: 2px solid #e5e7eb; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #374151; }
        .form-group input { width: 100%; padding: 12px; border: 2px solid #d1d5db; border-radius: 6px; font-size: 1.1em; }
        .form-group input:focus { outline: none; border-color: #2563eb; }
        .btn { padding: 12px 24px; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 1.1em; margin-right: 10px; }
        .btn-primary { background: #2563eb; color: white; }
        .btn-primary:hover { background: #1d4ed8; }
        .btn-success { background: #059669; color: white; }
        .btn-success:hover { background: #047857; }
        .btn-danger { background: #dc2626; color: white; }
        .btn-danger:hover { background: #b91c1c; }
        .btn-secondary { background: #6b7280; color: white; }
        .btn-secondary:hover { background: #4b5563; }
        .btn:disabled { background: #9ca3af; cursor: not-allowed; }
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
        .status-draft { background: #f3f4f6; color: #374151; }
        .status-active { background: #d1fae5; color: #065f46; }
        .status-ended { background: #f3f4f6; color: #374151; }
        .status-sold { background: #dbeafe; color: #1e40af; }
        .warning-box { background: #fef3c7; border: 1px solid #f59e0b; padding: 15px; border-radius: 6px; margin-bottom: 20px; }
        .info-box { background: #dbeafe; border: 1px solid #3b82f6; padding: 15px; border-radius: 6px; margin-bottom: 20px; }
        .success-box { background: #d1fae5; border: 1px solid #10b981; padding: 15px; border-radius: 6px; margin-bottom: 20px; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .stats-row { display: flex; justify-content: space-between; margin-bottom: 10px; }
        .stats-row.total { font-weight: bold; border-top: 1px solid rgba(255,255,255,0.3); padding-top: 10px; }
        @media (max-width: 768px) { .grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🏷️ Manage Auction: <?= htmlspecialchars($item['title']) ?></h1>
            <p><?= htmlspecialchars($item['description']) ?></p>
            <span class="status-badge status-<?= str_replace('_', '-', $item['status']) ?>">
                <?= ucwords(str_replace('_', ' ', $item['status'])) ?>
            </span>
        </div>

        <?php if (isset($message)): ?>
            <div class="message <?= $messageType ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <!-- Current Status -->
        <?php if ($currentBid): ?>
            <div class="current-bid">
                <h3>Current Highest Bid</h3>
                <div class="bid-amount">$<?= number_format($currentBid['bid_amount'], 2) ?></div>
                <p>Down payment: <?= $currentBid['down_payment_percentage'] ?>% ($<?= number_format($currentBid['down_payment_amount'], 2) ?>)</p>
                <?php if ($currentBid['bid_amount'] >= $item['reserved_amount']): ?>
                    <p style="background: rgba(255,255,255,0.2); padding: 8px; border-radius: 4px; margin-top: 10px;">
                        ✅ Reserved amount met - Item will sell when auction ends
                    </p>
                <?php else: ?>
                    <p style="background: rgba(255,255,255,0.2); padding: 8px; border-radius: 4px; margin-top: 10px;">
                        ⚠️ Reserved amount not yet met - Item will not sell
                    </p>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="current-bid">
                <h3>No Bids Yet</h3>
                <div class="bid-amount">$0.00</div>
                <p>Waiting for the first bid...</p>
            </div>
        <?php endif; ?>

        <!-- Potential Earnings -->
        <?php if ($currentBid): ?>
            <div class="earnings-card">
                <h3>💰 Potential Earnings (if sold at current bid)</h3>
                <div class="stats-row">
                    <span>Winning Bid:</span>
                    <span>$<?= number_format($currentBid['bid_amount'], 2) ?></span>
                </div>
                <div class="stats-row">
                    <span>Platform Commission (<?= $commissionPercentage ?>%):</span>
                    <span>-$<?= number_format($commissionAmount, 2) ?></span>
                </div>
                <div class="stats-row total">
                    <span>Your Earnings:</span>
                    <span>$<?= number_format($potentialEarnings, 2) ?></span>
                </div>
            </div>
        <?php endif; ?>

        <div class="grid">
            <!-- Reserved Amount Management -->
            <div class="section">
                <h2>🎯 Reserved Amount Settings</h2>
                
                <div class="info-box">
                    <strong>Current Reserved Amount:</strong> $<?= number_format($item['reserved_amount'], 2) ?>
                    <br><small>Items will only sell if the highest bid meets or exceeds this amount</small>
                </div>

                <?php if ($item['status'] === 'draft' || $item['status'] === 'active_bidding'): ?>
                    <form method="POST" class="reserved-amount-form">
                        <div class="form-group">
                            <label for="reserved_amount">Update Reserved Amount ($)</label>
                            <input type="number" 
                                   id="reserved_amount" 
                                   name="reserved_amount" 
                                   value="<?= $item['reserved_amount'] ?>"
                                   min="0.01" 
                                   step="0.01" 
                                   required>
                            <small style="color: #6b7280;">
                                <?php if ($currentBid): ?>
                                    Cannot be set higher than current highest bid ($<?= number_format($currentBid['bid_amount'], 2) ?>)
                                <?php else: ?>
                                    Set the minimum amount you're willing to accept
                                <?php endif; ?>
                            </small>
                        </div>
                        <button type="submit" name="update_reserved_amount" class="btn btn-primary">
                            Update Reserved Amount
                        </button>
                    </form>
                <?php else: ?>
                    <div class="warning-box">
                        Reserved amount cannot be changed after the auction has ended.
                    </div>
                <?php endif; ?>
            </div>

            <!-- Auction Controls -->
            <div class="section">
                <h2>🎮 Auction Controls</h2>
                
                <?php if ($item['status'] === 'draft'): ?>
                    <div class="info-box">
                        <strong>Auction Status:</strong> Draft
                        <br>Your auction is ready to start. Click below to make it live.
                    </div>
                    <form method="POST" style="margin-top: 15px;">
                        <button type="submit" name="start_auction" class="btn btn-success">
                            🚀 Start Auction
                        </button>
                    </form>
                    
                <?php elseif ($item['status'] === 'active_bidding'): ?>
                    <div class="success-box">
                        <strong>Auction Status:</strong> Active
                        <br>Your auction is live and accepting bids.
                    </div>
                    
                    <?php if ($currentBid): ?>
                        <div class="warning-box">
                            <strong>⚠️ Important:</strong> 
                            <?php if ($currentBid['bid_amount'] >= $item['reserved_amount']): ?>
                                If you end the auction now, the item will be sold to the highest bidder for $<?= number_format($currentBid['bid_amount'], 2) ?>.
                            <?php else: ?>
                                The current highest bid ($<?= number_format($currentBid['bid_amount'], 2) ?>) is below your reserved amount. 
                                If you end the auction now, the item will NOT be sold.
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" style="margin-top: 15px;" onsubmit="return confirm('Are you sure you want to end this auction?')">
                        <button type="submit" name="end_auction" class="btn btn-danger">
                            🛑 End Auction
                        </button>
                    </form>
                    
                <?php elseif ($item['status'] === 'ended'): ?>
                    <div class="warning-box">
                        <strong>Auction Ended:</strong> Reserved amount was not met
                        <br>The item was not sold because the highest bid did not reach your reserved amount.
                    </div>
                    
                <?php elseif ($item['status'] === 'sold'): ?>
                    <div class="success-box">
                        <strong>🎉 Item Sold!</strong>
                        <br>Your item has been sold to the winning bidder.
                        <br><strong>Final Sale Price:</strong> $<?= number_format($currentBid['bid_amount'], 2) ?>
                        <br><strong>Your Earnings:</strong> $<?= number_format($potentialEarnings, 2) ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Bidding Activity -->
        <div class="section">
            <h2>📊 Bidding Activity</h2>
            <?php if (empty($bidHistory)): ?>
                <p style="color: #6b7280; text-align: center; padding: 20px;">
                    No bids have been placed yet.
                </p>
            <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Bidder</th>
                            <th>Bid Amount</th>
                            <th>Down Payment</th>
                            <th>Status</th>
                            <th>Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bidHistory as $bid): ?>
                            <tr>
                                <td><?= htmlspecialchars($bid['username']) ?></td>
                                <td>$<?= number_format($bid['bid_amount'], 2) ?></td>
                                <td><?= $bid['down_payment_percentage'] ?>% ($<?= number_format($bid['down_payment_amount'], 2) ?>)</td>
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
                                <td><?= date('M j, Y g:i A', strtotime($bid['created_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Action Buttons -->
        <div class="section">
            <div style="display: flex; gap: 10px; justify-content: center;">
                <button class="btn btn-secondary" onclick="window.location.reload()">
                    🔄 Refresh Data
                </button>
                <button class="btn btn-secondary" onclick="window.location.href='../../dashboard.php'">
                    ← Back to Dashboard
                </button>
            </div>
        </div>
    </div>

    <script>
        // Auto-refresh every 30 seconds if auction is active
        <?php if ($item['status'] === 'active_bidding'): ?>
        setInterval(function() {
            if (document.hidden === false) {
                window.location.reload();
            }
        }, 30000);
        <?php endif; ?>
    </script>
</body>
</html>