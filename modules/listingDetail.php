<?php
require_once __DIR__ . '/../config.php';

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get listing ID from URL parameter
$listing_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$listing_id) {
    die("Listing not found");
}

$pdo = db();

// Get current user ID
$current_user_id = $_SESSION['user_id'] ?? null;

// Fetch listing details
try {
    $stmt = $pdo->prepare("
        SELECT l.*, u.name as seller_name, u.email as seller_email, u.profile_pic as seller_profile_pic
        FROM listings l 
        LEFT JOIN users u ON l.user_id = u.id 
        WHERE l.id = ?
    ");
    $stmt->execute([$listing_id]);
    $listing = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$listing) {
        die("Listing not found");
    }
    
    // Get listing categories
    $categories = [];
    try {
        $categoriesStmt = $pdo->prepare("
            SELECT c.name 
            FROM listing_categories lc 
            JOIN categories c ON lc.category_id = c.id 
            WHERE lc.listing_id = ?
        ");
        $categoriesStmt->execute([$listing_id]);
        $categories = $categoriesStmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        // Tables don't exist, use empty array
    }
    
    // Get listing labels
    $labels = [];
    try {
        $labelsStmt = $pdo->prepare("
            SELECT l.name 
            FROM listing_labels ll 
            JOIN labels l ON ll.label_id = l.id 
            WHERE ll.listing_id = ?
        ");
        $labelsStmt->execute([$listing_id]);
        $labels = $labelsStmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        // Tables don't exist, use empty array
    }
    
    // Get listing answers for additional details
    $answers = [];
    try {
        $answersStmt = $pdo->prepare("
            SELECT lq.question, la.answer 
            FROM listing_answers la 
            JOIN listing_questions lq ON la.question_id = lq.id 
            WHERE la.listing_id = ?
        ");
        $answersStmt->execute([$listing_id]);
        $answers = $answersStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Debug: uncomment to see what's being fetched
        // echo "<pre>Answers: "; print_r($answers); echo "</pre>";
    } catch (Exception $e) {
        // Tables don't exist, use empty array
        // Debug: uncomment to see error
        // echo "Error fetching answers: " . $e->getMessage();
    }
    
    // Convert answers to associative array for easy access
    $listingData = [];
    foreach ($answers as $answer) {
        $listingData[$answer['question']] = $answer['answer'];
    }
    
    // Get listing proofs
    $proofs = [];
    try {
        $proofsStmt = $pdo->prepare("
            SELECT file_path 
            FROM listing_proofs 
            WHERE listing_id = ?
        ");
        $proofsStmt->execute([$listing_id]);
        $proofs = $proofsStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Add file info for each proof
        foreach ($proofs as &$proof) {
            $fullPath = __DIR__ . '/../public/' . $proof['file_path'];
            $proof['file_type'] = pathinfo($proof['file_path'], PATHINFO_EXTENSION);
            $proof['file_size'] = file_exists($fullPath) ? filesize($fullPath) : 0;
        }
        
    } catch (Exception $e) {
        // Table doesn't exist, use empty array
        $proofs = [];
    }
    
    // Calculate metrics
    $monthly_revenue = $listing['monthly_revenue'] ?: 0;
    $asking_price = $listing['asking_price'] ?: 0;
    $multiple = $monthly_revenue > 0 ? round($asking_price / $monthly_revenue, 1) : 0;
    $annual_revenue = $monthly_revenue * 12;
    $estimated_profit = $monthly_revenue * 0.8; // Assuming 80% profit margin
    $roi = $asking_price > 0 ? round(($estimated_profit * 12 / $asking_price) * 100, 1) : 0;
    
} catch (Exception $e) {
    die("Error loading listing: " . $e->getMessage());
}

// Get similar listings
try {
    $similarStmt = $pdo->prepare("
        SELECT id, name, asking_price, monthly_revenue 
        FROM listings 
        WHERE id != ? AND status = 'approved' AND type = ? 
        ORDER BY RAND() 
        LIMIT 2
    ");
    $similarStmt->execute([$listing_id, $listing['type']]);
    $similarListings = $similarStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $similarListings = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($listing['name']) ?> - Marketplace</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

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
        .tab-active {
            border-bottom: 2px solid #3b82f6;
            color: #3b82f6;
            font-weight: 600;
        }
        .sticky-purchase-card {
            position: sticky;
            top: 2rem;
        }
        .fade-in {
            animation: fadeIn 0.5s ease-in-out;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
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
                            <i class="fas fa-shield-alt mr-1"></i> <?= ucfirst($listing['status']) ?>
                        </span>
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                            <?= ucfirst($listing['type']) ?>
                        </span>
                        <?php foreach ($categories as $category): ?>
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-700">
                                <?= htmlspecialchars($category) ?>
                            </span>
                        <?php endforeach; ?>
                        <?php foreach (array_slice($labels, 0, 2) as $label): ?>
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                                <i class="fas fa-bolt mr-1"></i> <?= htmlspecialchars($label) ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                    
                    <h1 class="text-3xl font-bold text-gray-900 mb-4"><?= htmlspecialchars($listing['name']) ?></h1>
                    
                    <p class="text-gray-600 mb-6">
                        <?php 
                        $description = "A " . $listing['type'] . " business";
                        if ($monthly_revenue > 0) {
                            $description .= " generating $" . number_format($monthly_revenue) . " monthly revenue";
                        }
                        if (!empty($listing['site_age'])) {
                            $description .= ", established " . $listing['site_age'];
                        }
                        echo $description . ".";
                        ?>
                    </p>
                    
                    <!-- Key Metrics -->
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div class="metric-card bg-white border border-gray-200 rounded-xl p-4 text-center">
                            <div class="text-2xl font-bold text-gray-900">
                                <?= $monthly_revenue > 0 ? '$' . number_format($monthly_revenue) : 'N/A' ?>
                            </div>
                            <div class="text-xs text-gray-500 font-medium">Monthly Revenue</div>
                        </div>
                        <div class="metric-card bg-white border border-gray-200 rounded-xl p-4 text-center">
                            <div class="text-2xl font-bold text-gray-900">
                                <?php if ($listing['type'] === 'youtube' && !empty($listing['subscribers'])): ?>
                                    <?= number_format($listing['subscribers']) ?>
                                <?php elseif (!empty($listing['traffic_trend'])): ?>
                                    <span class="text-lg flex items-center justify-center gap-2">
                                        <?php if ($listing['traffic_trend'] === 'Increasing'): ?>
                                            <i class="fas fa-arrow-trend-up text-green-600"></i>
                                        <?php elseif ($listing['traffic_trend'] === 'Stable'): ?>
                                            <i class="fas fa-minus text-blue-600"></i>
                                        <?php else: ?>
                                            <i class="fas fa-arrow-trend-down text-red-600"></i>
                                        <?php endif; ?>
                                        <?= htmlspecialchars($listing['traffic_trend']) ?>
                                    </span>
                                <?php else: ?>
                                    N/A
                                <?php endif; ?>
                            </div>
                            <div class="text-xs text-gray-500 font-medium">
                                <?= $listing['type'] === 'youtube' ? 'Subscribers' : 'Traffic Trend' ?>
                            </div>
                        </div>
                        <div class="metric-card bg-white border border-gray-200 rounded-xl p-4 text-center">
                            <div class="text-2xl font-bold text-gray-900">
                                <?php if ($listing['type'] === 'youtube' && !empty($listing['videos_count'])): ?>
                                    <?= number_format($listing['videos_count']) ?>
                                <?php elseif (!empty($listing['site_age'])): ?>
                                    <?= htmlspecialchars($listing['site_age']) ?>
                                <?php else: ?>
                                    N/A
                                <?php endif; ?>
                            </div>
                            <div class="text-xs text-gray-500 font-medium">
                                <?= $listing['type'] === 'youtube' ? 'Videos' : 'Site Age' ?>
                            </div>
                        </div>
                        <div class="metric-card bg-white border border-gray-200 rounded-xl p-4 text-center">
                            <div class="text-2xl font-bold text-gray-900">
                                <?= $multiple > 0 ? $multiple . 'x' : 'N/A' ?>
                            </div>
                            <div class="text-xs text-gray-500 font-medium">Multiple</div>
                        </div>
                    </div>
                </div>

                <!-- Tabs Navigation -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
                    <div class="border-b border-gray-200">
                        <nav class="flex -mb-px">
                            <button id="overview-tab" class="tab-active py-4 px-6 text-center border-transparent font-medium text-sm">
                                Overview
                            </button>
                            <button id="financials-tab" class="py-4 px-6 text-center border-transparent text-gray-500 font-medium text-sm hover:text-gray-700">
                                Financials
                            </button>
                            <button id="traffic-tab" class="py-4 px-6 text-center border-transparent text-gray-500 font-medium text-sm hover:text-gray-700">
                                Traffic & Metrics
                            </button>
                            <button id="proofs-tab" class="py-4 px-6 text-center border-transparent text-gray-500 font-medium text-sm hover:text-gray-700">
                                Proofs
                            </button>
                            <button id="faq-tab" class="py-4 px-6 text-center border-transparent text-gray-500 font-medium text-sm hover:text-gray-700">
                                Q&A
                            </button>
                        </nav>
                    </div>

                    <!-- Tab Content -->
                    <div class="p-6">
                        <!-- Overview Tab Content -->
                        <div id="overview-content" class="fade-in">
                            <h2 class="text-xl font-bold text-gray-900 mb-4">Business Overview</h2>
                            <div class="prose max-w-none text-gray-600 mb-6">
                                <p class="mb-4">
                                    <?= htmlspecialchars($listing['name']) ?> is a <?= $listing['type'] ?> business 
                                    <?php if ($monthly_revenue > 0): ?>
                                        generating $<?= number_format($monthly_revenue) ?> in monthly revenue
                                    <?php endif; ?>
                                    <?php if ($listing['type'] === 'youtube' && !empty($listing['subscribers'])): ?>
                                        with <?= number_format($listing['subscribers']) ?> subscribers
                                    <?php endif; ?>
                                    <?php if (!empty($listing['site_age'])): ?>
                                        , established <?= htmlspecialchars($listing['site_age']) ?> ago
                                    <?php endif; ?>.
                                    <?php if ($listing['type'] === 'youtube' && !empty($listing['faceless'])): ?>
                                        This is a <?= $listing['faceless'] ? 'faceless' : 'face-to-camera' ?> channel.
                                    <?php endif; ?>
                                </p>
                                <?php if (!empty($listing['traffic_trend'])): ?>
                                    <p class="mb-4">
                                        The business shows a <?= htmlspecialchars($listing['traffic_trend']) ?> trend in traffic and user engagement.
                                        <?php if (!empty($listingData['What is your main traffic source?'])): ?>
                                            Primary traffic source is <?= htmlspecialchars($listingData['What is your main traffic source?']) ?>.
                                        <?php endif; ?>
                                    </p>
                                <?php endif; ?>
                            </div>

                            <!-- Dynamic Questions and Answers -->
                            <?php if (!empty($answers)): ?>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                                    <?php 
                                    $half = ceil(count($answers) / 2);
                                    $firstHalf = array_slice($answers, 0, $half);
                                    $secondHalf = array_slice($answers, $half);
                                    ?>
                                    <div>
                                        <h3 class="font-semibold text-gray-900 mb-3">Business Details</h3>
                                        <ul class="space-y-3 text-gray-600">
                                            <?php foreach ($firstHalf as $answer): ?>
                                                <li class="border-b border-gray-100 pb-2">
                                                    <div class="font-medium text-gray-800 text-sm mb-1">
                                                        <?= htmlspecialchars($answer['question']) ?>
                                                    </div>
                                                    <div class="text-gray-600">
                                                        <?= htmlspecialchars($answer['answer']) ?>
                                                    </div>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                    <?php if (!empty($secondHalf)): ?>
                                        <div>
                                            <h3 class="font-semibold text-gray-900 mb-3">Additional Information</h3>
                                            <ul class="space-y-3 text-gray-600">
                                                <?php foreach ($secondHalf as $answer): ?>
                                                    <li class="border-b border-gray-100 pb-2">
                                                        <div class="font-medium text-gray-800 text-sm mb-1">
                                                            <?= htmlspecialchars($answer['question']) ?>
                                                        </div>
                                                        <div class="text-gray-600">
                                                            <?= htmlspecialchars($answer['answer']) ?>
                                                        </div>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <!-- Proofs Section -->
                            <?php if (!empty($proofs)): ?>
                                <div class="mb-6">
                                    <h3 class="font-semibold text-gray-900 mb-3">Proofs</h3>
                                    <div class="grid grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3">
                                        <?php foreach ($proofs as $proof): ?>
                                            <?php
                                            $extension = pathinfo($proof['file_path'], PATHINFO_EXTENSION);
                                            $isImage = in_array(strtolower($extension), ['jpg', 'jpeg', 'png', 'gif']);
                                            ?>
                                            <?php if ($isImage): ?>
                                                <div class="rounded-lg overflow-hidden bg-gray-100 cursor-pointer hover:opacity-80 transition-opacity" 
                                                     onclick="openImageModal('<?= htmlspecialchars($proof['file_path']) ?>')">
                                                    <img src="<?= htmlspecialchars($proof['file_path']) ?>" 
                                                         alt="Proof" 
                                                         class="w-full h-24 object-cover"
                                                         onerror="this.style.display='none'">
                                                </div>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="mt-3 text-center">
                                        <button onclick="switchTab('proofs')" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                                            <i class="fas fa-eye mr-1"></i>View All Proofs
                                        </button>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- Show traffic trend if available -->
                            <?php if (!empty($listing['traffic_trend'])): ?>
                                <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 mb-6">
                                    <div class="flex">
                                        <div class="flex-shrink-0">
                                            <i class="fas fa-chart-line text-blue-500 text-xl mt-1"></i>
                                        </div>
                                        <div class="ml-3">
                                            <h3 class="text-sm font-medium text-blue-800">Traffic Trend</h3>
                                            <div class="mt-1 text-sm text-blue-700">
                                                <p>This business shows a <strong><?= htmlspecialchars($listing['traffic_trend']) ?></strong> trend in traffic and user engagement.</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- Show additional info if available -->
                            <?php if (!empty($listing['email'])): ?>
                                <div class="mb-6">
                                    <h3 class="font-semibold text-gray-900 mb-3">Contact Information</h3>
                                    <p class="text-gray-600">
                                        Business Email: <span class="font-medium"><?= htmlspecialchars($listing['email']) ?></span>
                                    </p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Financials Tab Content -->
                        <div id="financials-content" class="hidden fade-in">
                            <h2 class="text-xl font-bold text-gray-900 mb-4">Financial Performance</h2>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                                <div class="bg-white border border-gray-200 rounded-xl p-5">
                                    <h3 class="font-semibold text-gray-900 mb-3">Revenue Breakdown</h3>
                                    <div class="space-y-3">
                                        <div class="flex justify-between">
                                            <span class="text-gray-600">Monthly Revenue</span>
                                            <span class="font-medium">
                                                <?= $monthly_revenue > 0 ? '$' . number_format($monthly_revenue) : 'N/A' ?>
                                            </span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-gray-600">Annual Revenue</span>
                                            <span class="font-medium">
                                                <?= $annual_revenue > 0 ? '$' . number_format($annual_revenue) : 'N/A' ?>
                                            </span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-gray-600">Monetization Method</span>
                                            <span class="font-medium">
                                                <?php if (!empty($labels)): ?>
                                                    <?= implode(', ', array_map('htmlspecialchars', $labels)) ?>
                                                <?php else: ?>
                                                    N/A
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                        <div class="flex justify-between border-t border-gray-200 pt-2">
                                            <span class="text-gray-900 font-medium">Asking Price</span>
                                            <span class="font-bold">
                                                <?= $asking_price > 0 ? '$' . number_format($asking_price) : 'Contact for Price' ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                

                            </div>
                            
                            <?php if ($monthly_revenue > 0): ?>
                                <div class="bg-white border border-gray-200 rounded-xl p-5 mb-6">
                                    <h3 class="font-semibold text-gray-900 mb-4">Financial Summary</h3>
                                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                                        <div class="text-center p-4 bg-gray-50 rounded-lg">
                                            <div class="text-lg font-bold text-gray-900">$<?= number_format($monthly_revenue) ?></div>
                                            <div class="text-xs text-gray-500">Monthly Revenue</div>
                                        </div>
                                        <div class="text-center p-4 bg-gray-50 rounded-lg">
                                            <div class="text-lg font-bold text-gray-900">$<?= number_format($annual_revenue) ?></div>
                                            <div class="text-xs text-gray-500">Annual Revenue</div>
                                        </div>
                                        <div class="text-center p-4 bg-gray-50 rounded-lg">
                                            <div class="text-lg font-bold text-green-600">$<?= number_format($estimated_profit) ?></div>
                                            <div class="text-xs text-gray-500">Est. Monthly Profit</div>
                                        </div>
                                        <div class="text-center p-4 bg-gray-50 rounded-lg">
                                            <div class="text-lg font-bold text-blue-600"><?= round(($estimated_profit / $monthly_revenue) * 100, 1) ?>%</div>
                                            <div class="text-xs text-gray-500">Profit Margin</div>
                                        </div>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="bg-white border border-gray-200 rounded-xl p-5 mb-6">
                                    <div class="text-center py-8">
                                        <div class="text-gray-500">No financial data available</div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Traffic & Metrics Tab Content -->
                        <div id="traffic-content" class="hidden fade-in">
                            <h2 class="text-xl font-bold text-gray-900 mb-4">Traffic & Metrics</h2>
                            
                            <?php if (!empty($listingData)): ?>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                                    <div class="bg-white border border-gray-200 rounded-xl p-5">
                                        <h3 class="font-semibold text-gray-900 mb-3">Traffic Information</h3>
                                        <div class="space-y-4">
                                            <?php if ($listing['type'] === 'youtube'): ?>
                                                <?php if (!empty($listing['subscribers'])): ?>
                                                    <div>
                                                        <div class="flex justify-between mb-1">
                                                            <span class="text-sm font-medium text-gray-700">Subscribers</span>
                                                            <span class="text-sm font-medium text-gray-700">
                                                                <?= number_format($listing['subscribers']) ?>
                                                            </span>
                                                        </div>
                                                        <div class="w-full bg-gray-200 rounded-full h-2">
                                                            <div class="bg-red-600 h-2 rounded-full" style="width: 100%"></div>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <?php if (!empty($listing['videos_count'])): ?>
                                                    <div>
                                                        <div class="flex justify-between mb-1">
                                                            <span class="text-sm font-medium text-gray-700">Total Videos</span>
                                                            <span class="text-sm font-medium text-gray-700">
                                                                <?= number_format($listing['videos_count']) ?>
                                                            </span>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <?php if (!empty($listing['faceless'])): ?>
                                                    <div>
                                                        <div class="flex justify-between mb-1">
                                                            <span class="text-sm font-medium text-gray-700">Content Type</span>
                                                            <span class="text-sm font-medium text-gray-700">
                                                                <?= $listing['faceless'] ? 'Faceless' : 'Face-to-Camera' ?>
                                                            </span>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <?php if (isset($listingData['How many monthly unique visitors do you get?'])): ?>
                                                    <div>
                                                        <div class="flex justify-between mb-1">
                                                            <span class="text-sm font-medium text-gray-700">Monthly Visitors</span>
                                                            <span class="text-sm font-medium text-gray-700">
                                                                <?= number_format($listingData['How many monthly unique visitors do you get?']) ?>
                                                            </span>
                                                        </div>
                                                        <div class="w-full bg-gray-200 rounded-full h-2">
                                                            <div class="bg-green-600 h-2 rounded-full" style="width: 100%"></div>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <?php if (isset($listingData['What is your main traffic source?'])): ?>
                                                    <div>
                                                        <div class="flex justify-between mb-1">
                                                            <span class="text-sm font-medium text-gray-700">Main Traffic Source</span>
                                                            <span class="text-sm font-medium text-gray-700">
                                                                <?= htmlspecialchars($listingData['What is your main traffic source?']) ?>
                                                            </span>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                            
                                            <?php if (!empty($listing['traffic_trend'])): ?>
                                                <div>
                                                    <div class="flex justify-between mb-1">
                                                        <span class="text-sm font-medium text-gray-700">Traffic Trend</span>
                                                        <span class="text-sm font-medium text-gray-700 capitalize">
                                                            <?= htmlspecialchars($listing['traffic_trend']) ?>
                                                        </span>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="bg-white border border-gray-200 rounded-xl p-5">
                                        <h3 class="font-semibold text-gray-900 mb-3">Business Metrics</h3>
                                        <div class="space-y-4">
                                            <?php if ($monthly_revenue > 0): ?>
                                                <div>
                                                    <div class="flex justify-between mb-1">
                                                        <span class="text-sm font-medium text-gray-700">Monthly Revenue</span>
                                                        <span class="text-sm font-medium text-gray-700">$<?= number_format($monthly_revenue) ?></span>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if (!empty($listing['site_age'])): ?>
                                                <div>
                                                    <div class="flex justify-between mb-1">
                                                        <span class="text-sm font-medium text-gray-700">Business Age</span>
                                                        <span class="text-sm font-medium text-gray-700"><?= htmlspecialchars($listing['site_age']) ?></span>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if ($multiple > 0): ?>
                                                <div>
                                                    <div class="flex justify-between mb-1">
                                                        <span class="text-sm font-medium text-gray-700">Revenue Multiple</span>
                                                        <span class="text-sm font-medium text-gray-700"><?= $multiple ?>x</span>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-12">
                                    <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                        <i class="fas fa-chart-line text-gray-400 text-xl"></i>
                                    </div>
                                    <h3 class="text-sm font-medium text-gray-900 mb-1">No Traffic Data Available</h3>
                                    <p class="text-sm text-gray-500">Traffic and metrics information has not been provided for this listing</p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Proofs Tab Content -->
                        <div id="proofs-content" class="hidden fade-in">
                            <h2 class="text-xl font-bold text-gray-900 mb-4">Proof Documents</h2>
                            
                            <!-- Debug Info -->
                            <?php if (isset($_GET['debug'])): ?>
                                <div class="bg-yellow-100 border border-yellow-400 rounded p-4 mb-4">
                                    <strong>Debug Info:</strong><br>
                                    Listing ID: <?= $listing_id ?><br>
                                    Proofs Count: <?= count($proofs) ?><br>
                                    <?php if (!empty($proofs)): ?>
                                        Proofs Data: <pre><?= htmlspecialchars(print_r($proofs, true)) ?></pre>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($proofs)): ?>
                                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                                    <?php foreach ($proofs as $proof): ?>
                                        <?php
                                        $extension = pathinfo($proof['file_path'], PATHINFO_EXTENSION);
                                        $isImage = in_array(strtolower($extension), ['jpg', 'jpeg', 'png', 'gif']);
                                        ?>
                                        <?php if ($isImage): ?>
                                            <div class="rounded-xl overflow-hidden bg-gray-100 cursor-pointer hover:shadow-lg transition-all duration-200 hover:scale-105" 
                                                 onclick="openImageModal('<?= htmlspecialchars($proof['file_path']) ?>')">
                                                <img src="<?= htmlspecialchars($proof['file_path']) ?>" 
                                                     alt="Proof" 
                                                     class="w-full h-48 object-cover"
                                                     onerror="this.style.display='none'">
                                            </div>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                                
                                <div class="mt-8 bg-blue-50 border border-blue-200 rounded-xl p-4">
                                    <div class="flex">
                                        <div class="flex-shrink-0">
                                            <i class="fas fa-shield-alt text-blue-500 text-xl mt-1"></i>
                                        </div>
                                        <div class="ml-3">
                                            <h3 class="text-sm font-medium text-blue-800">Verified Documents</h3>
                                            <div class="mt-1 text-sm text-blue-700">
                                                <p>All proof documents have been uploaded by the seller to verify the authenticity and performance of this listing. These may include revenue screenshots, analytics data, and other supporting materials.</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-12">
                                    <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                        <i class="fas fa-file-alt text-gray-400 text-xl"></i>
                                    </div>
                                    <h3 class="text-sm font-medium text-gray-900 mb-1">No Proof Documents</h3>
                                    <p class="text-sm text-gray-500">No proof documents have been uploaded for this listing</p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- FAQ Tab Content -->
                        <div id="faq-content" class="hidden fade-in">
                            <h2 class="text-xl font-bold text-gray-900 mb-4">Questions & Answers</h2>
                            
                            <?php if (!empty($answers)): ?>
                                <div class="space-y-4">
                                    <?php foreach ($answers as $answer): ?>
                                        <div class="border border-gray-200 rounded-xl">
                                            <button class="faq-question w-full px-5 py-4 text-left flex justify-between items-center hover:bg-gray-50 rounded-xl">
                                                <span class="font-medium text-gray-900"><?= htmlspecialchars($answer['question']) ?></span>
                                                <i class="fas fa-chevron-down text-gray-400"></i>
                                            </button>
                                            <div class="faq-answer px-5 pb-4 hidden">
                                                <p class="text-gray-600"><?= htmlspecialchars($answer['answer']) ?></p>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-12">
                                    <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                        <i class="fas fa-question text-gray-400 text-xl"></i>
                                    </div>
                                    <h3 class="text-sm font-medium text-gray-900 mb-1">No Q&A Available</h3>
                                    <p class="text-sm text-gray-500">No questions and answers have been provided for this listing</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column - Purchase Card -->
            <div class="lg:col-span-1">
                <div class="sticky-purchase-card">
                    <div class="bg-white rounded-2xl shadow-lg border border-gray-200 overflow-hidden">
                        <div class="p-6 border-b border-gray-200">
                            <div class="text-center mb-4">
                                <div class="text-sm font-medium text-gray-500 mb-1">Asking Price</div>
                                <div class="text-3xl font-bold text-gray-900">
                                    <?= $asking_price > 0 ? '$' . number_format($asking_price) : 'Contact for Price' ?>
                                </div>
                                <?php if ($multiple > 0): ?>
                                    <div class="text-sm text-gray-500 mt-1"><?= $multiple ?>x monthly revenue</div>
                                <?php endif; ?>
                            </div>
                            
                            <?php if ($roi > 0): ?>
                                <div class="bg-green-50 border border-green-200 rounded-xl p-4 mb-6">
                                    <div class="text-center">
                                        <div class="text-sm font-medium text-green-800 mb-1">Estimated Annual ROI</div>
                                        <div class="text-2xl font-bold text-green-900"><?= $roi ?>%</div>
                                        <div class="text-xs text-green-700">Based on estimated profits</div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($current_user_id && $listing['user_id'] == $current_user_id): ?>
                            <!-- Owner's View -->
                            <div class="bg-blue-50 border-2 border-blue-200 rounded-xl p-4 mb-4">
                                <div class="text-center">
                                    <i class="fas fa-user-check text-blue-600 text-2xl mb-2"></i>
                                    <p class="text-sm font-semibold text-blue-800">This is Your Listing</p>
                                    <p class="text-xs text-blue-600 mt-1">You cannot purchase your own listing</p>
                                </div>
                            </div>
                            <div class="space-y-3">
                                <a href="index.php?p=dashboard&page=my_listing" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-4 rounded-xl transition-colors flex items-center justify-center text-decoration-none">
                                    <i class="fas fa-edit mr-2"></i>
                                    Manage Listing
                                </a>
                            </div>
                            <?php else: ?>
                            <!-- Buyer's View -->
                            <div class="space-y-3">
                                <button onclick="showBuyNowPopupDetail(<?= $listing['id'] ?>, '<?= htmlspecialchars($listing['name']) ?>', '<?= htmlspecialchars($listing['asking_price']) ?>', <?= $listing['user_id'] ?>)" class="w-full bg-primary-600 hover:bg-primary-700 text-white font-semibold py-3 px-4 rounded-xl transition-colors flex items-center justify-center cursor-pointer">
                                    <i class="fas fa-shopping-cart mr-2"></i>
                                    Buy Now
                                </button>
                                
                                <button onclick="showMakeOfferPopupDetail(<?= $listing['id'] ?>, '<?= htmlspecialchars($listing['name']) ?>', '<?= htmlspecialchars($listing['asking_price']) ?>', <?= $listing['user_id'] ?>)" class="w-full border-2 border-primary-600 text-primary-600 hover:bg-primary-600 hover:text-white font-semibold py-3 px-4 rounded-xl transition-colors flex items-center justify-center cursor-pointer">
                                    <i class="fas fa-handshake mr-2"></i>
                                    Make Offer
                                </button>
                                
                                <a href="index.php?p=dashboard&page=message&seller_id=<?= $listing['user_id'] ?>&listing_id=<?= $listing['id'] ?>" class="w-full bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold py-3 px-4 rounded-xl transition-colors flex items-center justify-center text-decoration-none">
                                    <i class="far fa-envelope mr-2"></i>
                                    Contact Seller
                                </a>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="p-6">
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
                            

                            
                            <div class="mt-6 pt-6 border-t border-gray-200">
                                <div class="grid grid-cols-3 gap-4 text-center">
                                    <div>
                                        <i class="fas fa-shield-alt text-green-500 text-lg mb-2"></i>
                                        <div class="text-xs font-medium text-gray-700">Secure</div>
                                    </div>
                                    <div>
                                        <i class="fas fa-handshake text-blue-500 text-lg mb-2"></i>
                                        <div class="text-xs font-medium text-gray-700">Escrow</div>
                                    </div>
                                    <div>
                                        <i class="fas fa-headset text-purple-500 text-lg mb-2"></i>
                                        <div class="text-xs font-medium text-gray-700">Support</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                   
                </div>
            </div>
        </div>
    </main>



    <!-- Image Modal -->
    <div id="imageModal" class="fixed inset-0 bg-black bg-opacity-75 z-50 hidden flex items-center justify-center p-4">
        <div class="relative max-w-4xl max-h-full">
            <button onclick="closeImageModal()" class="absolute top-4 right-4 text-white bg-black bg-opacity-50 rounded-full w-10 h-10 flex items-center justify-center hover:bg-opacity-75 z-10">
                <i class="fas fa-times"></i>
            </button>
            <img id="modalImage" src="" alt="Proof document" class="max-w-full max-h-full rounded-lg">
        </div>
    </div>

    <script>
        // Image modal functions
        function openImageModal(imageSrc) {
            document.getElementById('modalImage').src = imageSrc;
            document.getElementById('imageModal').classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }
        
        function closeImageModal() {
            document.getElementById('imageModal').classList.add('hidden');
            document.body.style.overflow = 'auto';
        }
        
        // Close modal when clicking outside the image
        document.getElementById('imageModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeImageModal();
            }
        });
        
        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeImageModal();
            }
        });

        // Tab switching functionality
        document.getElementById('overview-tab').addEventListener('click', function() {
            switchTab('overview');
        });
        
        document.getElementById('financials-tab').addEventListener('click', function() {
            switchTab('financials');
        });
        
        document.getElementById('traffic-tab').addEventListener('click', function() {
            switchTab('traffic');
        });
        
        document.getElementById('proofs-tab').addEventListener('click', function() {
            switchTab('proofs');
        });
        
        document.getElementById('faq-tab').addEventListener('click', function() {
            switchTab('faq');
        });
        
        function switchTab(tabName) {
            // Hide all tab contents
            document.querySelectorAll('[id$="-content"]').forEach(function(content) {
                content.classList.add('hidden');
            });
            
            // Remove active class from all tabs
            document.querySelectorAll('nav button').forEach(function(tab) {
                tab.classList.remove('tab-active');
                tab.classList.add('text-gray-500');
            });
            
            // Show selected tab content
            document.getElementById(tabName + '-content').classList.remove('hidden');
            
            // Add active class to selected tab
            document.getElementById(tabName + '-tab').classList.add('tab-active');
            document.getElementById(tabName + '-tab').classList.remove('text-gray-500');
        }
        
        // FAQ accordion functionality
        document.querySelectorAll('.faq-question').forEach(function(question) {
            question.addEventListener('click', function() {
                const answer = this.nextElementSibling;
                const icon = this.querySelector('i');
                
                if (answer.classList.contains('hidden')) {
                    answer.classList.remove('hidden');
                    icon.classList.remove('fa-chevron-down');
                    icon.classList.add('fa-chevron-up');
                } else {
                    answer.classList.add('hidden');
                    icon.classList.remove('fa-chevron-up');
                    icon.classList.add('fa-chevron-down');
                }
            });
        });
        
        // Buy Now and Make Offer Popup Functions
        function showBuyNowPopupDetail(listingId, listingName, askingPrice, sellerId) {
            // Create and show popup
            const popup = document.createElement('div');
            popup.id = 'buyNowPopupDetail';
            popup.className = 'fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4';
            popup.innerHTML = `
                <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full transform transition-all">
                    <div class="p-6">
                        <div class="flex items-center justify-between mb-6">
                            <div class="flex items-center gap-3">
                                <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                                    <i class="fas fa-shopping-cart text-blue-600 text-xl"></i>
                                </div>
                                <div>
                                    <h3 class="text-xl font-bold text-gray-900">Buy Now</h3>
                                    <p class="text-sm text-gray-500">Complete your purchase</p>
                                </div>
                            </div>
                            <button onclick="closePopupDetail()" class="text-gray-400 hover:text-gray-600 transition-colors">
                                <i class="fas fa-times text-xl"></i>
                            </button>
                        </div>
                        <div class="bg-gray-50 rounded-lg p-4 mb-6">
                            <h4 class="font-semibold text-gray-900 mb-2">${listingName}</h4>
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-gray-600">Purchase Price:</span>
                                <span class="text-2xl font-bold text-blue-600">$${askingPrice}</span>
                            </div>
                        </div>
                        <div class="space-y-3 mb-6">
                            <div class="flex items-center gap-3 text-sm">
                                <i class="fas fa-shield-alt text-green-500"></i>
                                <span class="text-gray-700">Secure escrow protection</span>
                            </div>
                            <div class="flex items-center gap-3 text-sm">
                                <i class="fas fa-check-circle text-green-500"></i>
                                <span class="text-gray-700">Verified listing with documentation</span>
                            </div>
                            <div class="flex items-center gap-3 text-sm">
                                <i class="fas fa-headset text-green-500"></i>
                                <span class="text-gray-700">24/7 transfer support included</span>
                            </div>
                        </div>
                        <div class="flex gap-3">
                            <button onclick="closePopupDetail()" class="flex-1 px-4 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors font-medium">
                                Cancel
                            </button>
                            <button onclick="window.location.href='./index.php?p=payment&id=${listingId}'" class="flex-1 px-4 py-3 bg-gradient-to-r from-blue-500 to-purple-500 text-white rounded-lg hover:opacity-90 transition-opacity font-semibold">
                                Proceed to Payment
                            </button>
                        </div>
                    </div>
                </div>
            `;
            document.body.appendChild(popup);
            document.body.style.overflow = 'hidden';
        }
        
        function showMakeOfferPopupDetail(listingId, listingName, askingPrice, sellerId) {
            const popup = document.createElement('div');
            popup.id = 'makeOfferPopupDetail';
            popup.className = 'fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4';
            popup.innerHTML = `
                <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full transform transition-all">
                    <div class="p-6">
                        <div class="flex items-center justify-between mb-6">
                            <div class="flex items-center gap-3">
                                <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                                    <i class="fas fa-handshake text-green-600 text-xl"></i>
                                </div>
                                <div>
                                    <h3 class="text-xl font-bold text-gray-900">Make an Offer</h3>
                                    <p class="text-sm text-gray-500">Negotiate the best price</p>
                                </div>
                            </div>
                            <button onclick="closePopupDetail()" class="text-gray-400 hover:text-gray-600 transition-colors">
                                <i class="fas fa-times text-xl"></i>
                            </button>
                        </div>
                        <div class="bg-gray-50 rounded-lg p-4 mb-6">
                            <h4 class="font-semibold text-gray-900 mb-2">${listingName}</h4>
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-gray-600">Asking Price:</span>
                                <span class="text-lg font-bold text-gray-900">$${askingPrice}</span>
                            </div>
                        </div>
                        <div class="flex gap-3">
                            <button onclick="closePopupDetail()" class="flex-1 px-4 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors font-medium">
                                Cancel
                            </button>
                            <button onclick="window.location.href='./index.php?p=makeoffer&id=${listingId}'" class="flex-1 px-4 py-3 bg-gradient-to-r from-green-500 to-green-600 text-white rounded-lg hover:opacity-90 transition-opacity font-semibold">
                                Make Offer
                            </button>
                        </div>
                    </div>
                </div>
            `;
            document.body.appendChild(popup);
            document.body.style.overflow = 'hidden';
        }
        
        // Submit offer function for detail page
        function submitOfferDetail(listingId) {
            const offerAmount = document.getElementById('offerAmountDetail').value;
            const offerMessage = document.getElementById('offerMessageDetail').value.trim();
            const submitButton = document.querySelector('#makeOfferPopupDetail button[onclick*="submitOfferDetail"]');
            
            // Validate offer amount
            if (!offerAmount || offerAmount <= 0) {
                alert('Please enter a valid offer amount');
                document.getElementById('offerAmountDetail').focus();
                return;
            }
            
            // Show loading state
            submitButton.disabled = true;
            submitButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Submitting...';
            
            // Create form data
            const formData = new FormData();
            formData.append('listing_id', listingId);
            formData.append('amount', offerAmount);
            formData.append('message', offerMessage || 'No message provided');
            
            // Submit offer via AJAX
            fetch('index.php?p=submit_offer_ajax', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.text().then(text => {
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        throw new Error('Invalid JSON response from server');
                    }
                });
            })
            .then(data => {
                if (data.success) {
                    // Show success message
                    alert('Your offer has been submitted successfully!');
                    closePopupDetail();
                } else {
                    alert(data.message || 'Failed to submit offer. Please try again.');
                    // Reset button
                    submitButton.disabled = false;
                    submitButton.innerHTML = 'Submit Offer';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Network error: ' + error.message + '. Please try again.');
                // Reset button
                submitButton.disabled = false;
                submitButton.innerHTML = 'Submit Offer';
            });
        }
        
        function closePopupDetail() {
            const buyPopup = document.getElementById('buyNowPopupDetail');
            const offerPopup = document.getElementById('makeOfferPopupDetail');
            if (buyPopup) buyPopup.remove();
            if (offerPopup) offerPopup.remove();
            document.body.style.overflow = 'auto';
        }
        
        // Close popup on outside click
        document.addEventListener('click', function(e) {
            if (e.target.id === 'buyNowPopupDetail' || e.target.id === 'makeOfferPopupDetail') {
                closePopupDetail();
            }
        });
        
        // Close popup with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closePopupDetail();
            }
        });
    </script>
</body>
</html>