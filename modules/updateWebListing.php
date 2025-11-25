<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/validation_helper.php';
require_login();

$pdo = db();

// Get listing ID from URL
$listing_id = $_GET['id'] ?? null;

if (!$listing_id) {
    echo "Invalid listing ID";
    exit;
}

// Fetch listing data
$stmt = $pdo->prepare("SELECT * FROM listings WHERE id = :id AND user_id = :user_id LIMIT 1");
$stmt->execute(['id' => $listing_id, 'user_id' => current_user()['id']]);
$listing = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$listing) {
    echo "Listing not found or you don't have permission to edit it";
    exit;
}

// Fetch categories & labels
$categories = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$labels = $pdo->query("SELECT id, name FROM labels ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Fetch selected categories and labels for this listing (with error handling)
$selectedCategoryIds = [];
$selectedLabelIds = [];

try {
    $selectedCategories = $pdo->prepare("SELECT category_id FROM listing_categories WHERE listing_id = ?");
    $selectedCategories->execute([$listing_id]);
    $selectedCategoryIds = $selectedCategories->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    // listing_categories table doesn't exist
    $selectedCategoryIds = [];
}

try {
    $selectedLabels = $pdo->prepare("SELECT label_id FROM listing_labels WHERE listing_id = ?");
    $selectedLabels->execute([$listing_id]);
    $selectedLabelIds = $selectedLabels->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    // listing_labels table doesn't exist
    $selectedLabelIds = [];
}

// Fetch questions for website type (with error handling)
$questions = [];
$existingAnswers = [];

try {
    $questionsStmt = $pdo->prepare("
      SELECT id, question AS question_text, type, options, is_required
      FROM listing_questions
      WHERE listing_type = 'website' AND status = 'active'
      ORDER BY id ASC
    ");
    $questionsStmt->execute();
    $questions = $questionsStmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch existing answers if questions exist
    if (!empty($questions)) {
        try {
            $answerStmt = $pdo->prepare("SELECT question_id, answer FROM listing_answers WHERE listing_id = ?");
            $answerStmt->execute([$listing_id]);
            $answers = $answerStmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($answers as $answer) {
                $existingAnswers[$answer['question_id']] = $answer['answer'];
            }
        } catch (Exception $e) {
            // listing_answers table doesn't exist, continue without answers
            $existingAnswers = [];
        }
    }
} catch (Exception $e) {
    // listing_questions table doesn't exist, continue without questions
    $questions = [];
    $existingAnswers = [];
}

// Fetch proof images (with error handling)
$proof_images = [];
try {
    $proof_stmt = $pdo->prepare("SELECT file_path FROM listing_proofs WHERE listing_id = :id");
    $proof_stmt->execute(['id' => $listing_id]);
    $proof_images = $proof_stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    // listing_proofs table doesn't exist
    $proof_images = [];
}
?>

<form action="./index.php?p=updateListing" method="post" enctype="multipart/form-data" id="websiteUpdateForm">
  <?php csrfTokenField(); ?>
  <input type="hidden" name="id" value="<?= $listing['id'] ?>">
  <input type="hidden" name="type" value="website">

  <div class="bg-gradient-to-br from-gray-50 to-gray-100 min-h-screen">
    <!-- Step 1: Form -->
    <div id="step1" class="max-w-3xl mx-auto p-6 space-y-8">
      <!-- Progress Steps -->
      <div class="flex items-center justify-center mb-10">
        <div class="flex items-center">
          <div class="flex flex-col items-center">
            <div class="w-12 h-12 rounded-full bg-gradient-to-r from-purple-600 to-pink-600 text-white flex items-center justify-center shadow-lg">
              <i class="fa-solid fa-globe text-sm"></i>
            </div>
            <span class="mt-2 text-sm font-medium text-purple-600">Website Details</span>
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

      <header class="text-center py-4">
        <h1 class="text-3xl font-bold text-gray-800">Update Your Website</h1>
        <p class="text-gray-600 mt-2">Edit your website details</p>
        <div class="w-20 h-1 bg-gradient-to-r from-purple-600 to-pink-600 mx-auto mt-4 rounded-full"></div>
      </header>

      <div class="bg-white rounded-xl shadow-lg p-6 transition-all duration-300 hover:shadow-xl border border-gray-100">
        <h2 class="text-xl font-bold mb-6 flex items-center gap-2 text-gray-800">
          <i class="fa-solid fa-globe text-blue-600 bg-blue-50 p-2 rounded-lg"></i> Website Details
        </h2>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <!-- Name -->
          <div class="space-y-2">
            <label class="block text-sm font-medium text-gray-700 flex items-center gap-2">
              Your Name *
            </label>
            <div class="relative">
              <input type="text" name="name" value="<?= htmlspecialchars($listing['name']) ?>" placeholder="Enter your full name" required
                class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 py-2.5 pl-10 pr-4 border transition duration-200">
              <i class="fa-solid fa-user text-gray-400 absolute left-3 top-3.5"></i>
            </div>
          </div>

          <!-- Email -->
          <div class="space-y-2">
            <label class="block text-sm font-medium text-gray-700 flex items-center gap-2">
              Your Email *
            </label>
            <div class="relative">
              <input type="email" name="email" value="<?= htmlspecialchars($listing['email']) ?>" placeholder="Enter your email address" required
                class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 py-2.5 pl-10 pr-4 border transition duration-200">
              <i class="fa-solid fa-envelope text-gray-400 absolute left-3 top-3.5"></i>
            </div>
          </div>

          <!-- URL -->
          <div class="space-y-2 md:col-span-2">
            <label class="block text-sm font-medium text-gray-700 flex items-center gap-2">
               Site URL *
            </label>
            <div class="relative">
              <input type="text" name="url" value="<?= htmlspecialchars($listing['url']) ?>" placeholder="https://yourwebsite.com" required
                class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-2 focus:ring-purple-500 focus:border-purple-500 py-2.5 pl-10 pr-4 border transition duration-200">
              <i class="fa-solid fa-link text-gray-400 absolute left-3 top-3.5"></i>
            </div>
          </div>

          <!-- Traffic Trend -->
          <div class="space-y-2">
            <label class="block text-sm font-medium text-gray-700 flex items-center gap-2">
               Traffic Trend
            </label>
            <div class="relative">
              <select name="traffic_trend"
                class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-2 focus:ring-purple-500 focus:border-purple-500 py-2.5 pl-10 pr-4 border appearance-none transition duration-200">
                <option value="">Select trend</option>
                <option value="Increasing" <?= $listing['traffic_trend'] == 'Increasing' ? 'selected' : '' ?>>Increasing</option>
                <option value="Stable" <?= $listing['traffic_trend'] == 'Stable' ? 'selected' : '' ?>>Stable</option>
                <option value="Decreasing" <?= $listing['traffic_trend'] == 'Decreasing' ? 'selected' : '' ?>>Decreasing</option>
              </select>
              <i class="fa-solid fa-chart-line text-gray-400 absolute left-3 top-3.5"></i>
              <i class="fa-solid fa-chevron-down text-gray-400 absolute right-3 top-3.5"></i>
            </div>
          </div>

          <!-- Monthly Revenue -->
          <div class="space-y-2">
            <label class="block text-sm font-medium text-gray-700 flex items-center gap-2">
             Monthly Revenue ($)
            </label>
            <div class="relative">
              <input type="number" name="monthly_revenue" value="<?= htmlspecialchars($listing['monthly_revenue']) ?>" placeholder="0"
                class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-2 focus:ring-purple-500 focus:border-purple-500 py-2.5 pl-10 pr-4 border transition duration-200">
              <i class="fa-solid fa-dollar-sign text-gray-400 absolute left-3 top-3.5"></i>
            </div>
          </div>

          <!-- Asking Price -->
          <div class="space-y-2">
            <label class="block text-sm font-medium text-gray-700 flex items-center gap-2">
             Asking Price ($) *
            </label>
            <div class="relative">
              <input type="number" name="asking_price" value="<?= htmlspecialchars($listing['asking_price']) ?>" placeholder="e.g. 50000" required
                class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-2 focus:ring-purple-500 focus:border-purple-500 py-2.5 pl-10 pr-4 border transition duration-200">
              <i class="fa-solid fa-tag text-gray-400 absolute left-3 top-3.5"></i>
            </div>
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-4">
          <!-- Site Age -->
          <div class="space-y-2">
            <label class="block text-sm font-medium text-gray-700 flex items-center gap-2">
               Site Age (years)
            </label>
            <div class="relative">
              <input type="text" name="site_age" value="<?= htmlspecialchars($listing['site_age']) ?>" placeholder="e.g., 2.5"
                class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-2 focus:ring-purple-500 focus:border-purple-500 py-2.5 pl-10 pr-4 border transition duration-200">
              <i class="fa-solid fa-clock text-gray-400 absolute left-3 top-3.5"></i>
            </div>
          </div>

          <!-- Categories Multi-Select -->
          <div class="space-y-2 md:col-span-2 relative">
            <label class="block text-sm font-medium text-gray-700 flex items-center gap-2">
               Niche / Category
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
            <input type="hidden" name="categories" id="catHidden" value="<?= implode(',', $selectedCategoryIds) ?>">
          </div>

          <!-- Labels Multi-Select -->
          <div class="space-y-2 md:col-span-2 relative">
            <label class="block text-sm font-medium text-gray-700 flex items-center gap-2">
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
            <input type="hidden" name="labels" id="labelHidden" value="<?= implode(',', $selectedLabelIds) ?>">
          </div>
        </div>
      </div>

    <!-- Upload Proof Images -->
    <div class="bg-white rounded-xl shadow-lg p-6 transition-all duration-300 hover:shadow-xl">
      <h2 class="text-xl font-bold mb-6 flex items-center gap-2 text-gray-800">
        <i class="fa-solid fa-file-lines text-blue-600"></i> Upload Income Proof Images
      </h2>

      <div id="uploadArea" class="border-2 border-dashed border-gray-300 rounded-xl p-8 text-center hover:border-blue-400 hover:bg-blue-50 transition cursor-pointer">
        <i class="fa-solid fa-cloud-arrow-up text-4xl text-gray-400 mb-2"></i>
        <p class="text-gray-600 font-medium">Drag & Drop Files Here</p>
        <p class="text-xs text-gray-400">Supported: PNG, JPG, PDF (Max 10MB each)</p>
        <input type="file" id="fileInput" name="proof_files[]" class="hidden" multiple>
        <button type="button" class="mt-4 px-6 py-2 rounded-lg bg-blue-600 text-white hover:bg-blue-700 transition duration-200">+ Choose Files</button>
      </div>

      <div id="uploadedFiles" class="mt-6 space-y-3">
        <!-- Existing Files -->
        <?php foreach ($proof_images as $img): ?>
          <div class="flex items-center justify-between bg-gray-50 rounded-lg border p-3 hover:bg-white hover:shadow-sm transition" data-file="<?= e($img) ?>">
            <div class="flex items-center gap-3">
              <!-- Image Preview -->
              <img src="<?= e($img) ?>" alt="Proof Image" class="w-16 h-16 object-cover rounded-lg border">

              <div>
                <p class="font-medium text-sm"><?= basename($img) ?></p>
                <div class="flex text-xs text-gray-500 gap-3"><span>Existing File</span></div>
              </div>
            </div>
            <button type="button" class="text-red-500 hover:text-red-700 deleteExisting"><i class="fa-solid fa-trash"></i></button>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

      <!-- Step Navigation -->
      <div id="step1Navigation" class="text-center pt-4">
        <button type="button" id="continueToQuestions"
          class="px-10 py-3.5 rounded-lg bg-gradient-to-r from-purple-600 to-pink-600 text-white font-semibold hover:bg-blue-700 transition duration-300 shadow-md hover:shadow-lg transform hover:-translate-y-0.5 flex items-center gap-2 mx-auto">
          <span>Continue to Questions</span>
          <i class="fa-solid fa-arrow-right"></i>
        </button>
      </div>
    </div>

    <!-- Questions Section (Step 2) -->
    <div id="step2" class="hidden max-w-3xl mx-auto p-6 space-y-8">
      <!-- Progress Steps for Step 2 -->
      <div class="flex items-center justify-center mb-8">
        <div class="flex items-center">
          <div class="flex flex-col items-center">
            <div class="w-10 h-10 rounded-full bg-green-500 text-white flex items-center justify-center shadow-md">
              <i class="fa-solid fa-check text-sm"></i>
            </div>
            <span class="mt-2 text-sm font-medium text-green-600">Basic Info</span>
          </div>
          <div class="w-24 h-1 bg-gradient-to-r from-purple-500 to-pink-500 mx-2"></div>
          <div class="flex flex-col items-center">
            <div class="w-10 h-10 rounded-full bg-gradient-to-r from-purple-600 to-pink-600 text-white flex items-center justify-center shadow-md">
              <i class="fa-solid fa-question-circle text-sm"></i>
            </div>
            <span class="mt-2 text-sm font-medium text-purple-600">Questions</span>
          </div>
        </div>
      </div>

      <header class="text-center py-4">
        <h1 class="text-3xl font-bold text-gray-800">Additional Questions</h1>
        <p class="text-gray-600 mt-2">Please answer these questions to complete your listing update</p>
        <div class="w-20 h-1 bg-gradient-to-r from-purple-500 to-pink-500 mx-auto mt-4 rounded-full"></div>
      </header>

      <div class="bg-white rounded-xl shadow-lg p-6 border border-gray-100">
        <h2 class="text-2xl font-bold mb-6 text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-question-circle text-blue-600 bg-blue-50 p-2 rounded-lg"></i> Additional Questions
        </h2>

        <?php if (!empty($questions)): ?>
        <?php foreach ($questions as $q): ?>
          <div class="mb-6 p-4 border border-gray-200 rounded-lg hover:border-blue-200 transition duration-200">
            <label class="block text-gray-700 font-semibold mb-3 flex items-center gap-2">
              <i class="fa-solid fa-circle-question text-blue-500"></i>
              <?= htmlspecialchars($q['question_text']) ?>
              <?= $q['is_required'] ? '<span class="text-red-500">*</span>' : '' ?>
            </label>

            <?php
              $name = "question_" . $q['id'];
              $isRequired = isset($q['is_required']) && $q['is_required'] ? 'required' : '';
              $options = !empty($q['options']) ? explode(',', $q['options']) : [];
              $existingValue = $existingAnswers[$q['id']] ?? '';
            ?>

            <?php switch ($q['type']): case 'text': ?>
              <div class="relative">
                <input type="text" name="<?= $name ?>" value="<?= htmlspecialchars($existingValue) ?>" class="border border-gray-300 rounded-lg px-4 py-2.5 w-full focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-200 pl-10" <?= $isRequired ?>>
                <i class="fa-solid fa-pen text-gray-400 absolute left-3 top-3"></i>
              </div>
              <?php break; ?>

            <?php case 'number': ?>
              <div class="relative">
                <input type="number" name="<?= $name ?>" value="<?= htmlspecialchars($existingValue) ?>" class="border border-gray-300 rounded-lg px-4 py-2.5 w-full focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-200 pl-10" <?= $isRequired ?>>
                <i class="fa-solid fa-hashtag text-gray-400 absolute left-3 top-3"></i>
              </div>
              <?php break; ?>

            <?php case 'textarea': ?>
              <div class="relative">
                <textarea name="<?= $name ?>" rows="4" class="border border-gray-300 rounded-lg px-4 py-2.5 w-full focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-200 pl-10" <?= $isRequired ?>><?= htmlspecialchars($existingValue) ?></textarea>
                <i class="fa-solid fa-align-left text-gray-400 absolute left-3 top-3"></i>
              </div>
              <?php break; ?>

            <?php case 'select': ?>
              <div class="relative">
                <select name="<?= $name ?>" class="border border-gray-300 rounded-lg px-4 py-2.5 w-full focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-200 appearance-none pl-10 pr-10" <?= $isRequired ?>>
                  <option value="">Select an option</option>
                  <?php foreach ($options as $opt): ?>
                    <option value="<?= htmlspecialchars(trim($opt)) ?>" <?= $existingValue == trim($opt) ? 'selected' : '' ?>><?= htmlspecialchars(trim($opt)) ?></option>
                  <?php endforeach; ?>
                </select>
                <i class="fa-solid fa-list text-gray-400 absolute left-3 top-3"></i>
                <i class="fa-solid fa-chevron-down text-gray-400 absolute right-3 top-3"></i>
              </div>
              <?php break; ?>

            <?php case 'checkbox': ?>
              <div class="space-y-2">
                <?php 
                $existingValues = explode(',', $existingValue);
                foreach ($options as $opt): ?>
                  <label class="inline-flex items-center mr-4 bg-gray-50 px-3 py-2 rounded-lg hover:bg-blue-50 transition duration-150">
                    <input type="checkbox" name="<?= $name ?>[]" value="<?= htmlspecialchars(trim($opt)) ?>" <?= in_array(trim($opt), $existingValues) ? 'checked' : '' ?> class="mr-2 rounded text-blue-600 focus:ring-blue-500">
                    <i class="fa-regular fa-square-check text-blue-500 mr-2"></i>
                    <?= htmlspecialchars(trim($opt)) ?>
                  </label>
                <?php endforeach; ?>
              </div>
              <?php break; ?>

            <?php case 'radio': ?>
              <div class="space-y-2">
                <?php foreach ($options as $opt): ?>
                  <label class="inline-flex items-center mr-4 bg-gray-50 px-3 py-2 rounded-lg hover:bg-blue-50 transition duration-150">
                    <input type="radio" name="<?= $name ?>" value="<?= htmlspecialchars(trim($opt)) ?>" <?= $existingValue == trim($opt) ? 'checked' : '' ?> class="mr-2 text-blue-600 focus:ring-blue-500" <?= $isRequired ?>>
                    <i class="fa-regular fa-circle text-blue-500 mr-2"></i>
                    <?= htmlspecialchars(trim($opt)) ?>
                  </label>
                <?php endforeach; ?>
              </div>
              <?php break; ?>

            <?php default: ?>
              <div class="relative">
                <input type="text" name="<?= $name ?>" value="<?= htmlspecialchars($existingValue) ?>" placeholder="Unsupported field type" class="border border-gray-300 rounded-lg px-4 py-2.5 w-full pl-10" disabled>
                <i class="fa-solid fa-triangle-exclamation text-yellow-500 absolute left-3 top-3"></i>
              </div>
            <?php endswitch; ?>
          </div>
        <?php endforeach; ?>
        <?php else: ?>
          <div class="text-center py-8">
            <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
              <i class="fas fa-question-circle text-gray-400 text-2xl"></i>
            </div>
            <h3 class="text-lg font-semibold text-gray-700 mb-2">No Additional Questions</h3>
            <p class="text-gray-500">No additional questions are configured for website listings.</p>
          </div>
        <?php endif; ?>

        <div class="flex justify-between items-center pt-6">
          <button type="button" id="goBackToForm"
            class="px-8 py-3 rounded-lg bg-gray-500 text-white font-semibold hover:bg-gray-600 transition duration-300 shadow-md hover:shadow-lg flex items-center gap-2">
            <i class="fa-solid fa-arrow-left"></i>
            <span>Back to Form</span>
          </button>
          
          <button type="submit" 
            class="px-10 py-3.5 rounded-lg bg-green-600 text-white font-semibold hover:bg-green-700 transition duration-300 shadow-md hover:shadow-lg transform hover:-translate-y-0.5 flex items-center gap-2">
            <i class="fa-solid fa-save"></i>
            <span>Update Listing</span>
          </button>
        </div>
      </div>
    </div>
  </div>
</form>

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
</style>

<script>
  // ================
  // STEP NAVIGATION
  // ================
  function goToQuestions() {
    // Hide step 1 and show step 2 with animation
    document.getElementById('step1').style.opacity = '0';
    document.getElementById('step1').style.transform = 'translateX(-50px)';
    
    setTimeout(() => {
      document.getElementById('step1').classList.add('hidden');
      document.getElementById('step2').classList.remove('hidden');
      document.getElementById('step2').classList.add('fade-in');
      
      // Smooth scroll to top
      window.scrollTo({ top: 0, behavior: 'smooth' });
    }, 300);
  }
  
  function goBackToForm() {
    // Hide step 2 and show step 1 with animation
    document.getElementById('step2').style.opacity = '0';
    document.getElementById('step2').style.transform = 'translateX(50px)';
    
    setTimeout(() => {
      document.getElementById('step2').classList.add('hidden');
      document.getElementById('step1').classList.remove('hidden');
      document.getElementById('step1').style.opacity = '1';
      document.getElementById('step1').style.transform = 'translateX(0)';
      document.getElementById('step1').classList.add('fade-in');
      
      // Smooth scroll to top
      window.scrollTo({ top: 0, behavior: 'smooth' });
    }, 300);
  }

  document.addEventListener('DOMContentLoaded', function() {
    // Initialize navigation buttons
    const continueBtn = document.getElementById('continueToQuestions');
    const backBtn = document.getElementById('goBackToForm');
    
    if (continueBtn) {
      continueBtn.addEventListener('click', goToQuestions);
    }
    
    if (backBtn) {
      backBtn.addEventListener('click', goBackToForm);
    }
  });

  // ================
  // MULTI-SELECT FUNCTIONALITY
  // ================
  let selected = { 
    cat: <?= json_encode(array_map(function($id, $name) { 
      return ['id' => (int)$id, 'name' => $name]; 
    }, $selectedCategoryIds, array_map(function($id) use ($categories) {
      foreach($categories as $cat) {
        if($cat['id'] == $id) return $cat['name'];
      }
      return '';
    }, $selectedCategoryIds))) ?>, 
    label: <?= json_encode(array_map(function($id, $name) { 
      return ['id' => (int)$id, 'name' => $name]; 
    }, $selectedLabelIds, array_map(function($id) use ($labels) {
      foreach($labels as $label) {
        if($label['id'] == $id) return $label['name'];
      }
      return '';
    }, $selectedLabelIds))) ?>
  };

  function toggleDropdown(type) {
    document.getElementById(type+'Dropdown').classList.toggle('hidden');
  }

  function selectOption(type, id, name) {
    if (!selected[type].some(o => o.id === id)) {
      selected[type].push({id, name});
      renderSelected(type);
    }
  }

  function renderSelected(type) {
    const box = document.getElementById(type+'Select');
    const hidden = document.getElementById(type+'Hidden');
    const placeholder = document.getElementById(type+'Placeholder');

    // remove existing tags but keep placeholder
    box.querySelectorAll('.tag').forEach(t => t.remove());

    if (selected[type].length === 0) {
      placeholder.style.display = 'flex';
    } else {
      placeholder.style.display = 'none';
      selected[type].forEach((item,i)=>{
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

    hidden.value = selected[type].map(o=>o.id).join(',');
  }

  function removeOption(type, i) {
    selected[type].splice(i,1);
    renderSelected(type);
  }

  // Initialize dropdowns and render existing selections
  document.addEventListener('DOMContentLoaded', function() {
    // Render existing selections
    renderSelected('cat');
    renderSelected('label');
    
    // Initialize dropdown click handlers
    const catSelect = document.getElementById('catSelect');
    const labelSelect = document.getElementById('labelSelect');
    
    if (catSelect) {
      catSelect.addEventListener('click', () => toggleDropdown('cat'));
    }
    
    if (labelSelect) {
      labelSelect.addEventListener('click', () => toggleDropdown('label'));
    }
    
    // Initialize dropdown option click handlers
    document.addEventListener('click', function(e) {
      if (e.target.closest('[data-type]')) {
        const option = e.target.closest('[data-type]');
        const type = option.getAttribute('data-type');
        const id = parseInt(option.getAttribute('data-id'));
        const name = option.getAttribute('data-name');
        selectOption(type, id, name);
      }
      
      // Handle remove button clicks
      if (e.target.closest('[data-remove-type]')) {
        const button = e.target.closest('[data-remove-type]');
        const type = button.getAttribute('data-remove-type');
        const index = parseInt(button.getAttribute('data-remove-index'));
        removeOption(type, index);
      }
    });

    // 🔍 Search filter
    ['cat','label'].forEach(type=>{
      const search = document.getElementById(type+'Search');
      const dropdown = document.getElementById(type+'Dropdown');
      search?.addEventListener('input', e=>{
        const q = e.target.value.toLowerCase();
        dropdown.querySelectorAll('div.px-3').forEach(opt=>{
          opt.style.display = opt.textContent.toLowerCase().includes(q)?'':'none';
        });
      });
    });

    // Close dropdown when clicking outside
    document.addEventListener('click', e=>{
      if(!e.target.closest('.relative')){
        document.querySelectorAll('[id$="Dropdown"]').forEach(d=>d.classList.add('hidden'));
      }
    });
  });

  // ================
  // FILE UPLOAD FUNCTIONALITY
  // ================
  let uploadDataTransfer = new DataTransfer();
  
  function initializeFileUpload() {
    const uploadArea = document.getElementById('uploadArea');
    const fileInput = document.getElementById('fileInput');
    const uploadedFiles = document.getElementById('uploadedFiles');

    if (!uploadArea || !fileInput || !uploadedFiles) {
      console.error('File upload elements not found');
      return;
    }

    // Click to upload
    uploadArea.addEventListener('click', (e) => {
      if (e.target.tagName !== 'BUTTON') {
        fileInput.click();
      }
    });

    // Button click
    const uploadButton = uploadArea.querySelector('button');
    if (uploadButton) {
      uploadButton.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        fileInput.click();
      });
    }

    // File input change - handle multiple files
    fileInput.addEventListener('change', (e) => {
      if (e.target.files.length > 0) {
        handleFiles(e.target.files);
      }
    });

    // Drag and drop events
    uploadArea.addEventListener('dragover', (e) => { 
      e.preventDefault();
      e.stopPropagation();
      uploadArea.classList.add('border-purple-500', 'bg-purple-50');
    });
    
    uploadArea.addEventListener('dragleave', (e) => {
      e.preventDefault();
      e.stopPropagation();
      uploadArea.classList.remove('border-purple-500', 'bg-purple-50');
    });
    
    uploadArea.addEventListener('drop', (e) => { 
      e.preventDefault();
      e.stopPropagation();
      uploadArea.classList.remove('border-purple-500', 'bg-purple-50');
      
      const files = e.dataTransfer.files;
      if (files.length > 0) {
        handleFiles(files);
      }
    });

    function handleFiles(files) {
      for(let file of files) {
        // Check file size
        if(file.size > 10*1024*1024) { 
          showNotification(`File too large: ${file.name} (Max 10MB)`, 'error');
          continue; 
        }

        // Check file type
        if (!file.type.startsWith('image/')) {
          showNotification(`Invalid file type: ${file.name}. Only images are allowed.`, 'error');
          continue;
        }

        // Check if file already exists
        let fileExists = false;
        for(let i = 0; i < uploadDataTransfer.files.length; i++) {
          if(uploadDataTransfer.files[i].name === file.name) {
            fileExists = true;
            break;
          }
        }

        if(fileExists) {
          showNotification(`File already added: ${file.name}`, 'error');
          continue;
        }

        // Add to DataTransfer
        uploadDataTransfer.items.add(file);
        
        // Update the actual file input immediately
        fileInput.files = uploadDataTransfer.files;
        
        console.log('File added to input. Total files now:', fileInput.files.length);

        // Create preview element
        createFilePreview(file);
      }
    }

    function createFilePreview(file) {
      const fileSize = (file.size/(1024*1024)).toFixed(1);
      const date = new Date().toLocaleDateString();
      const isImage = file.type.startsWith('image/');

      const fileEl = document.createElement('div');
      fileEl.className = 'bg-white rounded-lg border border-gray-200 p-4 hover:shadow-md transition-all duration-200';
      fileEl.setAttribute('data-filename', file.name);

      if(isImage) {
        // Create image preview
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
                    <i class="fa-solid fa-image text-green-600"></i> Image
                  </span>
                </div>
                <div class="mt-2">
                  <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                    <i class="fa-solid fa-check mr-1"></i> Ready to upload
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
      } else {
        // Non-image file
        fileEl.innerHTML = `
          <div class="flex items-center gap-4">
            <div class="flex-shrink-0">
              <i class="fa-regular fa-file text-3xl text-gray-400"></i>
            </div>
            <div class="flex-1 min-w-0">
              <p class="font-medium text-gray-900 truncate">${file.name}</p>
              <div class="flex items-center gap-4 text-sm text-gray-500 mt-1">
                <span class="flex items-center gap-1">
                  <i class="fa-solid fa-weight-hanging"></i> ${fileSize} MB
                </span>
                <span class="flex items-center gap-1">
                  <i class="fa-solid fa-file"></i> Document
                </span>
              </div>
            </div>
            <button type="button" class="flex-shrink-0 text-red-400 hover:text-red-600 transition-colors duration-200 p-2 rounded-full hover:bg-red-50" onclick="removeFile('${file.name}')">
              <i class="fa-solid fa-trash"></i>
            </button>
          </div>
        `;
      }
      
      uploadedFiles.appendChild(fileEl);
      showNotification(`File added: ${file.name}`, 'success');
    }

    // Delete existing files
    document.querySelectorAll('.deleteExisting').forEach(btn => {
      btn.addEventListener('click', function() {
        const parent = this.closest('div[data-file]');
        const filePath = parent.getAttribute('data-file');

        // Remove from UI
        parent.remove();

        // Add hidden input to delete files on server
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'delete_files[]';
        input.value = filePath;
        document.querySelector('form').appendChild(input);
        
        showNotification('File marked for deletion', 'success');
      });
    });
  }

  // Global function to remove files
  window.removeFile = function(filename) {
    // Remove from DataTransfer
    const newDt = new DataTransfer();
    for(let i = 0; i < uploadDataTransfer.files.length; i++) {
      if(uploadDataTransfer.files[i].name !== filename) {
        newDt.items.add(uploadDataTransfer.files[i]);
      }
    }
    uploadDataTransfer = newDt;
    
    // Update file input
    const fileInput = document.getElementById('fileInput');
    fileInput.files = uploadDataTransfer.files;
    
    // Remove preview element
    const fileEl = document.querySelector(`[data-filename="${filename}"]`);
    if(fileEl) {
      fileEl.remove();
    }
    
    showNotification(`File removed: ${filename}`, 'success');
  };

  // Notification function
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
    
    // Animate in
    setTimeout(() => {
      notification.style.transform = 'translateX(0)';
    }, 100);
    
    // Remove after 4 seconds
    setTimeout(() => {
      notification.style.transform = 'translateX(100%)';
      setTimeout(() => notification.remove(), 300);
    }, 4000);
  }

  // Initialize file upload on page load
  initializeFileUpload();
</script>
