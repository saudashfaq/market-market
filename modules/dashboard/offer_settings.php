<?php
require_once __DIR__ . "/../../config.php";
require_once __DIR__ . "/../../middlewares/auth.php";
require_once __DIR__ . "/../../includes/flash_helper.php";

// Only superadmin can access
if (!is_logged_in() || current_user()['role'] !== 'superadmin') {
    header("Location: " . url("index.php?p=dashboard"));
    exit;
}

$pdo = db();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $minOfferPercentage = floatval($_POST['min_offer_percentage'] ?? 70);
    
    // Validate percentage (must be between 1% and 100%)
    if ($minOfferPercentage < 1 || $minOfferPercentage > 100) {
        setErrorMessage('Minimum offer percentage must be between 1% and 100%');
    } else {
        try {
            // Update or insert the setting
            $stmt = $pdo->prepare("
                INSERT INTO system_settings (setting_key, setting_value, description, updated_by) 
                VALUES ('min_offer_percentage', ?, 'Minimum offer percentage of asking price (set by superadmin)', ?)
                ON DUPLICATE KEY UPDATE 
                setting_value = VALUES(setting_value),
                updated_by = VALUES(updated_by)
            ");
            
            $stmt->execute([$minOfferPercentage, current_user()['id']]);
            
            setSuccessMessage('Minimum offer percentage updated successfully to ' . number_format($minOfferPercentage, 1) . '%');
            
            // Log the change
            require_once __DIR__ . "/../../includes/log_helper.php";
            log_action(
                "Offer Settings Updated",
                "Superadmin updated minimum offer percentage to {$minOfferPercentage}%",
                "system_settings",
                current_user()['id']
            );
            
        } catch (Exception $e) {
            setErrorMessage('Error updating setting: ' . $e->getMessage());
        }
    }
}

// Get current setting
$stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'min_offer_percentage'");
$stmt->execute();
$currentPercentage = floatval($stmt->fetchColumn() ?: 70);

// Get statistics
$stmt = $pdo->query("
    SELECT 
        COUNT(*) as total_offers,
        AVG((o.amount / l.asking_price) * 100) as avg_offer_percentage,
        MIN((o.amount / l.asking_price) * 100) as min_offer_percentage,
        MAX((o.amount / l.asking_price) * 100) as max_offer_percentage
    FROM offers o
    JOIN listings l ON o.listing_id = l.id
    WHERE o.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
");
$stats = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<div class="max-w-4xl mx-auto p-6">
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <!-- Header -->
        <div class="bg-gradient-to-r from-blue-600 to-indigo-600 px-6 py-4">
            <h1 class="text-2xl font-bold text-white flex items-center">
                <i class="fas fa-cog mr-3"></i>
                Offer Settings
            </h1>
            <p class="text-blue-100 mt-1">Configure minimum offer requirements for the platform</p>
        </div>

        <!-- Flash Messages -->
        <?php if (hasSuccessMessage()): ?>
            <div class="bg-green-50 border-l-4 border-green-400 p-4 m-6">
                <div class="flex">
                    <i class="fas fa-check-circle text-green-400 mr-3 mt-0.5"></i>
                    <p class="text-green-700"><?= getSuccessMessage() ?></p>
                </div>
            </div>
        <?php endif; ?>

        <?php if (hasErrorMessage()): ?>
            <div class="bg-red-50 border-l-4 border-red-400 p-4 m-6">
                <div class="flex">
                    <i class="fas fa-exclamation-triangle text-red-400 mr-3 mt-0.5"></i>
                    <p class="text-red-700"><?= getErrorMessage() ?></p>
                </div>
            </div>
        <?php endif; ?>

        <div class="p-6">
            <!-- Current Statistics -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
                <div class="bg-blue-50 rounded-lg p-4 text-center">
                    <div class="text-2xl font-bold text-blue-600"><?= number_format($stats['total_offers']) ?></div>
                    <div class="text-sm text-blue-600">Total Offers (30 days)</div>
                </div>
                <div class="bg-green-50 rounded-lg p-4 text-center">
                    <div class="text-2xl font-bold text-green-600"><?= number_format($stats['avg_offer_percentage'] ?? 0, 1) ?>%</div>
                    <div class="text-sm text-green-600">Average Offer %</div>
                </div>
                <div class="bg-orange-50 rounded-lg p-4 text-center">
                    <div class="text-2xl font-bold text-orange-600"><?= number_format($stats['min_offer_percentage'] ?? 0, 1) ?>%</div>
                    <div class="text-sm text-orange-600">Lowest Offer %</div>
                </div>
                <div class="bg-purple-50 rounded-lg p-4 text-center">
                    <div class="text-2xl font-bold text-purple-600"><?= number_format($stats['max_offer_percentage'] ?? 0, 1) ?>%</div>
                    <div class="text-sm text-purple-600">Highest Offer %</div>
                </div>
            </div>

            <!-- Settings Form -->
            <form method="POST" class="space-y-6">
                <div class="bg-gray-50 rounded-lg p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                        <i class="fas fa-percentage text-blue-500 mr-2"></i>
                        Minimum Offer Percentage
                    </h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Minimum Percentage of Asking Price
                            </label>
                            <div class="relative">
                                <input 
                                    type="number" 
                                    name="min_offer_percentage" 
                                    value="<?= $currentPercentage ?>"
                                    min="1" 
                                    max="100" 
                                    step="0.1"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                    required
                                >
                                <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                    <span class="text-gray-500 text-lg">%</span>
                                </div>
                            </div>
                            <p class="text-xs text-gray-500 mt-1">
                                Users cannot make offers below this percentage of the listing's asking price
                            </p>
                        </div>
                        
                        <div class="bg-blue-50 rounded-lg p-4">
                            <h4 class="font-medium text-blue-900 mb-2">How it works:</h4>
                            <ul class="text-sm text-blue-800 space-y-1">
                                <li>• Sets platform-wide minimum offer threshold</li>
                                <li>• Prevents extremely low offers</li>
                                <li>• Maintains marketplace quality</li>
                                <li>• Sellers can set higher minimums if needed</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Example Calculation -->
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                    <h4 class="font-medium text-yellow-900 mb-2 flex items-center">
                        <i class="fas fa-calculator text-yellow-600 mr-2"></i>
                        Example Calculation
                    </h4>
                    <div class="text-sm text-yellow-800">
                        <p>If a listing has an asking price of <strong>$10,000</strong> and minimum percentage is set to <strong id="examplePercentage"><?= $currentPercentage ?>%</strong>:</p>
                        <p class="mt-1">Minimum allowed offer = <strong id="exampleAmount">$<?= number_format(10000 * $currentPercentage / 100) ?></strong></p>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="flex justify-end space-x-4">
                    <a href="index.php?p=dashboard&page=superAdminSettings" 
                       class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors">
                        Cancel
                    </a>
                    <button type="submit" 
                            class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors flex items-center">
                        <i class="fas fa-save mr-2"></i>
                        Update Setting
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const percentageInput = document.querySelector('input[name="min_offer_percentage"]');
    const examplePercentage = document.getElementById('examplePercentage');
    const exampleAmount = document.getElementById('exampleAmount');
    
    function updateExample() {
        const percentage = parseFloat(percentageInput.value) || 0;
        const amount = (10000 * percentage) / 100;
        
        examplePercentage.textContent = percentage.toFixed(1) + '%';
        exampleAmount.textContent = '$' + amount.toLocaleString();
    }
    
    percentageInput.addEventListener('input', updateExample);
});
</script>