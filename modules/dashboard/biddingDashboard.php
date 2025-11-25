<?php
require_once __DIR__ . "/../../middlewares/auth.php";
require_once __DIR__ . "/../bidding/BiddingSystem.php";

// Check admin authentication
$user = current_user();
$userRole = $user['role'] ?? '';

if (!in_array($userRole, ['admin', 'superadmin', 'super_admin', 'superAdmin'])) {
    header('Location: ' . url('index.php?p=login'));
    exit;
}

// Get database connection
$pdo = db();
$biddingSystem = new BiddingSystem($pdo);
$userId = $user['id'];

// Handle form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_commission'])) {
        $newCommission = floatval($_POST['commission_percentage']);
        $result = $biddingSystem->updateCommissionPercentage($userId, $newCommission);
        $message = $result['message'];
        $messageType = $result['success'] ? 'success' : 'error';
    }
    
    if (isset($_POST['update_settings'])) {
        try {
            $pdo->beginTransaction();
            
            $settingsToUpdate = [
                'bid_increment_type' => $_POST['bid_increment_type'] ?? 'fixed',
                'bid_increment_fixed' => floatval($_POST['bid_increment_fixed'] ?? 10.00),
                'bid_increment_percentage' => floatval($_POST['bid_increment_percentage'] ?? 5.00),
                'default_down_payment' => floatval($_POST['default_down_payment']),
                'min_down_payment' => floatval($_POST['min_down_payment']),
                'max_down_payment' => floatval($_POST['max_down_payment']),
                'auction_extension_minutes' => intval($_POST['auction_extension_minutes'] ?? 2),
                'down_payment_warning_threshold' => floatval($_POST['down_payment_warning_threshold'] ?? 10.00),
                'default_reserved_amount_percentage' => floatval($_POST['default_reserved_amount_percentage'] ?? 0.00)
            ];
            
            $stmt = $pdo->prepare("
                INSERT INTO system_settings (setting_key, setting_value, updated_by) 
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by)
            ");
            
            foreach ($settingsToUpdate as $key => $value) {
                $stmt->execute([$key, $value, $userId]);
            }
            
            log_action('system_settings_updated', 'SuperAdmin updated bidding system settings', 'admin', $userId);
            
            $pdo->commit();
            $message = 'Settings updated successfully!';
            $messageType = 'success';
            
            $stmt = $pdo->prepare("SELECT * FROM system_settings ORDER BY setting_key");
            $stmt->execute();
            $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = 'Error: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
}

// Get current system settings
$settings = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM system_settings ORDER BY setting_key");
    $stmt->execute();
    $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $settings = [
        ['setting_key' => 'commission_percentage', 'setting_value' => '10.00', 'description' => 'Platform commission percentage'],
        ['setting_key' => 'default_down_payment', 'setting_value' => '50.00', 'description' => 'Default down payment percentage'],
        ['setting_key' => 'min_down_payment', 'setting_value' => '1.00', 'description' => 'Minimum down payment percentage'],
        ['setting_key' => 'max_down_payment', 'setting_value' => '100.00', 'description' => 'Maximum down payment percentage']
    ];
}

// Get bidding statistics
$stats = [
    'total_listings' => 0,
    'with_reserve' => 0,
    'avg_down_payment' => 50.00,
    'total_offers' => 0
];

try {
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total_listings,
            SUM(CASE WHEN reserved_amount > 0 THEN 1 ELSE 0 END) as with_reserve,
            AVG(COALESCE(min_down_payment_percentage, 50)) as avg_down_payment
        FROM listings
    ");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result) {
        $stats['total_listings'] = $result['total_listings'];
        $stats['with_reserve'] = $result['with_reserve'];
        $stats['avg_down_payment'] = $result['avg_down_payment'];
    }
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM offers");
    $stats['total_offers'] = $stmt->fetchColumn();
} catch (PDOException $e) {
    // Keep default values
}

// Settings map for easy access
$settingsMap = [];
foreach ($settings as $setting) {
    $settingsMap[$setting['setting_key']] = $setting['setting_value'];
}
?>

<!-- Enhanced Bidding Settings Dashboard -->
<div class="w-full">
    <!-- Header -->
    <div class="bg-gradient-to-r from-violet-600 to-purple-600 rounded-2xl shadow-xl p-8 mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-white mb-2 flex items-center gap-3">
                    <i class="fas fa-sliders-h"></i>
                    Bidding Control Settings
                </h1>
                <p class="text-violet-100">Configure platform-wide bidding rules and requirements</p>
            </div>
            <div class="bg-white/20 backdrop-blur-sm rounded-xl px-6 py-3">
                <div class="text-white text-sm font-medium">Last Updated</div>
                <div class="text-white text-lg font-bold"><?= date('M d, Y') ?></div>
            </div>
        </div>
    </div>

    <?php if ($message): ?>
    <div class="mb-6 p-4 rounded-lg <?= $messageType === 'success' ? 'bg-green-50 border border-green-200 text-green-800' : 'bg-red-50 border border-red-200 text-red-800' ?>">
        <div class="flex items-center gap-2">
            <i class="fas fa-<?= $messageType === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
            <span class="font-medium"><?= htmlspecialchars($message) ?></span>
        </div>
    </div>
    <?php endif; ?>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 hover:shadow-md transition-shadow">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm font-medium mb-1">Total Listings</p>
                    <p id="stat-total-listings" class="text-3xl font-bold text-gray-900"><?= number_format($stats['total_listings']) ?></p>
                </div>
                <div class="bg-blue-100 rounded-full p-3">
                    <i class="fas fa-list text-blue-600 text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 hover:shadow-md transition-shadow">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm font-medium mb-1">With Reserve</p>
                    <p id="stat-with-reserve" class="text-3xl font-bold text-gray-900"><?= number_format($stats['with_reserve']) ?></p>
                </div>
                <div class="bg-green-100 rounded-full p-3">
                    <i class="fas fa-shield-alt text-green-600 text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 hover:shadow-md transition-shadow">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm font-medium mb-1">Avg Down Payment</p>
                    <p id="stat-avg-down" class="text-3xl font-bold text-gray-900"><?= number_format($stats['avg_down_payment'], 1) ?>%</p>
                </div>
                <div class="bg-purple-100 rounded-full p-3">
                    <i class="fas fa-percentage text-purple-600 text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 hover:shadow-md transition-shadow">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm font-medium mb-1">Total Offers</p>
                    <p id="stat-total-offers" class="text-3xl font-bold text-gray-900"><?= number_format($stats['total_offers']) ?></p>
                </div>
                <div class="bg-orange-100 rounded-full p-3">
                    <i class="fas fa-handshake text-orange-600 text-xl"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Settings Form -->
        <div class="lg:col-span-2">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                <h2 class="text-xl font-bold text-gray-900 mb-6 flex items-center gap-2">
                    <i class="fas fa-cog text-violet-600"></i>
                    Configuration Settings
                </h2>

                <form method="POST" class="space-y-6">
                    <!-- Bid Increment Section -->
                    <div class="bg-gradient-to-r from-blue-50 to-indigo-50 rounded-lg p-5 border border-blue-100">
                        <h3 class="font-semibold text-gray-900 mb-4 flex items-center gap-2">
                            <i class="fas fa-arrow-up text-blue-600"></i>
                            Bid Increment Settings
                        </h3>
                        
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Increment Type</label>
                                <select id="bid_increment_type" name="bid_increment_type" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-violet-500">
                                    <option value="fixed" <?= ($settingsMap['bid_increment_type'] ?? 'fixed') === 'fixed' ? 'selected' : '' ?>>Fixed Amount ($)</option>
                                    <option value="percentage" <?= ($settingsMap['bid_increment_type'] ?? 'fixed') === 'percentage' ? 'selected' : '' ?>>Percentage (%)</option>
                                </select>
                            </div>

                            <div id="fixed_increment_section" style="display: <?= ($settingsMap['bid_increment_type'] ?? 'fixed') === 'percentage' ? 'none' : 'block' ?>;">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Fixed Increment ($)</label>
                                <input type="number" id="bid_increment_fixed" name="bid_increment_fixed" value="<?= $settingsMap['bid_increment_fixed'] ?? '10.00' ?>" step="0.01" min="1" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-violet-500">
                                <p class="text-xs text-gray-500 mt-1">Each bid must be at least this amount higher</p>
                            </div>

                            <div id="percentage_increment_section" style="display: <?= ($settingsMap['bid_increment_type'] ?? 'fixed') === 'fixed' ? 'none' : 'block' ?>;">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Percentage Increment (%)</label>
                                <input type="number" id="bid_increment_percentage" name="bid_increment_percentage" value="<?= $settingsMap['bid_increment_percentage'] ?? '5.00' ?>" step="0.01" min="1" max="100" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-violet-500">
                                <p class="text-xs text-gray-500 mt-1">Each bid must be this percentage higher</p>
                            </div>
                        </div>
                    </div>

                    <!-- Down Payment Section -->
                    <div class="bg-gradient-to-r from-green-50 to-emerald-50 rounded-lg p-5 border border-green-100">
                        <h3 class="font-semibold text-gray-900 mb-4 flex items-center gap-2">
                            <i class="fas fa-money-bill-wave text-green-600"></i>
                            Down Payment Settings
                        </h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Default (%)</label>
                                <input type="number" name="default_down_payment" value="<?= $settingsMap['default_down_payment'] ?? '50.00' ?>" step="0.01" min="1" max="100" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500" required>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Minimum (%)</label>
                                <input type="number" name="min_down_payment" value="<?= $settingsMap['min_down_payment'] ?? '1.00' ?>" step="0.01" min="0.01" max="100" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500" required>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Maximum (%)</label>
                                <input type="number" name="max_down_payment" value="<?= $settingsMap['max_down_payment'] ?? '100.00' ?>" step="0.01" min="1" max="100" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500" required>
                            </div>
                        </div>
                        <p class="text-xs text-gray-500 mt-2">Sellers can reduce down payment to minimum value</p>
                    </div>

                    <!-- Auction Settings Section -->
                    <div class="bg-gradient-to-r from-purple-50 to-pink-50 rounded-lg p-5 border border-purple-100">
                        <h3 class="font-semibold text-gray-900 mb-4 flex items-center gap-2">
                            <i class="fas fa-clock text-purple-600"></i>
                            Auction Settings
                        </h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Extension Time (min)</label>
                                <input type="number" name="auction_extension_minutes" value="<?= $settingsMap['auction_extension_minutes'] ?? '2' ?>" min="1" max="60" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500" required>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Warning Threshold (%)</label>
                                <input type="number" name="down_payment_warning_threshold" value="<?= $settingsMap['down_payment_warning_threshold'] ?? '10.00' ?>" step="0.01" min="1" max="100" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500" required>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Reserved Amount (%)</label>
                                <input type="number" name="default_reserved_amount_percentage" value="<?= $settingsMap['default_reserved_amount_percentage'] ?? '0.00' ?>" step="0.01" min="0" max="100" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500" required>
                            </div>
                        </div>
                    </div>

                    <!-- Commission Settings -->
                    <div class="bg-gradient-to-r from-yellow-50 to-orange-50 rounded-lg p-5 border border-yellow-100">
                        <h3 class="font-semibold text-gray-900 mb-4 flex items-center gap-2">
                            <i class="fas fa-percentage text-yellow-600"></i>
                            Platform Commission
                        </h3>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Commission Percentage (%)</label>
                            <input type="number" name="commission_percentage" value="<?= $settingsMap['commission_percentage'] ?? '10.00' ?>" step="0.01" min="0" max="50" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500" required>
                            <p class="text-xs text-gray-500 mt-1">Applied to all future transactions</p>
                        </div>
                    </div>

                    <button type="submit" name="update_settings" class="w-full bg-gradient-to-r from-violet-600 to-purple-600 text-white px-6 py-3 rounded-lg font-semibold hover:from-violet-700 hover:to-purple-700 transition-all shadow-lg hover:shadow-xl flex items-center justify-center gap-2">
                        <i class="fas fa-save"></i>
                        Save All Settings
                    </button>
                </form>
            </div>
        </div>

        <!-- Info Panel -->
        <div class="space-y-6">
            <!-- Quick Guide -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                <h3 class="font-bold text-gray-900 mb-4 flex items-center gap-2">
                    <i class="fas fa-book text-blue-600"></i>
                    Quick Guide
                </h3>
                <div class="space-y-3 text-sm">
                    <div class="flex items-start gap-2">
                        <i class="fas fa-check-circle text-green-500 mt-1"></i>
                        <div>
                            <p class="font-semibold text-gray-700">Bid Increment</p>
                            <p class="text-gray-600">Controls minimum increase for each bid</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-2">
                        <i class="fas fa-check-circle text-green-500 mt-1"></i>
                        <div>
                            <p class="font-semibold text-gray-700">Down Payment</p>
                            <p class="text-gray-600">Default 50%, sellers can reduce to 1%</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-2">
                        <i class="fas fa-check-circle text-green-500 mt-1"></i>
                        <div>
                            <p class="font-semibold text-gray-700">Reserved Amount</p>
                            <p class="text-gray-600">Minimum price - item won't sell below this</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-2">
                        <i class="fas fa-check-circle text-green-500 mt-1"></i>
                        <div>
                            <p class="font-semibold text-gray-700">Auto-Extend</p>
                            <p class="text-gray-600">Prevents last-second sniping</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Important Notes -->
            <div class="bg-gradient-to-br from-yellow-50 to-orange-50 border-2 border-yellow-200 rounded-xl p-6">
                <h4 class="font-semibold text-yellow-900 mb-3 flex items-center gap-2">
                    <i class="fas fa-exclamation-triangle"></i>
                    Important Notes
                </h4>
                <ul class="text-sm text-yellow-800 space-y-2">
                    <li class="flex items-start gap-2">
                        <i class="fas fa-circle text-xs mt-1"></i>
                        <span>Sellers can set their own reserved amount</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <i class="fas fa-circle text-xs mt-1"></i>
                        <span>Items won't sell if bid is below reserve</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <i class="fas fa-circle text-xs mt-1"></i>
                        <span>All changes are logged securely</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <i class="fas fa-circle text-xs mt-1"></i>
                        <span>Changes apply to new listings immediately</span>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
// Toggle increment type sections
document.getElementById('bid_increment_type').addEventListener('change', function() {
    const fixedSection = document.getElementById('fixed_increment_section');
    const percentageSection = document.getElementById('percentage_increment_section');
    
    if (this.value === 'fixed') {
        fixedSection.style.display = 'block';
        percentageSection.style.display = 'none';
    } else {
        fixedSection.style.display = 'none';
        percentageSection.style.display = 'block';
    }
});

// Polling Integration
console.log('🚀 Bidding Dashboard polling initialization started');

if (typeof startPolling !== 'undefined') {
    console.log('✅ Starting polling for bidding dashboard');
    
    try {
        startPolling({
            listings: (newListings) => {
                if (newListings.length > 0) {
                    console.log('📊 New listings detected:', newListings.length);
                    
                    // Update total listings count
                    const totalListingsEl = document.getElementById('stat-total-listings');
                    if (totalListingsEl) {
                        const currentCount = parseInt(totalListingsEl.textContent.replace(/,/g, ''));
                        totalListingsEl.textContent = (currentCount + newListings.length).toLocaleString();
                        
                        // Animate the update
                        totalListingsEl.classList.add('animate-pulse');
                        setTimeout(() => totalListingsEl.classList.remove('animate-pulse'), 1000);
                    }
                    
                    // Count listings with reserve
                    const withReserve = newListings.filter(l => l.reserved_amount && l.reserved_amount > 0).length;
                    if (withReserve > 0) {
                        const withReserveEl = document.getElementById('stat-with-reserve');
                        if (withReserveEl) {
                            const currentCount = parseInt(withReserveEl.textContent.replace(/,/g, ''));
                            withReserveEl.textContent = (currentCount + withReserve).toLocaleString();
                            withReserveEl.classList.add('animate-pulse');
                            setTimeout(() => withReserveEl.classList.remove('animate-pulse'), 1000);
                        }
                    }
                }
            },
            offers: (newOffers) => {
                if (newOffers.length > 0) {
                    console.log('💰 New offers detected:', newOffers.length);
                    
                    // Update total offers count
                    const totalOffersEl = document.getElementById('stat-total-offers');
                    if (totalOffersEl) {
                        const currentCount = parseInt(totalOffersEl.textContent.replace(/,/g, ''));
                        totalOffersEl.textContent = (currentCount + newOffers.length).toLocaleString();
                        
                        // Animate the update
                        totalOffersEl.classList.add('animate-pulse');
                        setTimeout(() => totalOffersEl.classList.remove('animate-pulse'), 1000);
                    }
                }
            }
        });
        
        console.log('✅ Polling started successfully for Bidding Dashboard');
    } catch (error) {
        console.error('❌ Error starting polling:', error);
    }
} else {
    console.warn('⚠️ startPolling function not found - polling.js may not be loaded');
}

</script>

