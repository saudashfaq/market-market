<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/pagination_helper.php';
require_once __DIR__ . '/../../includes/validation_helper.php';
require_once __DIR__ . '/../../includes/flash_helper.php';
require_login();

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // CSRF validation
  if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
    setErrorMessage('Invalid request. Please try again.');
    header("Location: " . $_SERVER['REQUEST_URI']);
    exit;
  }
  $action = $_POST['action'] ?? '';
  $listing_type = trim($_POST['listing_type'] ?? '');
  $question = trim($_POST['question'] ?? '');
  $type = $_POST['type'] ?? 'text';
  $options = $_POST['options'] ?? null;
  $is_required = isset($_POST['is_required']) ? 1 : 0;
  $status = $_POST['status'] ?? 'active';

  if ($action === 'add' && $question !== '') {
    $stmt = $pdo->prepare("INSERT INTO listing_questions (listing_type, question, type, options, is_required, status) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$listing_type, $question, $type, $options, $is_required, $status]);
  }

  if ($action === 'edit') {
    $id = (int)$_POST['id'];
    $stmt = $pdo->prepare("UPDATE listing_questions SET listing_type=?, question=?, type=?, options=?, is_required=?, status=? WHERE id=?");
    $stmt->execute([$listing_type, $question, $type, $options, $is_required, $status, $id]);
  }

  if ($action === 'delete') {
    $id = (int)$_POST['id'];
    $pdo->prepare("DELETE FROM listing_questions WHERE id=?")->execute([$id]);
  }
}

// Get pagination parameters
$page = getCurrentPage('pg');
$perPage = 10;

// Setup search and filter conditions
$search = $_GET['search'] ?? '';
$listing_type = $_GET['listing_type'] ?? '';
$status = $_GET['status'] ?? '';

$conditions = [];
if ($search) {
    $conditions['question'] = ['like' => $search];
}
if ($listing_type) {
    $conditions['listing_type'] = $listing_type;
}
if ($status) {
    $conditions['status'] = $status;
}

// Get paginated questions
$result = getPaginationData($pdo, 'listing_questions', $conditions, $page, $perPage, 'id DESC');
$questions = $result['data'];
$pagination = $result['pagination'];
?>

<div class="max-w-7xl mx-auto p-6">
  <!-- Header Section -->
  <div class="mb-8">
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Listing Questions</h1>
        <p class="text-gray-600 mt-1">Manage custom questions for different listing types</p>
      </div>
      <button onclick="openAddModal()" class="flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2.5 rounded-lg text-sm font-medium transition-colors shadow-sm">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
          <path fill-rule="evenodd" d="M10 5a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H6a1 1 0 110-2h3V6a1 1 0 011-1z" clip-rule="evenodd" />
        </svg>
        Add Question
      </button>
    </div>

    <!-- Search and Filter Section -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
      <form method="GET" class="flex flex-wrap gap-4 items-end">
        <input type="hidden" name="p" value="dashboard">
        <input type="hidden" name="page" value="superAdminQuestion">
        
        <div class="flex-1 min-w-[200px]">
          <label class="block text-sm font-medium text-gray-700 mb-2">
            <i class="fa fa-search mr-1"></i>Search Questions
          </label>
          <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
                 placeholder="Search by question text..." 
                 class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
        </div>
        
        <div class="min-w-[150px]">
          <label class="block text-sm font-medium text-gray-700 mb-2">
            <i class="fa fa-tag mr-1"></i>Listing Type
          </label>
          <select name="listing_type" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            <option value="">All Types</option>
            <?php
            // Get unique listing types
            $typesStmt = $pdo->query("SELECT DISTINCT listing_type FROM listing_questions WHERE listing_type IS NOT NULL ORDER BY listing_type");
            $types = $typesStmt->fetchAll(PDO::FETCH_COLUMN);
            foreach ($types as $type) {
                $selected = ($listing_type === $type) ? 'selected' : '';
                echo '<option value="' . htmlspecialchars($type) . '" ' . $selected . '>' . htmlspecialchars($type) . '</option>';
            }
            ?>
          </select>
        </div>
        
        <div class="min-w-[150px]">
          <label class="block text-sm font-medium text-gray-700 mb-2">
            <i class="fa fa-toggle-on mr-1"></i>Status
          </label>
          <select name="status" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            <option value="">All Status</option>
            <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
            <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
          </select>
        </div>
        
        <div class="flex gap-2">
          <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors flex items-center">
            <i class="fa fa-search mr-2"></i>Filter
          </button>
          <a href="?p=dashboard&page=superAdminQuestion" class="px-6 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition-colors flex items-center">
            <i class="fa fa-times mr-2"></i>Clear
          </a>
        </div>
      </form>
    </div>
    
    <div>
    </div>
    
    <!-- Stats Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
      <div class="bg-white rounded-lg border border-gray-200 p-4 shadow-sm">
        <div class="flex items-center">
          <div class="rounded-full bg-blue-100 p-3 mr-4">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
          <div>
            <p class="text-sm font-medium text-gray-600">Total Questions</p>
            <p class="text-2xl font-bold text-gray-900"><?= count($questions) ?></p>
          </div>
        </div>
      </div>
      
      <div class="bg-white rounded-lg border border-gray-200 p-4 shadow-sm">
        <div class="flex items-center">
          <div class="rounded-full bg-green-100 p-3 mr-4">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
          </div>
          <div>
            <p class="text-sm font-medium text-gray-600">Active Questions</p>
            <p class="text-2xl font-bold text-gray-900"><?= count(array_filter($questions, function($q) { return $q['status'] === 'active'; })) ?></p>
          </div>
        </div>
      </div>
      
      <div class="bg-white rounded-lg border border-gray-200 p-4 shadow-sm">
        <div class="flex items-center">
          <div class="rounded-full bg-purple-100 p-3 mr-4">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
            </svg>
          </div>
          <div>
            <p class="text-sm font-medium text-gray-600">Listing Types</p>
            <p class="text-2xl font-bold text-gray-900"><?= count(array_unique(array_column($questions, 'listing_type'))) ?></p>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Questions Table -->
  <div class="bg-white shadow-lg rounded-xl overflow-hidden border border-gray-200">
    <div class="overflow-x-auto">
      <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
          <tr>
            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Listing Type</th>
            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Question</th>
            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Required</th>
            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
          </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
          <?php if (empty($questions)): ?>
            <tr>
              <td colspan="7" class="px-6 py-12 text-center">
                <div class="flex flex-col items-center justify-center text-gray-400">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                  <p class="text-lg font-medium">No questions found</p>
                  <p class="mt-1">Get started by adding your first question</p>
                </div>
              </td>
            </tr>
          <?php else: foreach ($questions as $q): ?>
            <tr class="hover:bg-gray-50 transition-colors">
              <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?= $q['id'] ?></td>
              <td class="px-6 py-4 whitespace-nowrap">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                  <?= htmlspecialchars($q['listing_type']) ?>
                </span>
              </td>
              <td class="px-6 py-4 text-sm text-gray-900 max-w-xs truncate"><?= htmlspecialchars($q['question']) ?></td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                  <?= htmlspecialchars($q['type']) ?>
                </span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                <?php if ($q['is_required']): ?>
                  <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                    Required
                  </span>
                <?php else: ?>
                  <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                    Optional
                  </span>
                <?php endif; ?>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                <?php if ($q['status'] === 'active'): ?>
                  <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                    Active
                  </span>
                <?php else: ?>
                  <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                    Inactive
                  </span>
                <?php endif; ?>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                <div class="flex justify-end space-x-2">
                  <button 
                    onclick="openEditModal(<?= $q['id'] ?>, '<?= addslashes($q['listing_type']) ?>', '<?= addslashes($q['question']) ?>', '<?= $q['type'] ?>', '<?= addslashes($q['options']) ?>', <?= $q['is_required'] ?>, '<?= $q['status'] ?>')" 
                    class="inline-flex items-center px-3 py-1.5 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors"
                  >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                    Edit
                  </button>
                  <button 
                    onclick="openDeleteModal(<?= $q['id'] ?>)" 
                    class="inline-flex items-center px-3 py-1.5 border border-transparent text-sm font-medium rounded-md text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition-colors"
                  >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                    Delete
                  </button>
                </div>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
    
    <!-- Pagination -->
    <div class="px-6 py-4 border-t border-gray-200 bg-gray-50">
      <?php 
      $extraParams = ['p' => 'dashboard', 'page' => 'superAdminQuestion'];
      if ($search) $extraParams['search'] = $search;
      if ($listing_type) $extraParams['listing_type'] = $listing_type;
      if ($status) $extraParams['status'] = $status;
      
      echo renderPagination($pagination, url('index.php'), $extraParams, 'pg'); 
      ?>
    </div>
  </div>
</div>

<!-- Add Modal -->
<div id="addModal" class="hidden fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-2">
  <div class="bg-white rounded-lg shadow-xl w-full max-w-sm"> <!-- max-w-md → max-w-sm -->
    <div class="flex items-center justify-between p-4 border-b border-gray-200"> <!-- p-6 → p-4 -->
      <h2 class="text-lg font-semibold text-gray-900">Add New Question</h2> <!-- text-xl → text-lg -->
      <button type="button" onclick="closeAddModal()" class="text-gray-400 hover:text-gray-500 transition">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
        </svg>
      </button>
    </div>

    <form method="POST" class="p-4 space-y-3"> <!-- p-6 → p-4 -->
      <?php csrfTokenField(); ?>
      <input type="hidden" name="action" value="add">

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Listing Type</label>
        <input type="text" name="listing_type" class="w-full border border-gray-300 rounded-md px-2 py-1.5 text-sm focus:ring-blue-500 focus:border-blue-500 transition" placeholder="e.g. SaaS, Ecommerce" required>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Question</label>
        <textarea name="question" rows="2" class="w-full border border-gray-300 rounded-md px-2 py-1.5 text-sm focus:ring-blue-500 focus:border-blue-500 transition" required></textarea>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Input Type</label>
        <select name="type" class="w-full border border-gray-300 rounded-md px-2 py-1.5 text-sm focus:ring-blue-500 focus:border-blue-500 transition" onchange="toggleOptions(this, 'addOptions')">
          <option value="text">Text</option>
          <option value="number">Number</option>
          <option value="textarea">Textarea</option>
          <option value="select">Select</option>
          <option value="radio">Radio</option>
          <option value="checkbox">Checkbox</option>
        </select>
      </div>

      <div id="addOptions" class="hidden">
        <label class="block text-sm font-medium text-gray-700 mb-1">Options (comma separated)</label>
        <input type="text" name="options" class="w-full border border-gray-300 rounded-md px-2 py-1.5 text-sm focus:ring-blue-500 focus:border-blue-500 transition">
      </div>

      <div class="flex items-center">
        <input type="checkbox" name="is_required" id="addRequired" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded transition" checked>
        <label for="addRequired" class="ml-2 block text-sm text-gray-700">Required field</label>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
        <select name="status" class="w-full border border-gray-300 rounded-md px-2 py-1.5 text-sm focus:ring-blue-500 focus:border-blue-500 transition">
          <option value="active">Active</option>
          <option value="inactive">Inactive</option>
        </select>
      </div>

      <div class="flex justify-end space-x-2 pt-3">
        <button type="button" onclick="closeAddModal()" class="px-3 py-1.5 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:ring-2 focus:ring-blue-500 transition">Cancel</button>
        <button type="submit" class="px-3 py-1.5 rounded-md text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:ring-2 focus:ring-blue-500 transition">Save</button>
      </div>
    </form>
  </div>
</div>


<!-- Edit Modal -->
<div id="editModal" class="hidden fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-2">
  <div class="bg-white rounded-lg shadow-xl w-full max-w-sm"> <!-- max-w-md → max-w-sm -->
    <div class="flex items-center justify-between p-4 border-b border-gray-200"> <!-- p-6 → p-4 -->
      <h2 class="text-lg font-semibold text-gray-900">Edit Question</h2> <!-- text-xl → text-lg -->
      <button type="button" onclick="closeEditModal()" class="text-gray-400 hover:text-gray-500 transition">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
        </svg>
      </button>
    </div>
    <form method="POST" class="p-4 space-y-3"> <!-- p-6 → p-4 -->
      <?php csrfTokenField(); ?>
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="id" id="editId">

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Listing Type</label>
        <input id="editListingType" name="listing_type" class="w-full border border-gray-300 rounded-md px-2 py-1.5 text-sm focus:ring-blue-500 focus:border-blue-500 transition" required>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Question</label>
        <textarea id="editQuestion" name="question" rows="2" class="w-full border border-gray-300 rounded-md px-2 py-1.5 text-sm focus:ring-blue-500 focus:border-blue-500 transition" required></textarea>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Input Type</label>
        <select id="editInputType" name="type" class="w-full border border-gray-300 rounded-md px-2 py-1.5 text-sm focus:ring-blue-500 focus:border-blue-500 transition" onchange="toggleOptions(this, 'editOptions')">
          <option value="text">Text</option>
          <option value="number">Number</option>
          <option value="textarea">Textarea</option>
          <option value="select">Select</option>
          <option value="radio">Radio</option>
          <option value="checkbox">Checkbox</option>
        </select>
      </div>

      <div id="editOptions" class="hidden">
        <label class="block text-sm font-medium text-gray-700 mb-1">Options (comma separated)</label>
        <input id="editOptionsInput" type="text" name="options" class="w-full border border-gray-300 rounded-md px-2 py-1.5 text-sm focus:ring-blue-500 focus:border-blue-500 transition">
      </div>

      <div class="flex items-center">
        <input id="editRequired" type="checkbox" name="is_required" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded transition">
        <label for="editRequired" class="ml-2 block text-sm text-gray-700">Required field</label>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
        <select id="editStatus" name="status" class="w-full border border-gray-300 rounded-md px-2 py-1.5 text-sm focus:ring-blue-500 focus:border-blue-500 transition">
          <option value="active">Active</option>
          <option value="inactive">Inactive</option>
        </select>
      </div>

      <div class="flex justify-end space-x-2 pt-3">
        <button type="button" onclick="closeEditModal()" class="px-3 py-1.5 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:ring-2 focus:ring-blue-500 transition">Cancel</button>
        <button type="submit" class="px-3 py-1.5 rounded-md text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:ring-2 focus:ring-blue-500 transition">Update</button>
      </div>
    </form>
  </div>
</div>


<!-- Delete Modal -->
<div id="deleteModal" class="hidden fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
  <div class="bg-white rounded-xl shadow-2xl w-full max-w-sm">
    <div class="p-6 text-center">
      <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100 mb-4">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
        </svg>
      </div>
      <h3 class="text-lg font-medium text-gray-900 mb-2">Delete Question</h3>
      <p class="text-sm text-gray-500 mb-6">Are you sure you want to delete this question? This action cannot be undone.</p>
      <form method="POST" class="flex justify-center space-x-3">
        <?php csrfTokenField(); ?>
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" id="deleteId">
        <button type="button" onclick="closeDeleteModal()" class="px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors">Cancel</button>
        <button type="submit" class="px-4 py-2 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition-colors">Delete</button>
      </form>
    </div>
  </div>
</div>

<script>
function openAddModal() { 
  document.getElementById('addModal').classList.remove('hidden'); 
  document.body.classList.add('overflow-hidden');
}

function closeAddModal() { 
  document.getElementById('addModal').classList.add('hidden'); 
  document.body.classList.remove('overflow-hidden');
}

function openEditModal(id, listingType, question, inputType, options, required, status) {
  document.getElementById('editId').value = id;
  document.getElementById('editListingType').value = listingType;
  document.getElementById('editQuestion').value = question;
  document.getElementById('editInputType').value = inputType;
  document.getElementById('editOptionsInput').value = options || '';
  document.getElementById('editRequired').checked = required == 1;
  document.getElementById('editStatus').value = status;
  toggleOptions(document.getElementById('editInputType'), 'editOptions');
  document.getElementById('editModal').classList.remove('hidden');
  document.body.classList.add('overflow-hidden');
}

function closeEditModal() { 
  document.getElementById('editModal').classList.add('hidden'); 
  document.body.classList.remove('overflow-hidden');
}

function openDeleteModal(id) {
  document.getElementById('deleteId').value = id;
  document.getElementById('deleteModal').classList.remove('hidden');
  document.body.classList.add('overflow-hidden');
}

function closeDeleteModal() { 
  document.getElementById('deleteModal').classList.add('hidden'); 
  document.body.classList.remove('overflow-hidden');
}

function toggleOptions(select, id) {
  const val = select.value;
  const box = document.getElementById(id);
  if (['select','radio','checkbox'].includes(val)) {
    box.classList.remove('hidden');
  } else {
    box.classList.add('hidden');
  }
}

// Close modals when clicking outside
document.addEventListener('click', function(event) {
  const addModal = document.getElementById('addModal');
  const editModal = document.getElementById('editModal');
  const deleteModal = document.getElementById('deleteModal');
  
  if (event.target === addModal) closeAddModal();
  if (event.target === editModal) closeEditModal();
  if (event.target === deleteModal) closeDeleteModal();
});

// Polling Integration for SuperAdmin Questions
window.addEventListener('load', function() {
  // Define BASE constant globally
  const BASE = "<?php echo BASE; ?>";
  console.log('🔧 BASE constant defined:', BASE);
  
  // Ensure API_BASE_PATH is set
  if (!window.API_BASE_PATH) {
    const path = window.location.pathname;
    window.API_BASE_PATH = (path.includes('/marketplace/') ? '/marketplace' : '') + '/api';
    console.log('🔧 [Questions] API_BASE_PATH:', window.API_BASE_PATH);
  }
  
  console.log('🚀 SuperAdmin Questions polling initialization started');
  
  if (typeof startPolling !== 'undefined') {
    console.log('✅ Starting polling for questions');
    
    try {
      startPolling({
        // Monitor for any new activity that might affect questions
        listings: (newListings) => {
          if (newListings.length > 0) {
            console.log('📋 New listings detected, questions page may need refresh');
          }
        }
      });
      
      console.log('✅ Polling started successfully for SuperAdmin Questions');
    } catch (error) {
      console.error('❌ Error starting polling:', error);
    }
  } else {
    console.warn('⚠️ startPolling function not found - polling.js may not be loaded');
  }
});
</script>