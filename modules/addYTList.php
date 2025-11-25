<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/validation_helper.php';
$pdo = db();

// Clear any old form data when loading fresh
FormValidator::clearOldInput();

// Fetch dynamic categories & labels
$categories = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$labels = $pdo->query("SELECT id, name FROM labels ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

$questions = $pdo->query("
  SELECT id, question AS question_text, type, options, is_required
  FROM listing_questions
  WHERE listing_type = 'youtube' AND status = 'active'
  ORDER BY id ASC
")->fetchAll(PDO::FETCH_ASSOC);
?>

<style>
  .step-transition {
    transition: all 0.3s ease-in-out;
  }
  
  .fade-in {
    animation: fadeIn 0.5s ease-in-out;
  }
  
  @keyframes fadeIn {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
  }
  
  .required-field {
    border-color: #ef4444 !important;
    background-color: #fef2f2 !important;
  }
  
  .success-field {
    border-color: #10b981 !important;
    background-color: #f0fdf4 !important;
  }
</style>

<div class="bg-gradient-to-br from-gray-50 to-gray-100 min-h-screen">
  <!-- Step 1: Form -->
  <div id="step1" class="max-w-4xl mx-auto px-4 py-8 space-y-8">
    
    <!-- Validation Errors Display -->
    <?php if (hasValidationErrors()): ?>
      <div class="bg-red-50 border-l-4 border-red-500 p-4 rounded-lg mb-6">
        <div class="flex items-start">
          <i class="fas fa-exclamation-circle text-red-500 mt-1 mr-3"></i>
          <div class="flex-1">
            <h3 class="text-red-800 font-semibold mb-2">Please fix the following errors:</h3>
            <ul class="list-disc list-inside text-red-700 space-y-1">
              <?php foreach (getAllValidationErrors() as $field => $error): ?>
                <li><?= htmlspecialchars($error) ?></li>
              <?php endforeach; ?>
            </ul>
          </div>
        </div>
      </div>
    <?php endif; ?>
    
    <!-- Progress Steps -->
    <div class="flex items-center justify-center mb-10">
      <div class="flex items-center">
        <div class="flex flex-col items-center">
          <div class="w-12 h-12 rounded-full bg-gradient-to-r from-purple-600 to-pink-600 text-white flex items-center justify-center shadow-lg">
            <i class="fa-solid fa-play text-sm"></i>
          </div>
          <span class="mt-2 text-sm font-medium text-purple-600">Channel Details</span>
        </div>
        <div class="w-32 h-1 bg-gray-300 mx-4"></div>
        <div class="flex flex-col items-center">
          <div class="w-12 h-12 rounded-full bg-gray-300 text-gray-600 flex items-center justify-center shadow-lg">
            <i class="fa-solid fa-question-circle text-sm"></i>
          </div>
          <span class="mt-2 text-sm font-medium text-gray-500">Questions</span>
        </div>
      </div>
    </div>

    <header class="text-center mb-10">
      <h1 class="text-3xl font-bold text-gray-800 flex items-center justify-center gap-3">
        <i class="fa-brands fa-youtube text-red-600"></i>
        List Your YouTube Channel
      </h1>
      <p class="text-gray-600 mt-2 max-w-2xl mx-auto">
        Fill out the form below to submit your YouTube channel for review and potential sale.
      </p>
      <div class="w-24 h-1 bg-gradient-to-r from-purple-500 to-pink-500 mx-auto mt-4 rounded-full"></div>
    </header>

    <form id="listingForm" class="space-y-6" action="./index.php?p=saveListing" method="post" enctype="multipart/form-data">
      <?php csrfTokenField(); ?>
      <input type="hidden" name="type" value="youtube">

      <!-- Channel Details -->
      <div class="bg-white rounded-xl shadow-lg p-6 mb-8 hover:shadow-xl transition-all duration-300 border border-gray-100">
        <h2 class="text-xl font-semibold text-gray-800 mb-6 border-b pb-3 flex items-center gap-2">
          <i class="fa-solid fa-circle-info text-purple-600 bg-purple-50 p-2 rounded-lg"></i>
          Channel Details
        </h2>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <!-- Name -->
          <div class="space-y-2">
            <label class="text-sm font-medium text-gray-700 flex items-center gap-2">
              Listing Name <span class="text-red-500">*</span>
            </label>
            <div class="relative">
              <input type="text" name="name" placeholder="Enter your listing name" required
                class="w-full border border-gray-300 rounded-lg pl-10 pr-4 py-3 text-gray-700 focus:ring-2 focus:ring-purple-500 focus:border-transparent transition"
                value="">
              <i class="fa-solid fa-user text-gray-400 absolute left-3 top-3.5"></i>
            </div>
          </div>

          <!-- Email -->
          <div class="space-y-2">
            <label class="text-sm font-medium text-gray-700 flex items-center gap-2">
              Your Email <span class="text-red-500">*</span>
            </label>
            <div class="relative">
              <input type="email" name="email" placeholder="Enter your email" required
                class="w-full border border-gray-300 rounded-lg pl-10 pr-4 py-3 text-gray-700 focus:ring-2 focus:ring-purple-500 focus:border-transparent transition"
                value="">
              <i class="fa-solid fa-envelope text-gray-400 absolute left-3 top-3.5"></i>
            </div>
          </div>
        </div>

        <!-- Channel URL -->
        <div class="space-y-2 mt-4">
          <label class="text-sm font-medium text-gray-700 flex items-center gap-2">
            Channel URL <span class="text-red-500">*</span>
          </label>
          <div class="relative">
            <input type="url" name="url" placeholder="https://youtube.com/channel/your-channel" required
              class="w-full border border-gray-300 rounded-lg pl-10 pr-4 py-3 text-gray-700 focus:ring-2 focus:ring-purple-500 focus:border-transparent transition"
              value="">
            <i class="fa-solid fa-link text-gray-400 absolute left-3 top-3.5"></i>
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-4">
          <!-- Monthly Revenue -->
          <div class="space-y-2">
            <label class="text-sm font-medium text-gray-700 flex items-center gap-2">
              Monthly Revenue ($)
            </label>
            <div class="relative">
              <input type="number" name="monthly_revenue" placeholder="e.g. 5000"
                class="w-full border border-gray-300 rounded-lg pl-10 pr-4 py-3 text-gray-700 focus:ring-2 focus:ring-purple-500 focus:border-transparent transition"
                value="">
              <i class="fa-solid fa-dollar-sign text-gray-400 absolute left-3 top-3.5"></i>
            </div>
          </div>

          <!-- Asking Price -->
          <div class="space-y-2">
            <label class="text-sm font-medium text-gray-700 flex items-center gap-2">
              Asking Price ($)
            </label>
            <div class="relative">
              <input type="number" name="asking_price" placeholder="e.g. 50000"
                class="w-full border border-gray-300 rounded-lg pl-10 pr-4 py-3 text-gray-700 focus:ring-2 focus:ring-purple-500 focus:border-transparent transition"
                value="">
              <i class="fa-solid fa-tag text-gray-400 absolute left-3 top-3.5"></i>
            </div>
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-4">
          <!-- Channel Age -->
          <div class="space-y-2">
            <label class="text-sm font-medium text-gray-700 flex items-center gap-2">
              Channel Age (years)
            </label>
            <div class="relative">
              <input type="text" name="site_age" placeholder="e.g. 3"
                class="w-full border border-gray-300 rounded-lg pl-10 pr-4 py-3 text-gray-700 focus:ring-2 focus:ring-purple-500 focus:border-transparent transition"
                value="">
              <i class="fa-solid fa-clock text-gray-400 absolute left-3 top-3.5"></i>
            </div>
          </div>

          <!-- Subscribers -->
          <div class="space-y-2">
            <label class="text-sm font-medium text-gray-700 flex items-center gap-2">
              Subscribers
            </label>
            <div class="relative">
              <input type="number" name="subscribers" placeholder="e.g. 100000"
                class="w-full border border-gray-300 rounded-lg pl-10 pr-4 py-3 text-gray-700 focus:ring-2 focus:ring-purple-500 focus:border-transparent transition"
                value="">
              <i class="fa-solid fa-users text-gray-400 absolute left-3 top-3.5"></i>
            </div>
          </div>
        </div>

        <!-- Faceless Channel -->
        <div class="space-y-2 pt-4">
          <label class="text-sm font-medium text-gray-700 flex items-center gap-2">
            Is the channel faceless?
          </label>
          <div class="flex items-center gap-6 mt-2">
            <label class="flex items-center gap-2 bg-gray-50 px-4 py-2 rounded-lg hover:bg-purple-50 transition duration-150 cursor-pointer">
              <input type="radio" name="faceless" value="1" class="text-purple-600 focus:ring-purple-500">
              <i class="fa-solid fa-check text-green-500"></i>
              <span>Yes</span>
            </label>
            <label class="flex items-center gap-2 bg-gray-50 px-4 py-2 rounded-lg hover:bg-purple-50 transition duration-150 cursor-pointer">
              <input type="radio" name="faceless" value="0" class="text-purple-600 focus:ring-purple-500">
              <i class="fa-solid fa-xmark text-red-500"></i>
              <span>No</span>
            </label>
          </div>
        </div>
      </div>

      <!-- 🎯 BIDDING CONTROL SECTION -->
      <div class="bg-white rounded-xl shadow-lg p-6 mb-8 hover:shadow-xl transition-all duration-300 border border-gray-100">
        <h2 class="text-xl font-semibold text-gray-800 mb-6 border-b pb-3 flex items-center gap-2">
          <i class="fa-solid fa-gavel text-purple-600 bg-purple-50 p-2 rounded-lg"></i>
          Bidding & Auction Settings
        </h2>

        <!-- Info Box -->
        <div class="bg-gradient-to-r from-red-50 to-pink-50 border border-red-200 rounded-xl p-5 mb-6">
          <h3 class="text-sm font-semibold text-red-900 mb-3 flex items-center gap-2">
            <i class="fa-solid fa-info-circle text-red-600"></i>
            How Bidding Controls Work
          </h3>
          <ul class="text-xs text-red-800 space-y-2">
            <li class="flex items-start gap-2">
              <i class="fa-solid fa-shield-alt text-red-600 mt-0.5"></i>
              <span><strong>Reserved Amount:</strong> Your channel won't sell if the highest bid is below this amount</span>
            </li>
            <li class="flex items-start gap-2">
              <i class="fa-solid fa-percentage text-red-600 mt-0.5"></i>
              <span><strong>Down Payment:</strong> Lower percentage (even 1%) attracts more bidders</span>
            </li>
            <li class="flex items-start gap-2">
              <i class="fa-solid fa-clock text-red-600 mt-0.5"></i>
              <span><strong>Auto-extend:</strong> Prevents last-second sniping by extending auction time</span>
            </li>
            <li class="flex items-start gap-2">
              <i class="fa-solid fa-bolt text-red-600 mt-0.5"></i>
              <span><strong>Buy Now:</strong> Allow instant purchase at a fixed price</span>
            </li>
          </ul>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <!-- Reserved Amount -->
          <div class="space-y-2">
            <label class="text-sm font-medium text-gray-700 flex items-center gap-2">
              <i class="fa-solid fa-shield-alt text-purple-600"></i>
              Reserved Amount ($)
            </label>
            <div class="relative">
              <input type="number" name="reserved_amount" placeholder="e.g. 80000" step="0.01" min="0"
                class="w-full border border-gray-300 rounded-lg pl-10 pr-4 py-3 text-gray-700 focus:ring-2 focus:ring-purple-500 focus:border-transparent transition">
              <i class="fa-solid fa-dollar-sign text-gray-400 absolute left-3 top-3.5"></i>
            </div>
            <p class="text-xs text-gray-500 mt-1">Minimum price - won't sell below this amount</p>
          </div>

          <!-- Minimum Down Payment -->
          <div class="space-y-2">
            <label class="text-sm font-medium text-gray-700 flex items-center gap-2">
              <i class="fa-solid fa-percentage text-purple-600"></i>
              Minimum Down Payment (%)
            </label>
            <div class="relative">
              <input type="number" name="min_down_payment_percentage" placeholder="50" min="1" max="100" value="50" step="0.01"
                class="w-full border border-gray-300 rounded-lg pl-10 pr-4 py-3 text-gray-700 focus:ring-2 focus:ring-purple-500 focus:border-transparent transition">
              <i class="fa-solid fa-percent text-gray-400 absolute left-3 top-3.5"></i>
            </div>
            <p class="text-xs text-gray-500 mt-1">Can be reduced to 1% (default 50%)</p>
          </div>

          <!-- Buy Now Price -->
          <div class="space-y-2">
            <label class="text-sm font-medium text-gray-700 flex items-center gap-2">
              <i class="fa-solid fa-bolt text-purple-600"></i>
              Buy Now Price ($)
            </label>
            <div class="relative">
              <input type="number" name="buy_now_price" placeholder="e.g. 150000" step="0.01" min="0"
                class="w-full border border-gray-300 rounded-lg pl-10 pr-4 py-3 text-gray-700 focus:ring-2 focus:ring-purple-500 focus:border-transparent transition">
              <i class="fa-solid fa-dollar-sign text-gray-400 absolute left-3 top-3.5"></i>
            </div>
            <p class="text-xs text-gray-500 mt-1">Optional - instant purchase price</p>
          </div>

          <!-- Auction Duration -->
          <div class="space-y-2">
            <label class="text-sm font-medium text-gray-700 flex items-center gap-2">
              <i class="fa-solid fa-calendar-days text-purple-600"></i>
              Auction Duration (days)
            </label>
            <div class="relative">
              <input type="number" name="auction_duration_days" placeholder="7" min="1" max="90"
                class="w-full border border-gray-300 rounded-lg pl-10 pr-4 py-3 text-gray-700 focus:ring-2 focus:ring-purple-500 focus:border-transparent transition">
              <i class="fa-solid fa-clock text-gray-400 absolute left-3 top-3.5"></i>
            </div>
            <p class="text-xs text-gray-500 mt-1">Leave empty for no time limit</p>
          </div>
        </div>

        <!-- Auto-extend Checkbox -->
        <div class="mt-6 flex items-center gap-3 bg-purple-50 p-4 rounded-lg border border-purple-200">
          <input type="checkbox" name="auto_extend_enabled" id="auto_extend_yt" checked
            class="h-5 w-5 text-purple-600 rounded focus:ring-purple-500 border-gray-300">
          <label for="auto_extend_yt" class="text-sm font-medium text-gray-700 flex items-center gap-2 cursor-pointer">
            <i class="fa-solid fa-clock-rotate-left text-purple-600"></i>
            Auto-extend auction on late bids (prevents sniping)
          </label>
        </div>
      </div>

      <!-- Back to original structure -->
      <div class="bg-white rounded-xl shadow-lg p-6 mb-8 hover:shadow-xl transition-all duration-300 border border-gray-100">
        <h2 class="text-xl font-semibold text-gray-800 mb-6 border-b pb-3 flex items-center gap-2">
          <i class="fa-solid fa-folder text-purple-600 bg-purple-50 p-2 rounded-lg"></i>
          Categories & Labels
        </h2>

        <!-- Categories Multi-Select -->
        <div class="space-y-2 pt-4 relative">
          <label class="text-sm font-medium text-gray-700 flex items-center gap-2">
            Category (Niche)
          </label>
          <div id="catSelect" class="w-full mt-1 border border-gray-300 rounded-lg cursor-pointer bg-white px-3 py-2 flex flex-wrap gap-2 min-h-[45px] transition duration-200 hover:border-gray-400">
            <span class="text-gray-400 flex items-center gap-2" id="catPlaceholder">
              <i class="fa-solid fa-folder-open"></i> Select categories
            </span>
          </div>
          <div id="catDropdown" class="absolute z-10 w-full mt-1 bg-white border border-gray-300 rounded-lg shadow-lg max-h-48 overflow-y-auto hidden transition-all duration-300">
            <div class="sticky top-0 bg-white p-2 border-b border-gray-200">
              <div class="relative">
                <input type="text" id="catSearch" placeholder="Search categories..." class="w-full pl-9 pr-3 py-2 text-sm border border-gray-300 rounded-lg outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                <i class="fa-solid fa-magnifying-glass text-gray-400 absolute left-3 top-2.5"></i>
              </div>
            </div>
            <div class="p-1">
              <?php foreach ($categories as $cat): ?>
              <div class="px-3 py-2 hover:bg-purple-50 cursor-pointer text-sm text-gray-700 flex items-center gap-2 rounded-md transition-colors duration-150"
                data-type="cat" data-id="<?= $cat['id'] ?>" data-name="<?= htmlspecialchars($cat['name']) ?>">
                <i class="fa-solid fa-folder text-purple-500 text-xs"></i>
                <?= htmlspecialchars($cat['name']) ?>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
          <input type="hidden" name="categories" id="catHidden">
        </div>

        <!-- Labels Multi-Select -->
        <div class="space-y-2 pt-4 relative">
          <label class="text-sm font-medium text-gray-700 flex items-center gap-2">
            Monetization & Labels
          </label>
          <div id="labelSelect" class="w-full mt-1 border border-gray-300 rounded-lg cursor-pointer bg-white px-3 py-2 flex flex-wrap gap-2 min-h-[45px] transition duration-200 hover:border-gray-400">
            <span class="text-gray-400 flex items-center gap-2" id="labelPlaceholder">
              <i class="fa-solid fa-tags"></i> Select monetization & labels
            </span>
          </div>
          <div id="labelDropdown" class="absolute z-10 w-full mt-1 bg-white border border-gray-300 rounded-lg shadow-lg max-h-48 overflow-y-auto hidden transition-all duration-300">
            <div class="sticky top-0 bg-white p-2 border-b border-gray-200">
              <div class="relative">
                <input type="text" id="labelSearch" placeholder="Search labels..." class="w-full pl-9 pr-3 py-2 text-sm border border-gray-300 rounded-lg outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                <i class="fa-solid fa-magnifying-glass text-gray-400 absolute left-3 top-2.5"></i>
              </div>
            </div>
            <div class="p-1">
              <?php foreach ($labels as $label): ?>
              <div class="px-3 py-2 hover:bg-purple-50 cursor-pointer text-sm text-gray-700 flex items-center gap-2 rounded-md transition-colors duration-150"
                data-type="label" data-id="<?= $label['id'] ?>" data-name="<?= htmlspecialchars($label['name']) ?>">
                <i class="fa-solid fa-tag text-purple-500 text-xs"></i>
                <?= htmlspecialchars($label['name']) ?>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
          <input type="hidden" name="labels" id="labelHidden">
        </div>

        <!-- Faceless Channel -->
        <div class="space-y-2 pt-4">
          <label class="text-sm font-medium text-gray-700 flex items-center gap-2">
            Is the faceless?
         </label>
          <div class="flex items-center gap-6 mt-2">
            <label class="flex items-center gap-2 bg-gray-50 px-4 py-2 rounded-lg hover:bg-purple-50 transition duration-150 cursor-pointer">
              <input type="radio" name="faceless" value="1" class="text-purple-600 focus:ring-purple-500">
              <i class="fa-solid fa-check text-green-500"></i>
              <span>Yes</span>
            </label>
            <label class="flex items-center gap-2 bg-gray-50 px-4 py-2 rounded-lg hover:bg-purple-50 transition duration-150 cursor-pointer">
              <input type="radio" name="faceless" value="0" class="text-purple-600 focus:ring-purple-500">
              <i class="fa-solid fa-xmark text-red-500"></i>
              <span>No</span>
            </label>
          </div>
        </div>
      </div>

      <!-- Proof Upload -->
      <div class="bg-white rounded-xl shadow-lg p-6 mb-8 hover:shadow-xl transition-all duration-300 border border-gray-100">
        <h2 class="text-xl font-semibold text-gray-800 mb-6 border-b pb-3 flex items-center gap-2">
          <i class="fa-solid fa-file-lines text-blue-600 bg-blue-50 p-2 rounded-lg"></i>
          Upload Proof Images
        </h2>

        <!-- Instructions -->
        <div class="bg-gradient-to-r from-red-50 to-pink-50 border border-red-200 rounded-xl p-6 mb-6">
          <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2">
            <i class="fa-brands fa-youtube text-red-600"></i>
            Required Proof Images
          </h3>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="flex items-start gap-3">
              <div class="w-8 h-8 bg-red-100 rounded-full flex items-center justify-center flex-shrink-0 mt-1">
                <i class="fa-solid fa-chart-line text-red-600 text-sm"></i>
              </div>
              <div>
                <h4 class="font-semibold text-red-800 mb-1">Channel Analytics Proof</h4>
                <p class="text-sm text-red-700">YouTube Studio analytics screenshots showing subscribers, views, and channel performance data</p>
              </div>
            </div>
            <div class="flex items-start gap-3">
              <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center flex-shrink-0 mt-1">
                <i class="fa-solid fa-dollar-sign text-green-600 text-sm"></i>
              </div>
              <div>
                <h4 class="font-semibold text-green-800 mb-1">Revenue Proof</h4>
                <p class="text-sm text-green-700">YouTube AdSense earnings, sponsorship payments, or other monetization screenshots</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Upload Area -->
        <div id="uploadArea" class="border-2 border-dashed border-gray-300 rounded-xl p-8 text-center hover:border-purple-400 hover:bg-purple-50 cursor-pointer transition duration-300 bg-gray-50">
          <i class="fa-solid fa-cloud-arrow-up text-5xl text-purple-400 mb-4"></i>
          <p class="text-gray-700 font-medium text-lg">Upload Your Proof Images</p>
          <p class="text-sm text-gray-500 mt-2">Click to browse or drag & drop multiple images</p>
          <p class="text-xs text-gray-400 mt-1">PNG, JPG (Max 10MB each) • Multiple files supported</p>
          <input type="file" name="proof_files[]" id="fileInput" class="hidden" multiple accept="image/*">
          <button type="button" id="chooseFilesBtn"
            class="mt-4 px-6 py-2.5 rounded-lg bg-purple-600 text-white hover:bg-purple-700 transition flex items-center gap-2 mx-auto">
            <i class="fa-solid fa-images"></i>
            <span>Browse Images</span>
          </button>
        </div>

        <div id="uploadedFiles" class="mt-6 space-y-3"></div>
      </div>

      <!-- Step Navigation -->
      <div id="step1Navigation" class="text-center pt-4">
        <button type="button" id="continueToQuestions"
          class="px-10 py-3.5 rounded-lg bg-gradient-to-r from-purple-600 to-pink-600 text-white font-semibold hover:from-purple-700 hover:to-pink-700 transition shadow-md hover:shadow-lg transform hover:-translate-y-0.5 flex items-center gap-2 mx-auto">
          <span>Next</span>
          <i class="fa-solid fa-arrow-right"></i>
        </button>
      </div>
    </form>
  </div>

  <!-- Questions Section (Step 2) -->
  <div id="step2" class="hidden max-w-4xl mx-auto px-4 py-8 space-y-8">
    <!-- Progress Steps for Step 2 -->
    <div class="flex items-center justify-center mb-10">
      <div class="flex items-center">
        <div class="flex flex-col items-center">
          <div class="w-12 h-12 rounded-full bg-green-500 text-white flex items-center justify-center shadow-lg">
            <i class="fa-solid fa-check text-sm"></i>
          </div>
          <span class="mt-2 text-sm font-medium text-green-600">Channel Details</span>
        </div>
        <div class="w-32 h-1 bg-gradient-to-r from-purple-500 to-pink-500 mx-4"></div>
        <div class="flex flex-col items-center">
          <div class="w-12 h-12 rounded-full bg-gradient-to-r from-purple-600 to-pink-600 text-white flex items-center justify-center shadow-lg">
            <i class="fa-solid fa-question-circle text-sm"></i>
          </div>
          <span class="mt-2 text-sm font-medium text-purple-600">Questions</span>
        </div>
      </div>
    </div>

    <header class="text-center mb-10">
      <h1 class="text-3xl font-bold text-gray-800 flex items-center justify-center gap-3">
        <i class="fa-brands fa-youtube text-red-600"></i>
        Additional Questions
      </h1>
      <p class="text-gray-600 mt-2 max-w-2xl mx-auto">
        Please answer these questions to complete your YouTube channel listing.
      </p>
      <div class="w-24 h-1 bg-gradient-to-r from-purple-500 to-pink-500 mx-auto mt-4 rounded-full"></div>
    </header>

    <div class="bg-white rounded-xl shadow-lg p-6 mb-8 hover:shadow-xl transition-all duration-300 border border-gray-100">
      <h2 class="text-xl font-semibold text-gray-800 mb-6 border-b pb-3 flex items-center gap-2">
        <i class="fa-solid fa-question-circle text-blue-600 bg-blue-50 p-2 rounded-lg"></i>
        Additional Questions
      </h2>

      <?php foreach ($questions as $q): ?>
        <div class="space-y-2 mb-6 p-4 border border-gray-200 rounded-lg hover:border-purple-200 transition duration-200">
          <label class="text-sm font-medium text-gray-700 flex items-center gap-2">
            <i class="fa-solid fa-circle-question text-purple-500"></i>
            <?= htmlspecialchars($q['question_text']) ?>
            <?php if ($q['is_required']): ?><span class="text-red-500">*</span><?php endif; ?>
          </label>

          <?php if ($q['type'] === 'text'): ?>
            <div class="relative">
              <input type="text" name="question_<?= $q['id'] ?>" <?= $q['is_required'] ? 'required' : '' ?>
                class="w-full border border-gray-300 rounded-lg pl-10 pr-4 py-3 text-gray-700 focus:ring-2 focus:ring-purple-500 focus:border-transparent transition">
              <i class="fa-solid fa-pen text-gray-400 absolute left-3 top-3.5"></i>
            </div>

          <?php elseif ($q['type'] === 'number'): ?>
            <div class="relative">
              <input type="number" name="question_<?= $q['id'] ?>" <?= $q['is_required'] ? 'required' : '' ?>
                class="w-full border border-gray-300 rounded-lg pl-10 pr-4 py-3 text-gray-700 focus:ring-2 focus:ring-purple-500 focus:border-transparent transition">
              <i class="fa-solid fa-hashtag text-gray-400 absolute left-3 top-3.5"></i>
            </div>

          <?php elseif ($q['type'] === 'textarea'): ?>
            <div class="relative">
              <textarea name="question_<?= $q['id'] ?>" rows="4" <?= $q['is_required'] ? 'required' : '' ?>
                class="w-full border border-gray-300 rounded-lg pl-10 pr-4 py-3 text-gray-700 focus:ring-2 focus:ring-purple-500 focus:border-transparent transition"></textarea>
              <i class="fa-solid fa-align-left text-gray-400 absolute left-3 top-3.5"></i>
            </div>

          <?php elseif ($q['type'] === 'select'): ?>
            <div class="relative">
              <select name="question_<?= $q['id'] ?>" <?= $q['is_required'] ? 'required' : '' ?>
                class="w-full border border-gray-300 rounded-lg pl-10 pr-10 py-3 text-gray-700 focus:ring-2 focus:ring-purple-500 focus:border-transparent transition appearance-none">
                <option value="">Select an option</option>
                <?php foreach (explode(',', $q['options']) as $opt): ?>
                  <option value="<?= trim($opt) ?>"><?= trim($opt) ?></option>
                <?php endforeach; ?>
              </select>
              <i class="fa-solid fa-list text-gray-400 absolute left-3 top-3.5"></i>
              <i class="fa-solid fa-chevron-down text-gray-400 absolute right-3 top-3.5"></i>
            </div>

          <?php elseif ($q['type'] === 'radio'): ?>
            <div class="flex flex-wrap gap-4">
              <?php foreach (explode(',', $q['options']) as $opt): ?>
                <label class="flex items-center gap-2 bg-gray-50 px-4 py-2 rounded-lg hover:bg-purple-50 transition duration-150 cursor-pointer">
                  <input type="radio" name="question_<?= $q['id'] ?>" value="<?= trim($opt) ?>" class="text-purple-600 focus:ring-purple-500">
                  <i class="fa-regular fa-circle text-purple-500"></i>
                  <span><?= trim($opt) ?></span>
                </label>
              <?php endforeach; ?>
            </div>

          <?php elseif ($q['type'] === 'checkbox'): ?>
            <div class="flex flex-wrap gap-4">
              <?php foreach (explode(',', $q['options']) as $opt): ?>
                <label class="flex items-center gap-2 bg-gray-50 px-4 py-2 rounded-lg hover:bg-purple-50 transition duration-150 cursor-pointer">
                  <input type="checkbox" name="question_<?= $q['id'] ?>[]" value="<?= trim($opt) ?>" class="rounded text-purple-600 focus:ring-purple-500">
                  <i class="fa-regular fa-square-check text-purple-500"></i>
                  <span><?= trim($opt) ?></span>
                </label>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>

      <div class="flex justify-between items-center pt-6">
        <button type="button" id="goBackToForm"
          class="px-8 py-3 rounded-lg bg-gray-500 text-white font-semibold hover:bg-gray-600 transition duration-300 shadow-md hover:shadow-lg flex items-center gap-2">
          <i class="fa-solid fa-arrow-left"></i>
          <span>Back to Form</span>
        </button>
        
        <button type="submit" form="listingForm"
          class="px-10 py-3.5 rounded-lg bg-gradient-to-r from-green-600 to-emerald-600 text-white font-semibold hover:from-green-700 hover:to-emerald-700 transition shadow-md hover:shadow-lg transform hover:-translate-y-0.5 flex items-center gap-2">
          <i class="fa-solid fa-paper-plane"></i>
          <span>Submit for Review</span>
        </button>
      </div>
    </div>
  </div>
</div>

<script>
// Global variables
let selected = { cat: [], label: [] };
let uploadDataTransfer = new DataTransfer();

// Initialize everything when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
  initializeMultiSelect();
  initializeFileUpload();
  initializeNavigation();
  initializeValidation();
});

// Multi-select functionality
function initializeMultiSelect() {
  // Category dropdown
  const catSelect = document.getElementById('catSelect');
  const catDropdown = document.getElementById('catDropdown');
  const catSearch = document.getElementById('catSearch');
  
  if (catSelect) {
    catSelect.addEventListener('click', () => toggleDropdown('cat'));
  }
  
  // Label dropdown
  const labelSelect = document.getElementById('labelSelect');
  const labelDropdown = document.getElementById('labelDropdown');
  const labelSearch = document.getElementById('labelSearch');
  
  if (labelSelect) {
    labelSelect.addEventListener('click', () => toggleDropdown('label'));
  }
  
  // Option selection (only once)
  if (!document.body.dataset.dropdownHandlerAdded) {
    document.body.dataset.dropdownHandlerAdded = 'true';
    
    document.addEventListener('click', function(e) {
      const option = e.target.closest('[data-type]');
      if (option) {
        const type = option.getAttribute('data-type');
        const id = parseInt(option.getAttribute('data-id'));
        const name = option.getAttribute('data-name');
        selectOption(type, id, name);
      }
      
      // Remove option
      const removeBtn = e.target.closest('[data-remove-type]');
      if (removeBtn) {
        const type = removeBtn.getAttribute('data-remove-type');
        const index = parseInt(removeBtn.getAttribute('data-remove-index'));
        removeOption(type, index);
      }
    });
  }
  
  // Search functionality
  if (catSearch) {
    catSearch.addEventListener('input', (e) => filterOptions('cat', e.target.value));
  }
  if (labelSearch) {
    labelSearch.addEventListener('input', (e) => filterOptions('label', e.target.value));
  }
  
  // Close dropdowns when clicking outside (only once)
  if (!document.body.dataset.closeDropdownHandlerAdded) {
    document.body.dataset.closeDropdownHandlerAdded = 'true';
    
    document.addEventListener('click', (e) => {
      if (!e.target.closest('.relative')) {
        document.querySelectorAll('[id$="Dropdown"]').forEach(d => d.classList.add('hidden'));
      }
    });
  }
}

function toggleDropdown(type) {
  const dropdown = document.getElementById(type + 'Dropdown');
  if (dropdown) {
    dropdown.classList.toggle('hidden');
  }
}

function selectOption(type, id, name) {
  if (!selected[type].some(o => o.id === id)) {
    selected[type].push({ id, name });
    renderSelected(type);
    // Don't close dropdown - allow multiple selections
    // toggleDropdown(type);
  }
}

function renderSelected(type) {
  const box = document.getElementById(type + 'Select');
  const hidden = document.getElementById(type + 'Hidden');
  const placeholder = document.getElementById(type + 'Placeholder');
  
  if (!box || !hidden || !placeholder) return;
  
  // Remove existing tags
  box.querySelectorAll('.tag').forEach(t => t.remove());
  
  if (selected[type].length === 0) {
    placeholder.style.display = 'flex';
  } else {
    placeholder.style.display = 'none';
    selected[type].forEach((item, i) => {
      const tag = document.createElement('span');
      tag.className = 'tag bg-purple-100 text-purple-700 px-3 py-1.5 rounded-lg text-sm flex items-center gap-2 shadow-sm';
      tag.innerHTML = `
        <i class="fa-solid ${type === 'cat' ? 'fa-folder' : 'fa-tag'} text-purple-500"></i>
        ${item.name} 
        <button type="button" class="text-purple-500 hover:text-purple-700 transition-colors" data-remove-type="${type}" data-remove-index="${i}">
          <i class="fa-solid fa-xmark"></i>
        </button>
      `;
      box.appendChild(tag);
    });
  }
  
  hidden.value = selected[type].map(o => o.id).join(',');
}

function removeOption(type, index) {
  selected[type].splice(index, 1);
  renderSelected(type);
}

function filterOptions(type, query) {
  const dropdown = document.getElementById(type + 'Dropdown');
  if (!dropdown) return;
  
  const options = dropdown.querySelectorAll('[data-type="' + type + '"]');
  options.forEach(option => {
    const text = option.textContent.toLowerCase();
    option.style.display = text.includes(query.toLowerCase()) ? '' : 'none';
  });
}

// File upload functionality
function initializeFileUpload() {
  const uploadArea = document.getElementById('uploadArea');
  const fileInput = document.getElementById('fileInput');
  const uploadedFiles = document.getElementById('uploadedFiles');
  const chooseFilesBtn = document.getElementById('chooseFilesBtn');
  
  if (!uploadArea || !fileInput || !uploadedFiles) return;
  
  // Click handlers
  uploadArea.addEventListener('click', (e) => {
    if (!chooseFilesBtn.contains(e.target)) {
      fileInput.click();
    }
  });
  
  if (chooseFilesBtn) {
    chooseFilesBtn.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      fileInput.click();
    });
  }
  
  // Drag and drop
  uploadArea.addEventListener('dragover', (e) => {
    e.preventDefault();
    uploadArea.classList.add('border-purple-500', 'bg-purple-50');
  });
  
  uploadArea.addEventListener('dragleave', () => {
    uploadArea.classList.remove('border-purple-500', 'bg-purple-50');
  });
  
  uploadArea.addEventListener('drop', (e) => {
    e.preventDefault();
    uploadArea.classList.remove('border-purple-500', 'bg-purple-50');
    handleFiles(e.dataTransfer.files);
  });
  
  // File input change
  fileInput.addEventListener('change', () => {
    handleFiles(fileInput.files);
  });
}

function handleFiles(files) {
  for (let file of files) {
    if (file.size > 10 * 1024 * 1024) {
      showNotification(`File too large: ${file.name} (Max 10MB)`, 'error');
      continue;
    }
    
    if (!file.type.startsWith('image/')) {
      showNotification(`Invalid file type: ${file.name}. Only images allowed.`, 'error');
      continue;
    }
    
    // Check if file already exists
    let exists = false;
    for (let i = 0; i < uploadDataTransfer.files.length; i++) {
      if (uploadDataTransfer.files[i].name === file.name) {
        exists = true;
        break;
      }
    }
    
    if (exists) {
      showNotification(`File already added: ${file.name}`, 'error');
      continue;
    }
    
    uploadDataTransfer.items.add(file);
    document.getElementById('fileInput').files = uploadDataTransfer.files;
    createFilePreview(file);
  }
}

function createFilePreview(file) {
  const uploadedFiles = document.getElementById('uploadedFiles');
  const fileSize = (file.size / (1024 * 1024)).toFixed(1);
  
  const fileEl = document.createElement('div');
  fileEl.className = 'bg-white rounded-lg border border-gray-200 p-4 hover:shadow-md transition-all duration-200';
  fileEl.setAttribute('data-filename', file.name);
  
  const reader = new FileReader();
  reader.onload = function(e) {
    fileEl.innerHTML = `
      <div class="flex items-start gap-4">
        <div class="flex-shrink-0">
          <img src="${e.target.result}" alt="${file.name}" class="w-20 h-20 object-cover rounded-lg border">
        </div>
        <div class="flex-1 min-w-0">
          <p class="font-medium text-gray-900 truncate">${file.name}</p>
          <div class="flex items-center gap-4 text-sm text-gray-500 mt-1">
            <span class="flex items-center gap-1">
              <i class="fa-solid fa-weight-hanging"></i> ${fileSize} MB
            </span>
            <span class="flex items-center gap-1">
              <i class="fa-solid fa-image text-red-600"></i> Image
            </span>
          </div>
          <div class="mt-2">
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
              <i class="fa-brands fa-youtube mr-1"></i> YouTube Proof
            </span>
          </div>
        </div>
        <button type="button" class="flex-shrink-0 text-red-400 hover:text-red-600 transition-colors duration-200 p-2 rounded-full hover:bg-red-50" onclick="removeFile('${file.name}')">
          <i class="fa-solid fa-trash"></i>
        </button>
      </div>
    `;
  };
  reader.readAsDataURL(file);
  
  uploadedFiles.appendChild(fileEl);
  showNotification(`File added: ${file.name}`, 'success');
}

function removeFile(filename) {
  // Remove from DataTransfer
  const newDt = new DataTransfer();
  for (let i = 0; i < uploadDataTransfer.files.length; i++) {
    if (uploadDataTransfer.files[i].name !== filename) {
      newDt.items.add(uploadDataTransfer.files[i]);
    }
  }
  uploadDataTransfer = newDt;
  
  // Update file input
  document.getElementById('fileInput').files = uploadDataTransfer.files;
  
  // Remove preview
  const fileEl = document.querySelector(`[data-filename="${filename}"]`);
  if (fileEl) {
    fileEl.remove();
  }
  
  showNotification(`File removed: ${filename}`, 'success');
}

// Navigation functionality
function initializeNavigation() {
  const continueBtn = document.getElementById('continueToQuestions');
  const backBtn = document.getElementById('goBackToForm');
  
  if (continueBtn) {
    continueBtn.addEventListener('click', goToQuestions);
  }
  
  if (backBtn) {
    backBtn.addEventListener('click', goBackToForm);
  }
}

function goToQuestions() {
  // Validate required fields
  const requiredFields = [
    { name: 'name', label: 'Name' },
    { name: 'email', label: 'Email' },
    { name: 'url', label: 'Channel URL' }
  ];
  
  let isValid = true;
  let firstInvalidField = null;
  
  requiredFields.forEach(field => {
    const input = document.querySelector(`input[name="${field.name}"]`);
    if (!input.value.trim()) {
      input.classList.add('required-field');
      if (!firstInvalidField) firstInvalidField = input;
      isValid = false;
      
      // Add error message
      if (!input.parentNode.querySelector('.error-message')) {
        const errorMsg = document.createElement('div');
        errorMsg.className = 'error-message text-red-500 text-sm mt-1 flex items-center gap-1';
        errorMsg.innerHTML = `<i class="fa-solid fa-exclamation-circle"></i> ${field.label} is required`;
        input.parentNode.appendChild(errorMsg);
      }
    } else {
      input.classList.remove('required-field');
      input.classList.add('success-field');
      
      const errorMsg = input.parentNode.querySelector('.error-message');
      if (errorMsg) errorMsg.remove();
      
      // Validate email format
      if (field.name === 'email' && !isValidEmail(input.value)) {
        input.classList.add('required-field');
        input.classList.remove('success-field');
        isValid = false;
        
        const errorMsg = document.createElement('div');
        errorMsg.className = 'error-message text-red-500 text-sm mt-1 flex items-center gap-1';
        errorMsg.innerHTML = `<i class="fa-solid fa-exclamation-circle"></i> Please enter a valid email address`;
        input.parentNode.appendChild(errorMsg);
      }
      
      // Validate URL format
      if (field.name === 'url' && !isValidYouTubeURL(input.value)) {
        input.classList.add('required-field');
        input.classList.remove('success-field');
        isValid = false;
        
        const errorMsg = document.createElement('div');
        errorMsg.className = 'error-message text-red-500 text-sm mt-1 flex items-center gap-1';
        errorMsg.innerHTML = `<i class="fa-solid fa-exclamation-circle"></i> Please enter a valid YouTube channel URL`;
        input.parentNode.appendChild(errorMsg);
      }
    }
  });
  
  if (!isValid) {
    if (firstInvalidField) {
      firstInvalidField.focus();
      firstInvalidField.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
    showNotification('Please fill in all required fields correctly before continuing.', 'error');
    return;
  }
  
  showNotification('Channel details validated successfully! Proceeding to questions...', 'success');
  
  // Animate transition
  document.getElementById('step1').style.opacity = '0';
  document.getElementById('step1').style.transform = 'translateX(-50px)';
  
  setTimeout(() => {
    document.getElementById('step1').classList.add('hidden');
    document.getElementById('step2').classList.remove('hidden');
    document.getElementById('step2').classList.add('fade-in');
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }, 300);
}

function goBackToForm() {
  document.getElementById('step2').style.opacity = '0';
  document.getElementById('step2').style.transform = 'translateX(50px)';
  
  setTimeout(() => {
    document.getElementById('step2').classList.add('hidden');
    document.getElementById('step1').classList.remove('hidden');
    document.getElementById('step1').style.opacity = '1';
    document.getElementById('step1').style.transform = 'translateX(0)';
    document.getElementById('step1').classList.add('fade-in');
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }, 300);
}

// Validation functionality
function initializeValidation() {
  const inputs = document.querySelectorAll('input[name="name"], input[name="email"], input[name="url"]');
  inputs.forEach(input => {
    input.addEventListener('blur', function() {
      const errorMsg = this.parentNode.querySelector('.error-message');
      if (errorMsg) errorMsg.remove();
      
      if (!this.value.trim()) {
        this.classList.add('required-field');
      } else {
        this.classList.remove('required-field');
        this.classList.add('success-field');
      }
    });
    
    input.addEventListener('input', function() {
      if (this.classList.contains('required-field') && this.value.trim()) {
        this.classList.remove('required-field');
        this.classList.add('success-field');
        
        const errorMsg = this.parentNode.querySelector('.error-message');
        if (errorMsg) errorMsg.remove();
      }
    });
  });
}

// Helper functions
function isValidEmail(email) {
  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  return emailRegex.test(email);
}

function isValidYouTubeURL(url) {
  const youtubeRegex = /^(https?:\/\/)?(www\.)?(youtube\.com\/(channel\/|c\/|user\/)|youtu\.be\/)/i;
  return youtubeRegex.test(url);
}

function showNotification(message, type = 'info') {
  const notification = document.createElement('div');
  notification.className = `fixed top-4 right-4 p-4 rounded-lg shadow-lg z-50 max-w-sm transform transition-all duration-300 ${
    type === 'error' ? 'bg-red-500 text-white' : 
    type === 'success' ? 'bg-green-500 text-white' : 
    'bg-blue-500 text-white'
  }`;
  
  notification.innerHTML = `
    <div class="flex items-center gap-2">
      <i class="fa-solid ${
        type === 'error' ? 'fa-exclamation-circle' : 
        type === 'success' ? 'fa-check-circle' : 
        'fa-info-circle'
      }"></i>
      <span>${message}</span>
    </div>
  `;
  
  document.body.appendChild(notification);
  
  setTimeout(() => {
    notification.style.transform = 'translateX(0)';
  }, 100);
  
  setTimeout(() => {
    notification.style.transform = 'translateX(100%)';
    setTimeout(() => notification.remove(), 300);
  }, 4000);
}

// Form validation and submission
function validateAndSubmit(event) {
  console.log('=== YOUTUBE FORM VALIDATION STARTED ===');
  
  const fileInput = document.getElementById('fileInput');
  
  // Update the file input with files from DataTransfer
  if (uploadDataTransfer && uploadDataTransfer.files.length > 0) {
    fileInput.files = uploadDataTransfer.files;
    console.log('Updated file input with DataTransfer files:', fileInput.files.length);
  }
  
  console.log('Form submission - Files in input:', fileInput.files.length);
  for (let i = 0; i < fileInput.files.length; i++) {
    console.log('File', i, ':', fileInput.files[i].name);
  }
  
  // Validate categories and labels are selected
  const categoriesValue = document.getElementById('catHidden').value;
  const labelsValue = document.getElementById('labelHidden').value;
  
  console.log('Categories selected:', categoriesValue);
  console.log('Labels selected:', labelsValue);
  console.log('Selected categories array:', selected.cat);
  console.log('Selected labels array:', selected.label);
  
  // Validate required questions in step 2
  if (!validateQuestionsStep()) {
    event.preventDefault();
    showNotification('Please fill in all required fields before submitting.', 'error');
    return false;
  }
  
  console.log('=== YOUTUBE FORM VALIDATION PASSED ===');
  
  // Show loading state
  const submitBtn = event.target;
  const originalText = submitBtn.innerHTML;
  submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> <span>Submitting...</span>';
  submitBtn.disabled = true;
  
  // Re-enable button after 10 seconds in case of issues
  setTimeout(() => {
    submitBtn.innerHTML = originalText;
    submitBtn.disabled = false;
  }, 10000);
  
  showNotification('Submitting your YouTube channel listing...', 'success');
  return true;
}

// Add missing validation for questions step
function validateQuestionsStep() {
  const requiredInputs = document.querySelectorAll('#step2 input[required]:not([type="radio"]):not([type="checkbox"]), #step2 select[required], #step2 textarea[required]');
  const requiredRadios = document.querySelectorAll('#step2 input[type="radio"][required]');
  
  // If no required fields exist, validation passes
  if (requiredInputs.length === 0 && requiredRadios.length === 0) {
    console.log('No required questions found, validation passed');
    return true;
  }
  
  let hasErrors = false;
  
  // Validate text, number, textarea, select fields
  requiredInputs.forEach(field => {
    if (!field.value.trim()) {
      field.classList.add('required-field');
      hasErrors = true;
      
      // Add error message if not exists
      if (!field.parentNode.querySelector('.error-message')) {
        const errorMsg = document.createElement('div');
        errorMsg.className = 'error-message text-red-500 text-sm mt-1 flex items-center gap-1';
        errorMsg.innerHTML = `<i class="fa-solid fa-exclamation-circle"></i> This field is required`;
        field.parentNode.appendChild(errorMsg);
      }
    } else {
      field.classList.remove('required-field');
      field.classList.add('success-field');
      
      // Remove error message if exists
      const errorMsg = field.parentNode.querySelector('.error-message');
      if (errorMsg) errorMsg.remove();
    }
  });
  
  // Validate radio button groups
  const radioGroups = {};
  requiredRadios.forEach(radio => {
    const name = radio.name;
    if (!radioGroups[name]) {
      radioGroups[name] = [];
    }
    radioGroups[name].push(radio);
  });
  
  // Check if at least one radio in each group is checked
  Object.keys(radioGroups).forEach(groupName => {
    const radios = radioGroups[groupName];
    const isChecked = radios.some(radio => radio.checked);
    
    if (!isChecked) {
      hasErrors = true;
      // Mark first radio's parent for error display
      const firstRadio = radios[0];
      const container = firstRadio.closest('.flex');
      if (container && !container.querySelector('.error-message')) {
        const errorMsg = document.createElement('div');
        errorMsg.className = 'error-message text-red-500 text-sm mt-2 flex items-center gap-1';
        errorMsg.innerHTML = `<i class="fa-solid fa-exclamation-circle"></i> Please select an option`;
        container.appendChild(errorMsg);
      }
    } else {
      // Remove error message if exists
      const container = radios[0].closest('.flex');
      if (container) {
        const errorMsg = container.querySelector('.error-message');
        if (errorMsg) errorMsg.remove();
      }
    }
  });
  
  console.log('Validation result:', !hasErrors);
  return !hasErrors;
}

// Add form submission handler
document.addEventListener('DOMContentLoaded', function() {
  const form = document.getElementById('listingForm');
  if (form && !form.dataset.listenerAdded) {
    // Mark form to prevent duplicate listeners
    form.dataset.listenerAdded = 'true';
    
    form.addEventListener('submit', function(e) {
      console.log('YouTube form submitting...');
      
      // Ensure file input has the latest files
      const fileInput = document.getElementById('fileInput');
      if (fileInput && uploadDataTransfer.files.length > 0) {
        fileInput.files = uploadDataTransfer.files;
        console.log('Updated file input with', fileInput.files.length, 'files');
      }
      
      // Check hidden field values before submission
      const categoriesValue = document.getElementById('catHidden').value;
      const labelsValue = document.getElementById('labelHidden').value;
      
      console.log('=== YOUTUBE FORM SUBMISSION DEBUG ===');
      console.log('Categories hidden field value:', categoriesValue);
      console.log('Labels hidden field value:', labelsValue);
      console.log('Files in input:', fileInput ? fileInput.files.length : 'No file input');
      
      // Log form data for debugging
      const formData = new FormData(form);
      console.log('YouTube form data being submitted:');
      for (let [key, value] of formData.entries()) {
        console.log(key, ':', value);
      }
    });
  }
});

// ========================================
// Real-time Categories & Labels Update
// ========================================
let lastUpdateTimestamp = 0;

async function refreshCategoriesAndLabels() {
  try {
    const response = await fetch('../api/get_categories_labels.php');
    const data = await response.json();
    
    console.log('📡 API Response:', data);
    
    if (data.success && data.timestamp > lastUpdateTimestamp) {
      lastUpdateTimestamp = data.timestamp;
      
      // Update categories dropdown
      updateDropdownOptions('cat', data.categories);
      
      // Update labels dropdown
      updateDropdownOptions('label', data.labels);
      
      console.log('✅ Categories & Labels refreshed at', new Date().toLocaleTimeString());
    }
  } catch (error) {
    console.error('❌ Failed to refresh categories/labels:', error);
  }
}

function updateDropdownOptions(type, items) {
  const dropdown = document.getElementById(type + 'Dropdown');
  if (!dropdown) {
    console.warn('⚠️ Dropdown not found:', type + 'Dropdown');
    return;
  }
  
  // Find the options container (skip search box)
  const optionsContainer = dropdown.querySelector('.p-1');
  if (!optionsContainer) {
    console.warn('⚠️ Options container not found in dropdown');
    return;
  }
  
  // Get currently selected IDs from the global selected object
  let selectedIds = [];
  if (typeof selected !== 'undefined' && selected[type]) {
    selectedIds = selected[type].map(item => String(item.id));
  }
  
  console.log(`🔄 Updating ${type} dropdown with ${items.length} items. Selected:`, selectedIds);
  
  // Clear existing options
  optionsContainer.innerHTML = '';
  
  // Add new options
  items.forEach(item => {
    const optionDiv = document.createElement('div');
    optionDiv.className = 'px-3 py-2 hover:bg-purple-50 cursor-pointer text-sm text-gray-700 flex items-center gap-2 rounded-md transition-colors duration-150';
    optionDiv.setAttribute('data-type', type);
    optionDiv.setAttribute('data-id', item.id);
    optionDiv.setAttribute('data-name', item.name);
    
    const iconType = type === 'cat' ? 'folder' : 'tag';
    
    // Add checkmark if already selected
    if (selectedIds.includes(String(item.id))) {
      optionDiv.innerHTML = `
        <i class="fa-solid fa-check text-green-500 text-xs"></i>
        <i class="fa-solid fa-${iconType} text-purple-500 text-xs"></i>
        ${item.name}
      `;
    } else {
      optionDiv.innerHTML = `
        <i class="fa-solid fa-${iconType} text-purple-500 text-xs"></i>
        ${item.name}
      `;
    }
    
    optionsContainer.appendChild(optionDiv);
  });
  
  console.log(`✅ ${type} dropdown updated with ${items.length} options`);
}

// Auto-refresh every 5 seconds
setInterval(refreshCategoriesAndLabels, 5000);

// Initial load after 1 second (to ensure DOM is ready)
setTimeout(refreshCategoriesAndLabels, 1000);

console.log('🔄 Real-time categories & labels update enabled - checking every 5 seconds');
</script>