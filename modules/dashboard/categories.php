<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/validation_helper.php';
require_once __DIR__ . '/../../includes/pagination_helper.php';
$pdo = db();

// Get stats for dashboard
$totalCategories = $pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();
$totalLabels = $pdo->query("SELECT COUNT(*) FROM labels")->fetchColumn();
$recentCategories = $pdo->query("SELECT COUNT(*) FROM categories WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
$recentLabels = $pdo->query("SELECT COUNT(*) FROM labels WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();

// Get validation errors
$validationErrors = FormValidator::getStoredErrors();

// Get pagination parameters
$page = getCurrentPage('pg');
$perPage = 10;

// Setup search conditions
$search = $_GET['search'] ?? '';
$conditions = [];

if ($search) {
    $conditions['name'] = ['like' => $search];
}

// Fetch categories with pagination
$categoriesResult = getPaginationData($pdo, 'categories', $conditions, $page, $perPage, 'created_at DESC');
$categories = $categoriesResult['data'];
$categoriesPagination = $categoriesResult['pagination'];

// Fetch labels with pagination (separate pagination)
$labelPage = getCurrentPage('label_pg');
$labelsResult = getPaginationData($pdo, 'labels', $conditions, $labelPage, $perPage, 'created_at DESC');
$labels = $labelsResult['data'];
$labelsPagination = $labelsResult['pagination'];

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Categories & Labels Management</title>
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
        .stat-card {
            transition: all 0.3s ease;
        }
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        .category-card {
            transition: all 0.2s ease;
        }
        .category-card:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px -2px rgba(0, 0, 0, 0.1);
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

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Header Section -->
    <div class="mb-8">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Categories & Labels</h1>
                <p class="text-gray-600 mt-1">Organize and manage your listing categories and labels</p>
            </div>
            <div class="flex items-center gap-3">
                <button onclick="openAddLabelModal()" class="bg-gradient-to-r from-green-600 to-emerald-600 hover:from-green-700 hover:to-emerald-700 text-white px-4 py-2 rounded-xl text-sm font-medium transition-all shadow-lg flex items-center gap-2">
                    <i class="fas fa-tag"></i> Add Label
                </button>
                <button onclick="openAddCategoryModal()" class="bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white px-4 py-2 rounded-xl text-sm font-medium transition-all shadow-lg flex items-center gap-2">
                    <i class="fas fa-plus"></i> Add Category
                </button>
            </div>
        </div>
        
        <!-- Stats Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="stat-card bg-white rounded-2xl shadow-sm p-6 border border-gray-200">
                <div class="flex items-center">
                    <div class="rounded-full bg-blue-100 p-3 mr-4">
                        <i class="fas fa-folder text-blue-600 text-xl"></i>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-600">Total Categories</p>
                        <p class="text-2xl font-bold text-gray-900"><?= $totalCategories ?></p>
                    </div>
                </div>
            </div>
            
            <div class="stat-card bg-white rounded-2xl shadow-sm p-6 border border-gray-200">
                <div class="flex items-center">
                    <div class="rounded-full bg-green-100 p-3 mr-4">
                        <i class="fas fa-tags text-green-600 text-xl"></i>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-600">Total Labels</p>
                        <p class="text-2xl font-bold text-gray-900"><?= $totalLabels ?></p>
                    </div>
                </div>
            </div>
            
            <div class="stat-card bg-white rounded-2xl shadow-sm p-6 border border-gray-200">
                <div class="flex items-center">
                    <div class="rounded-full bg-purple-100 p-3 mr-4">
                        <i class="fas fa-plus-circle text-purple-600 text-xl"></i>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-600">New Categories</p>
                        <p class="text-2xl font-bold text-gray-900"><?= $recentCategories ?></p>
                        <p class="text-xs text-gray-500">This week</p>
                    </div>
                </div>
            </div>
            
            <div class="stat-card bg-white rounded-2xl shadow-sm p-6 border border-gray-200">
                <div class="flex items-center">
                    <div class="rounded-full bg-orange-100 p-3 mr-4">
                        <i class="fas fa-star text-orange-600 text-xl"></i>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-600">New Labels</p>
                        <p class="text-2xl font-bold text-gray-900"><?= $recentLabels ?></p>
                        <p class="text-xs text-gray-500">This week</p>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <!-- Search Form -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 mb-8">
        <form method="GET" class="flex flex-col sm:flex-row gap-4 items-end">
            <input type="hidden" name="p" value="dashboard">
            <input type="hidden" name="page" value="categories">
            
            <div class="flex-1">
                <label class="block text-sm font-semibold text-gray-700 mb-3">
                    <i class="fas fa-search mr-2 text-blue-500"></i>Search Categories & Labels
                </label>
                <div class="relative">
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
                           placeholder="Search by name..." 
                           class="w-full pl-12 pr-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-4 focus:ring-blue-100 focus:border-blue-500 transition-all">
                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                        <i class="fas fa-search text-gray-400"></i>
                    </div>
                </div>
            </div>
            
            <div class="flex gap-3">
                <button type="submit" class="px-6 py-3 bg-gradient-to-r from-blue-600 to-blue-700 text-white rounded-xl hover:from-blue-700 hover:to-blue-800 transition-all font-medium shadow-lg flex items-center">
                    <i class="fas fa-search mr-2"></i>Search
                </button>
                <a href="?p=dashboard&page=categories" class="px-6 py-3 bg-gradient-to-r from-gray-500 to-gray-600 text-white rounded-xl hover:from-gray-600 hover:to-gray-700 transition-all font-medium shadow-lg flex items-center">
                    <i class="fas fa-times mr-2"></i>Clear
                </a>
            </div>
        </form>
    </div>


    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">

        <!-- Categories Section -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="bg-gradient-to-r from-blue-50 to-indigo-50 p-6 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-folder text-blue-600"></i>
                        </div>
                        <div>
                            <h3 class="text-xl font-bold text-gray-900">Categories</h3>
                            <p class="text-sm text-gray-600">Organize your listings</p>
                        </div>
                    </div>
                    <span class="bg-blue-100 text-blue-800 text-xs font-medium px-3 py-1 rounded-full">
                        <?= count($categories) ?> items
                    </span>
                </div>
            </div>

            <div class="p-6">
                <?php if (count($categories)): ?>
                    <div class="space-y-3">
                        <?php foreach ($categories as $cat): ?>
                            <div class="category-card flex justify-between items-center bg-gray-50 hover:bg-gray-100 p-4 rounded-xl border border-gray-200 transition-all">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center">
                                        <i class="fas fa-folder text-blue-600 text-sm"></i>
                                    </div>
                                    <div>
                                        <p class="font-semibold text-gray-900"><?= htmlspecialchars($cat['name']) ?></p>
                                        <span class="text-xs text-gray-500">
                                            <i class="fas fa-calendar mr-1"></i>
                                            Created <?= date('M j, Y', strtotime($cat['created_at'])) ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="flex gap-2">
                                    <button onclick="openEditCategoryModal(<?= $cat['id'] ?>, '<?= htmlspecialchars($cat['name'], ENT_QUOTES) ?>')" 
                                            class="p-2 text-blue-600 hover:bg-blue-100 rounded-lg transition-colors">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button onclick="deleteCategory(<?= $cat['id'] ?>)" 
                                            class="p-2 text-red-600 hover:bg-red-100 rounded-lg transition-colors">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-12">
                        <div class="w-20 h-20 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-6">
                            <i class="fas fa-folder-open text-blue-500 text-2xl"></i>
                        </div>
                        <h3 class="text-xl font-bold text-gray-900 mb-3">No Categories Found</h3>
                        <p class="text-gray-600 mb-6 max-w-sm mx-auto">Create your first category to organize listings and make them easier to find.</p>
                        <button onclick="openAddCategoryModal()" class="inline-flex items-center px-6 py-3 bg-gradient-to-r from-blue-600 to-indigo-600 text-white rounded-xl hover:from-blue-700 hover:to-indigo-700 transition-all font-semibold shadow-lg">
                            <i class="fas fa-plus mr-2"></i>
                            Create Category
                        </button>
                    </div>
                <?php endif; ?>

                <!-- Categories Pagination -->
                <?php if (count($categories) > 0): ?>
                    <div class="mt-6 pt-6 border-t border-gray-200">
                        <?php 
                        $extraParams = ['p' => 'dashboard', 'page' => 'categories'];
                        if ($search) $extraParams['search'] = $search;
                        echo renderPagination($categoriesPagination, url('index.php'), $extraParams, 'pg'); 
                        ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Labels Section -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="bg-gradient-to-r from-green-50 to-emerald-50 p-6 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-tags text-green-600"></i>
                        </div>
                        <div>
                            <h3 class="text-xl font-bold text-gray-900">Labels</h3>
                            <p class="text-sm text-gray-600">Tag and highlight listings</p>
                        </div>
                    </div>
                    <span class="bg-green-100 text-green-800 text-xs font-medium px-3 py-1 rounded-full">
                        <?= count($labels) ?> items
                    </span>
                </div>
            </div>

            <div class="p-6">
                <?php if (count($labels)): ?>
                    <div class="space-y-3 mb-6">
                        <?php foreach ($labels as $lbl): ?>
                            <div class="category-card flex justify-between items-center bg-gray-50 hover:bg-gray-100 p-4 rounded-xl border border-gray-200 transition-all">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 bg-green-100 rounded-lg flex items-center justify-center">
                                        <i class="fas fa-tag text-green-600 text-sm"></i>
                                    </div>
                                    <div>
                                        <p class="font-semibold text-gray-900"><?= htmlspecialchars($lbl['name']) ?></p>
                                        <span class="text-xs text-gray-500">
                                            <i class="fas fa-calendar mr-1"></i>
                                            Created <?= date('M j, Y', strtotime($lbl['created_at'])) ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="flex gap-2">
                                    <button onclick="openEditLabelModal(<?= $lbl['id'] ?>, '<?= htmlspecialchars($lbl['name'], ENT_QUOTES) ?>')" 
                                            class="p-2 text-blue-600 hover:bg-blue-100 rounded-lg transition-colors">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button onclick="deleteLabel(<?= $lbl['id'] ?>)" 
                                            class="p-2 text-red-600 hover:bg-red-100 rounded-lg transition-colors">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-12 mb-6">
                        <div class="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-6">
                            <i class="fas fa-tags text-green-500 text-2xl"></i>
                        </div>
                        <h3 class="text-xl font-bold text-gray-900 mb-3">No Labels Found</h3>
                        <p class="text-gray-600 mb-6 max-w-sm mx-auto">Create labels to tag and highlight special features of your listings.</p>
                    </div>
                <?php endif; ?>



                <!-- Labels Pagination -->
                <?php if (count($labels) > 0): ?>
                    <div class="mt-6 pt-6 border-t border-gray-200">
                        <?php 
                        $extraParams = ['p' => 'dashboard', 'page' => 'categories'];
                        if ($search) $extraParams['search'] = $search;
                        echo renderPagination($labelsPagination, url('index.php'), $extraParams, 'label_pg'); 
                        ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ================     ADD CATEGORY MODAL
======================= -->
<div id="addCategoryModal" class="hidden fixed inset-0 bg-black bg-opacity-40 flex justify-center items-center z-50">
  <div class="bg-white rounded-lg p-6 w-96 shadow-xl">
    <h3 class="text-lg font-semibold mb-4">Add Category</h3>
    <form method="POST" action="index.php?p=dashboard&page=save_category">
      <?php csrfTokenField(); ?>
      <input type="text" name="name" placeholder="Category name" required
             class="<?= inputErrorClass('name', $validationErrors, 'w-full border rounded-md px-3 py-2 mb-4 focus:ring-2 focus:ring-blue-500') ?>"
             value="<?= oldValue('name') ?>">
      <?php displayFieldError('name', $validationErrors); ?>
      <div class="flex justify-end gap-3">
        <button type="button" onclick="closeModal('addCategoryModal')" class="px-4 py-2 bg-gray-300 rounded-md">Cancel</button>
        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md">Save</button>
      </div>
    </form>
  </div>
</div>

<!-- ================     EDIT CATEGORY MODAL
======================= -->
<div id="editCategoryModal" class="hidden fixed inset-0 bg-black bg-opacity-40 flex justify-center items-center z-50">
  <div class="bg-white rounded-lg p-6 w-96 shadow-xl">
    <h3 class="text-lg font-semibold mb-4">Edit Category</h3>
    <form method="POST" action="index.php?p=dashboard&page=update_category">
      <?php csrfTokenField(); ?>
      <input type="hidden" name="id" id="edit_cat_id">
      <input type="text" name="name" id="edit_cat_name" required
             class="w-full border rounded-md px-3 py-2 mb-4 focus:ring-2 focus:ring-blue-500">
      <div class="flex justify-end gap-3">
        <button type="button" onclick="closeModal('editCategoryModal')" class="px-4 py-2 bg-gray-300 rounded-md">Cancel</button>
        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md">Update</button>
      </div>
    </form>
  </div>
</div>

<div id="addLabelModal" class="hidden fixed inset-0 bg-black bg-opacity-40 flex justify-center items-center z-50">
  <div class="bg-white rounded-lg p-6 w-96 shadow-xl">
    <h3 class="text-lg font-semibold mb-4">Add Label</h3>
    <form method="POST" action="index.php?p=dashboard&page=save_label">
      <?php csrfTokenField(); ?>
      <input type="text" name="name" placeholder="Label name" required
             class="w-full border rounded-md px-3 py-2 mb-3 focus:ring-2 focus:ring-green-500">
      <!-- <label class="text-sm text-gray-600 mb-1 block">Label Color</label>
      <input type="color" name="color" value="#6b7280" class="w-full border rounded-md mb-4 cursor-pointer"> -->
      <div class="flex justify-end gap-3">
        <button type="button" onclick="closeModal('addLabelModal')" class="px-4 py-2 bg-gray-300 rounded-md">Cancel</button>
        <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-md">Save</button>
      </div>
    </form>
  </div>
</div>

<!-- ================     EDIT LABEL MODAL
======================= -->
<div id="editLabelModal" class="hidden fixed inset-0 bg-black bg-opacity-40 flex justify-center items-center z-50">
  <div class="bg-white rounded-lg p-6 w-96 shadow-xl">
    <h3 class="text-lg font-semibold mb-4">Edit Label</h3>
    <form method="POST"  action="index.php?p=dashboard&page=save_label">
      <?php csrfTokenField(); ?>
      <input type="hidden" name="id" id="edit_lbl_id">
      <input type="text" name="name" id="edit_lbl_name" required
             class="w-full border rounded-md px-3 py-2 mb-3 focus:ring-2 focus:ring-green-500">
      <!-- <label class="text-sm text-gray-600 mb-1 block">Label Color</label>
      <input type="color" name="color" id="edit_lbl_color" class="w-full border rounded-md mb-4 cursor-pointer"> -->
      <div class="flex justify-end gap-3">
        <button type="button" onclick="closeModal('editLabelModal')" class="px-4 py-2 bg-gray-300 rounded-md">Cancel</button>
        <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-md">Update</button>
      </div>
    </form>
  </div>
</div>

<script>
function openAddCategoryModal() {
  document.getElementById('addCategoryModal').classList.remove('hidden');
}
function openEditCategoryModal(id, name) {
  document.getElementById('edit_cat_id').value = id;
  document.getElementById('edit_cat_name').value = name;
  document.getElementById('editCategoryModal').classList.remove('hidden');
}

function openAddLabelModal() {
  document.getElementById('addLabelModal').classList.remove('hidden');
}
function openEditLabelModal(id, name, color) {
  document.getElementById('edit_lbl_id').value = id;
  document.getElementById('edit_lbl_name').value = name;
  // document.getElementById('edit_lbl_color').value = color;
  document.getElementById('editLabelModal').classList.remove('hidden');
}

function closeModal(id) {
  document.getElementById(id).classList.add('hidden');
}

function deleteCategory(id) {
  if (confirm("Are you sure you want to delete this category?")) {
    window.location.href = "index.php?p=dashboard&page=delete_category&id=" + id;
  }
}
function deleteLabel(id) {
  if (confirm("Are you sure you want to delete this label?")) {
    window.location.href = "index.php?p=dashboard&page=delete_label&id=" + id;
  }
}
</script>

</body>
</html>
