<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/validation_helper.php';
require_once __DIR__ . '/../../includes/flash_helper.php';
require_once __DIR__ . '/../../includes/log_helper.php';
$pdo = db();

/* ---------------- ADD ---------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'add') {
  // CSRF validation
  if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
    exit('error: Invalid request. Please try again.');
  }
  
  // Create validator with form data
  $validator = new FormValidator($_POST);
  
  // Validate all fields
  $validator
    ->required('name', 'Full name is required')
    ->name('name', 'Name can only contain letters, spaces, hyphens and apostrophes')
    ->required('email', 'Email address is required')
    ->email('email', 'Please enter a valid email address')
    ->required('role', 'Role is required')
    ->required('status', 'Status is required');
  
  // Validate role is valid
  $validRoles = ['user', 'admin', 'superadmin'];
  if (!in_array($_POST['role'] ?? '', $validRoles)) {
    $validator->custom('role', function() { return false; }, 'Please select a valid role');
  }
  
  // Validate status is valid
  $validStatuses = ['active', 'blocked', 'pending'];
  if (!in_array($_POST['status'] ?? '', $validStatuses)) {
    $validator->custom('status', function() { return false; }, 'Please select a valid status');
  }
  
  // Check if email already exists
  if ($validator->passes()) {
    $email = trim($_POST['email']);
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
      $validator->custom('email', function() { return false; }, 'This email address is already registered');
    }
  }
  
  // If validation fails, return error
  if ($validator->fails()) {
    $errors = $validator->getErrors();
    $firstError = reset($errors);
    exit('error: ' . $firstError);
  }
  
  // Validation passed, create user
  $name = trim($_POST['name']);
  $email = trim($_POST['email']);
  $role = $_POST['role'];
  $status = $_POST['status'];
  $defaultPassword = '123456';
  
  try {
    // Check if email_verified column exists
    $columnExists = false;
    try {
      $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'email_verified'");
      $columnExists = $stmt->rowCount() > 0;
    } catch (Exception $e) {
      $columnExists = false;
    }
    
    // Insert user with requires_password_change flag and email_verified set to 1 for admin-created users
    if ($columnExists) {
      $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role, status, requires_password_change, email_verified) VALUES (?, ?, ?, ?, ?, ?, ?)");
      $stmt->execute([
        $name,
        $email,
        password_hash($defaultPassword, PASSWORD_DEFAULT),
        $role,
        $status,
        1,  // Set requires_password_change flag
        1   // Set email_verified to 1 for admin-created users
      ]);
    } else {
      // Fallback for systems without email_verified column
      $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role, status, requires_password_change) VALUES (?, ?, ?, ?, ?, ?)");
      $stmt->execute([
        $name,
        $email,
        password_hash($defaultPassword, PASSWORD_DEFAULT),
        $role,
        $status,
        1  // Set requires_password_change flag
      ]);
    }
    
    $userId = $pdo->lastInsertId();
    
    // Log user creation
    log_action(
        "User Created",
        "Admin created new user: {$name} ({$email}) with role: {$role}",
        "admin",
        current_user()['id'] ?? null,
        current_user()['role'] ?? 'admin'
    );
    
    // Try to send credentials email (optional - won't fail if email system not configured)
    $emailSent = false;
    try {
        if (file_exists(__DIR__ . '/../../vendor/autoload.php')) {
            require_once __DIR__ . '/../../includes/email_helper.php';
            if (function_exists('sendNewUserCredentialsEmail')) {
                $emailSent = sendNewUserCredentialsEmail($email, $name, $defaultPassword, $role);
            }
        }
    } catch (Exception $e) {
        error_log("⚠️ Email sending failed: " . $e->getMessage());
        $emailSent = false;
    }
    
    if ($emailSent) {
        error_log("✅ User created successfully (ID: $userId) and credentials email sent to: $email");
        exit('success');
    } else {
        error_log("⚠️ User created (ID: $userId) but email system not configured or failed for: $email");
        exit('success_no_email');
    }
  } catch (PDOException $e) {
    error_log("❌ User creation failed: " . $e->getMessage());
    exit('error: Failed to create user. Please try again.');
  }
}

/* ---------------- UPDATE ---------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'update') {
  // CSRF validation
  if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
    exit('error: Invalid request. Please try again.');
  }
  
  $id = intval($_POST['id'] ?? 0);
  if ($id <= 0) {
    exit('error: Invalid user ID');
  }
  
  // Create validator with form data
  $validator = new FormValidator($_POST);
  
  // Validate all fields
  $validator
    ->required('name', 'Full name is required')
    ->name('name', 'Name can only contain letters, spaces, hyphens and apostrophes')
    ->required('email', 'Email address is required')
    ->email('email', 'Please enter a valid email address')
    ->required('role', 'Role is required')
    ->required('status', 'Status is required');
  
  // Validate role is valid
  $validRoles = ['user', 'admin', 'superadmin'];
  if (!in_array($_POST['role'] ?? '', $validRoles)) {
    $validator->custom('role', function() { return false; }, 'Please select a valid role');
  }
  
  // Validate status is valid
  $validStatuses = ['active', 'blocked', 'pending'];
  if (!in_array($_POST['status'] ?? '', $validStatuses)) {
    $validator->custom('status', function() { return false; }, 'Please select a valid status');
  }
  
  // Check if email already exists for another user
  if ($validator->passes()) {
    $email = trim($_POST['email']);
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $stmt->execute([$email, $id]);
    if ($stmt->fetch()) {
      $validator->custom('email', function() { return false; }, 'This email address is already taken by another user');
    }
  }
  
  // If validation fails, return error
  if ($validator->fails()) {
    $errors = $validator->getErrors();
    $firstError = reset($errors);
    exit('error: ' . $firstError);
  }
  
  // Validation passed, update user
  $name = trim($_POST['name']);
  $email = trim($_POST['email']);
  $role = $_POST['role'];
  $status = $_POST['status'];
  
  try {
    $stmt = $pdo->prepare("UPDATE users SET name=?, email=?, role=?, status=? WHERE id=?");
    $stmt->execute([$name, $email, $role, $status, $id]);
    exit('success');
  } catch (PDOException $e) {
    exit('error: Failed to update user. Please try again.');
  }
}

/* ---------------- DELETE ---------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'delete') {
  // CSRF validation
  if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
    exit('error: Invalid request. Please try again.');
  }
  $id = intval($_POST['id'] ?? 0);
  
  if ($id <= 0) {
    exit('error: Invalid user ID');
  }
  
  $stmt = $pdo->prepare("DELETE FROM users WHERE id=?");
  $stmt->execute([$id]);
  exit('success');
}


/* ---------------- FETCH USERS WITH PAGINATION ---------------- */
require_once __DIR__ . '/../../includes/pagination_helper.php';

// Get pagination parameters
$page = getCurrentPage('pg');
$perPage = 20;

// Setup search and filter conditions
$search = isset($_GET['search']) && is_string($_GET['search']) ? trim($_GET['search']) : '';
$role = isset($_GET['role']) && is_string($_GET['role']) ? trim($_GET['role']) : '';

$conditions = [];
if (!empty($search)) {
    $conditions['name'] = ['like' => $search];
}
if (!empty($role)) {
    $conditions['role'] = $role;
}

// Get paginated data
$result = getPaginationData($pdo, 'users', $conditions, $page, $perPage, 'id DESC');
$users = $result['data'];
$pagination = $result['pagination'];

// Get stats for all users (users table doesn't have status column)
$statsQuery = "
    SELECT 
        COUNT(*) as total,
        COUNT(CASE WHEN role IN ('admin', 'superadmin') THEN 1 END) as admins,
        COUNT(CASE WHEN role = 'user' THEN 1 END) as users_count
    FROM users
";
$statsStmt = $pdo->query($statsQuery);
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

$total = $stats['total'];
$admins = $stats['admins'];
$users_count = $stats['users_count'];
// Since users table doesn't have status, we'll treat all as active
$active = $total;
$blocked = 0;
$pending = 0;

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Manage Members</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" crossorigin="anonymous">
<style>
  /* Enhanced Pagination Styles */
  .pagination-wrapper .pagination {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
  }
  
  .pagination-wrapper .pagination a,
  .pagination-wrapper .pagination span {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 2.5rem;
    height: 2.5rem;
    padding: 0.5rem;
    text-decoration: none;
    font-weight: 500;
    border-radius: 0.5rem;
    transition: all 0.2s ease-in-out;
    font-size: 0.875rem;
  }
  
  .pagination-wrapper .pagination a {
    background: white;
    color: #374151;
    border: 1px solid #d1d5db;
    box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
  }
  
  .pagination-wrapper .pagination a:hover {
    background: linear-gradient(to right, #0891b2, #0e7490);
    color: white;
    border-color: #0891b2;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    transform: translateY(-1px);
  }
  
  .pagination-wrapper .pagination .current {
    background: linear-gradient(to right, #0891b2, #0e7490);
    color: white;
    border: 1px solid #0891b2;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    font-weight: 600;
  }
  
  .pagination-wrapper .pagination .disabled {
    background: #f9fafb;
    color: #9ca3af;
    border: 1px solid #e5e7eb;
    cursor: not-allowed;
  }
  
  /* Smooth animations */
  .hover-lift:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
  }
  
  /* Custom scrollbar for table */
  .overflow-x-auto::-webkit-scrollbar {
    height: 8px;
  }
  
  .overflow-x-auto::-webkit-scrollbar-track {
    background: #f1f5f9;
    border-radius: 4px;
  }
  
  .overflow-x-auto::-webkit-scrollbar-thumb {
    background: linear-gradient(to right, #0891b2, #0e7490);
    border-radius: 4px;
  }
  
  .overflow-x-auto::-webkit-scrollbar-thumb:hover {
    background: linear-gradient(to right, #0e7490, #155e75);
  }
  
  /* Mobile responsive enhancements */
  @media (max-width: 768px) {
    .pagination-wrapper .pagination {
      justify-content: center;
      gap: 0.25rem;
    }
    
    .pagination-wrapper .pagination a,
    .pagination-wrapper .pagination span {
      min-width: 2rem;
      height: 2rem;
      font-size: 0.75rem;
      padding: 0.25rem;
    }
    
    /* Hide some table columns on mobile */
    .mobile-hide {
      display: none;
    }
    
    /* Stack form elements on mobile */
    .mobile-stack {
      flex-direction: column;
    }
  }
  
  /* Loading animation */
  @keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
  }
  
  .loading {
    animation: pulse 1.5s ease-in-out infinite;
  }
  
  /* Notification animations */
  .notification {
    min-width: 300px;
    max-width: 400px;
    font-weight: 500;
    z-index: 9999;
  }
  
  .notification i {
    font-size: 1.1em;
  }
  
  /* Form reset animations */
  .form-success {
    animation: successPulse 0.6s ease-in-out;
  }
  
  @keyframes successPulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.02); }
    100% { transform: scale(1); }
  }
  
  /* Field error styling */
  .field-error {
    animation: errorSlideIn 0.3s ease-out;
  }
  
  @keyframes errorSlideIn {
    0% { 
      opacity: 0; 
      transform: translateY(-10px); 
    }
    100% { 
      opacity: 1; 
      transform: translateY(0); 
    }
  }
  
  /* Error field styling */
  .border-red-500 {
    border-color: #ef4444 !important;
    box-shadow: 0 0 0 1px #ef4444;
  }
  
  .focus\:ring-red-500:focus {
    ring-color: #ef4444 !important;
  }
</style>
</head>
<body class="bg-gray-50 min-h-screen text-gray-800">
<main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

<!-- Header Section -->
<div class="mb-8">
  <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
    <div>
      <h1 class="text-3xl font-bold text-gray-900 flex items-center gap-3">
        <div class="rounded-full bg-gradient-to-r from-cyan-500 to-blue-600 p-3 shadow-lg">
          <i class="fa-solid fa-users-gear text-white text-xl"></i>
        </div>
        Member Management
      </h1>
      <p class="text-gray-600 mt-2 text-lg">Manage all user accounts and permissions</p>
    </div>
    <div class="flex items-center gap-3">
      <div class="bg-white px-4 py-2 rounded-lg shadow-sm border border-gray-200">
        <span class="text-sm text-gray-500">Total Members:</span>
        <span class="font-bold text-cyan-600 ml-1"><?= $total ?></span>
      </div>
    </div>
  </div>
</div>

<!-- Stats Cards -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
  <div class="bg-gradient-to-br from-cyan-50 to-cyan-100 rounded-xl shadow-lg p-6 border border-cyan-200 hover:shadow-xl transition-all duration-300">
    <div class="flex items-center">
      <div class="rounded-full bg-gradient-to-r from-cyan-500 to-cyan-600 p-3 mr-4 shadow-md">
        <i class="fa-solid fa-users text-white text-lg"></i>
      </div>
      <div>
        <p class="text-sm text-cyan-700 font-medium">Total Members</p>
        <p class="text-3xl font-bold text-cyan-900"><?= $total ?></p>
      </div>
    </div>
  </div>
  
  <div class="bg-gradient-to-br from-green-50 to-green-100 rounded-xl shadow-lg p-6 border border-green-200 hover:shadow-xl transition-all duration-300">
    <div class="flex items-center">
      <div class="rounded-full bg-gradient-to-r from-green-500 to-green-600 p-3 mr-4 shadow-md">
        <i class="fa-solid fa-user-check text-white text-lg"></i>
      </div>
      <div>
        <p class="text-sm text-green-700 font-medium">Active Members</p>
        <p class="text-3xl font-bold text-green-900"><?= $active ?></p>
      </div>
    </div>
  </div>
  
  <div class="bg-gradient-to-br from-purple-50 to-purple-100 rounded-xl shadow-lg p-6 border border-purple-200 hover:shadow-xl transition-all duration-300">
    <div class="flex items-center">
      <div class="rounded-full bg-gradient-to-r from-purple-500 to-purple-600 p-3 mr-4 shadow-md">
        <i class="fa-solid fa-user-shield text-white text-lg"></i>
      </div>
      <div>
        <p class="text-sm text-purple-700 font-medium">Admin Users</p>
        <p class="text-3xl font-bold text-purple-900"><?= $admins ?></p>
      </div>
    </div>
  </div>
  
  <div class="bg-gradient-to-br from-orange-50 to-orange-100 rounded-xl shadow-lg p-6 border border-orange-200 hover:shadow-xl transition-all duration-300">
    <div class="flex items-center">
      <div class="rounded-full bg-gradient-to-r from-orange-500 to-orange-600 p-3 mr-4 shadow-md">
        <i class="fa-solid fa-user text-white text-lg"></i>
      </div>
      <div>
        <p class="text-sm text-orange-700 font-medium">Regular Users</p>
        <p class="text-3xl font-bold text-orange-900"><?= $users_count ?></p>
      </div>
    </div>
  </div>
</div>

<!-- Add Member Section -->
<div class="bg-white shadow-lg rounded-2xl p-8 mb-8 border border-gray-100 hover-lift transition-all duration-300">
  <div class="flex items-center justify-between mb-6">
    <h2 class="text-xl font-semibold text-gray-800 flex items-center gap-3">
      <div class="rounded-full bg-gradient-to-r from-cyan-500 to-cyan-600 p-3 shadow-md">
        <i class="fa-solid fa-user-plus text-white text-lg"></i>
      </div>
      Add New Member
    </h2>
    <div class="text-sm text-gray-600 bg-yellow-50 border border-yellow-200 px-4 py-2 rounded-lg">
      <i class="fa-solid fa-key mr-2 text-yellow-600"></i>
      Default password: <span class="font-mono bg-yellow-100 px-2 py-1 rounded font-semibold">123456</span>
    </div>
  </div>

  <form id="addForm" class="grid md:grid-cols-2 lg:grid-cols-4 gap-6">
    <input type="hidden" name="action" value="add">
    <?php csrfTokenField(); ?>
    
    <div class="lg:col-span-2">
      <label class="block text-sm font-medium text-gray-700 mb-2">
        Full Name <span class="text-red-500">*</span>
      </label>
      <div class="relative">
        <input type="text" name="name" required 
               class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500 transition-colors"
               placeholder="Enter full name"
               minlength="2"
               maxlength="100">
        <i class="fa-solid fa-user text-gray-400 absolute right-3 top-3.5"></i>
      </div>
    </div>
    
    <div class="lg:col-span-2">
      <label class="block text-sm font-medium text-gray-700 mb-2">
        Email Address <span class="text-red-500">*</span>
      </label>
      <div class="relative">
        <input type="email" name="email" required 
               class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500 transition-colors"
               placeholder="Enter email address"
               maxlength="255">
        <i class="fa-solid fa-envelope text-gray-400 absolute right-3 top-3.5"></i>
      </div>
    </div>
    
    <div>
      <label class="block text-sm font-medium text-gray-700 mb-2">
        Role <span class="text-red-500">*</span>
      </label>
      <div class="relative">
        <select name="role" required class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500 appearance-none transition-colors">
          <option value="">Select Role</option>
          <option value="user">User</option>
          <option value="admin">Admin</option>
          <option value="superadmin">Super Admin</option>
        </select>
        <i class="fa-solid fa-chevron-down text-gray-400 absolute right-3 top-3.5 pointer-events-none"></i>
      </div>
    </div>
    
    <div>
      <label class="block text-sm font-medium text-gray-700 mb-2">
        Status <span class="text-red-500">*</span>
      </label>
      <div class="relative">
        <select name="status" required class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500 appearance-none transition-colors">
          <option value="">Select Status</option>
          <option value="active" selected>Active</option>
          <option value="blocked">Blocked</option>
          <option value="pending">Pending</option>
        </select>
        <i class="fa-solid fa-chevron-down text-gray-400 absolute right-3 top-3.5 pointer-events-none"></i>
      </div>
    </div>
    
    <div class="md:col-span-2 lg:col-span-4 flex justify-end mt-6">
      <button type="submit" class="bg-gradient-to-r from-cyan-600 to-cyan-700 hover:from-cyan-700 hover:to-cyan-800 text-white px-8 py-3 rounded-lg font-semibold flex items-center gap-2 transition-all duration-200 shadow-md hover:shadow-lg transform hover:-translate-y-0.5" data-original-text='<i class="fa-solid fa-user-plus"></i> Add New Member'>
        <i class="fa-solid fa-user-plus"></i>
        Add New Member
      </button>
    </div>
  </form>
</div>


<!-- Search and Filter -->
<div class="bg-white shadow-lg rounded-2xl p-6 mb-6 border border-gray-100">
  <div class="flex items-center justify-between mb-4">
    <h3 class="text-lg font-semibold text-gray-800 flex items-center gap-2">
      <i class="fa-solid fa-filter text-cyan-600"></i>
      Search & Filter Members
    </h3>
    <?php if ($search || $role): ?>
      <span class="bg-cyan-100 text-cyan-800 px-3 py-1 rounded-full text-sm font-medium">
        <i class="fa-solid fa-filter mr-1"></i>
        Filters Active
      </span>
    <?php endif; ?>
  </div>
  
  <form method="GET" class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-4 items-end">
    <input type="hidden" name="p" value="dashboard">
    <input type="hidden" name="page" value="addAdminSuperAdmin">
    
    <div class="lg:col-span-2">
      <label class="block text-sm font-medium text-gray-700 mb-2">
        <i class="fa-solid fa-magnifying-glass mr-1 text-cyan-600"></i>Search Members
      </label>
      <div class="relative">
        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
               placeholder="Search by name or email..." 
               class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500 transition-all duration-200">
        <i class="fa-solid fa-magnifying-glass text-gray-400 absolute left-3 top-3.5"></i>
      </div>
    </div>
    
    <div>
      <label class="block text-sm font-medium text-gray-700 mb-2">
        <i class="fa-solid fa-user-tag mr-1 text-cyan-600"></i>Role Filter
      </label>
      <div class="relative">
        <select name="role" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500 appearance-none transition-all duration-200">
          <option value="">All Roles</option>
          <option value="user" <?= $role === 'user' ? 'selected' : '' ?>>User</option>
          <option value="admin" <?= $role === 'admin' ? 'selected' : '' ?>>Admin</option>
          <option value="superadmin" <?= $role === 'superadmin' ? 'selected' : '' ?>>Super Admin</option>
        </select>
        <i class="fa-solid fa-chevron-down text-gray-400 absolute right-3 top-3.5 pointer-events-none"></i>
      </div>
    </div>
    
    <div class="flex gap-2">
      <button type="submit" class="flex-1 px-6 py-3 bg-gradient-to-r from-cyan-600 to-cyan-700 text-white rounded-lg hover:from-cyan-700 hover:to-cyan-800 transition-all duration-200 flex items-center justify-center shadow-md hover:shadow-lg">
        <i class="fa-solid fa-search mr-2"></i>Search
      </button>
      <a href="?p=dashboard&page=addAdminSuperAdmin" class="px-4 py-3 bg-gray-100 text-gray-600 rounded-lg hover:bg-gray-200 transition-all duration-200 flex items-center justify-center">
        <i class="fa-solid fa-times"></i>
      </a>
    </div>
  </form>
</div>


<!-- Members Table -->
<div class="bg-white shadow-lg rounded-2xl p-8 mb-10 border border-gray-100">
  <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
    <h2 class="text-xl font-semibold flex items-center gap-3">
      <div class="rounded-full bg-gradient-to-r from-cyan-500 to-cyan-600 p-2 shadow-md">
        <i class="fa-solid fa-users text-white text-lg"></i>
      </div>
      Members List (<?= $pagination['total_items'] ?>)
    </h2>
    <div class="flex items-center gap-4">
      <div class="text-sm text-gray-500 bg-gray-50 px-3 py-2 rounded-lg">
        Showing <span class="font-semibold text-gray-700"><?= $pagination['start_item'] ?>-<?= $pagination['end_item'] ?></span> of <span class="font-semibold text-gray-700"><?= $pagination['total_items'] ?></span> members
      </div>
      <div class="text-sm text-gray-500">
        Page <span class="font-semibold text-cyan-600"><?= $pagination['current_page'] ?></span> of <span class="font-semibold text-cyan-600"><?= $pagination['total_pages'] ?></span>
      </div>
    </div>
  </div>

  <div class="overflow-x-auto rounded-lg border border-gray-200 shadow-sm">
    <table class="w-full text-left" id="membersTable">
      <thead>
        <tr class="bg-gradient-to-r from-gray-50 to-gray-100 text-gray-700 text-sm border-b border-gray-200">
          <th class="py-4 px-6 font-semibold">#</th>
          <th class="py-4 px-6 font-semibold">
            <i class="fa-solid fa-user mr-2 text-cyan-600"></i>Member
          </th>
          <th class="py-4 px-6 font-semibold">
            <i class="fa-solid fa-user-tag mr-2 text-cyan-600"></i>Role
          </th>
          <th class="py-4 px-6 font-semibold">
            <i class="fa-solid fa-circle-dot mr-2 text-cyan-600"></i>Status
          </th>
          <th class="py-4 px-6 font-semibold text-right">
            <i class="fa-solid fa-cog mr-2 text-cyan-600"></i>Actions
          </th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-200">
        <?php if (empty($users)): ?>
          <tr>
            <td colspan="5" class="px-6 py-12 text-center">
              <div class="flex flex-col items-center justify-center text-gray-400">
                <i class="fa-solid fa-users text-5xl mb-4"></i>
                <p class="text-lg font-medium">No members found</p>
                <p class="mt-1">Get started by adding your first member</p>
              </div>
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($users as $i => $u): ?>
          <tr class="hover:bg-gradient-to-r hover:from-cyan-50 hover:to-blue-50 transition-all duration-200 border-b border-gray-100">
            <td class="py-4 px-6 font-medium text-gray-900">
              <div class="w-8 h-8 bg-gradient-to-r from-cyan-500 to-blue-600 rounded-full flex items-center justify-center text-white text-sm font-bold">
                <?= (($pagination['current_page'] - 1) * $pagination['per_page']) + $i + 1 ?>
              </div>
            </td>
            <td class="py-4 px-6">
              <div class="flex items-center gap-3">
                <div class="rounded-full bg-gradient-to-r from-cyan-100 to-blue-100 w-12 h-12 flex items-center justify-center shadow-sm">
                  <i class="fa-solid fa-user text-cyan-600 text-lg"></i>
                </div>
                <div>
                  <div class="font-semibold text-gray-900 text-base"><?= htmlspecialchars($u['name']) ?></div>
                  <div class="text-sm text-gray-500 flex items-center gap-1">
                    <i class="fa-solid fa-envelope text-xs"></i>
                    <?= htmlspecialchars($u['email']) ?>
                  </div>
                </div>
              </div>
            </td>
            <td class="py-4 px-6">
              <?php
              $roleData = [
                'user' => ['bg' => 'bg-gradient-to-r from-blue-100 to-blue-200', 'text' => 'text-blue-800', 'icon' => 'fa-user', 'label' => 'User'],
                'admin' => ['bg' => 'bg-gradient-to-r from-purple-100 to-purple-200', 'text' => 'text-purple-800', 'icon' => 'fa-user-shield', 'label' => 'Admin'], 
                'superadmin' => ['bg' => 'bg-gradient-to-r from-red-100 to-red-200', 'text' => 'text-red-800', 'icon' => 'fa-user-crown', 'label' => 'Super Admin']
              ];
              $role = $roleData[$u['role']] ?? ['bg' => 'bg-gray-100', 'text' => 'text-gray-800', 'icon' => 'fa-user', 'label' => ucfirst($u['role'])];
              ?>
              <span class="inline-flex items-center px-4 py-2 rounded-full text-sm font-semibold <?= $role['bg'] ?> <?= $role['text'] ?> shadow-sm">
                <i class="fa-solid <?= $role['icon'] ?> mr-2"></i>
                <?= $role['label'] ?>
              </span>
            </td>
            <td class="py-4 px-6">
              <?php
              $statusData = [
                'active' => ['bg' => 'bg-gradient-to-r from-green-100 to-green-200', 'text' => 'text-green-800', 'icon' => 'fa-circle-check', 'label' => 'Active'],
                'blocked' => ['bg' => 'bg-gradient-to-r from-red-100 to-red-200', 'text' => 'text-red-800', 'icon' => 'fa-circle-xmark', 'label' => 'Blocked'],
                'pending' => ['bg' => 'bg-gradient-to-r from-yellow-100 to-yellow-200', 'text' => 'text-yellow-800', 'icon' => 'fa-circle-pause', 'label' => 'Pending']
              ];
              $status = $statusData[$u['status']] ?? ['bg' => 'bg-gray-100', 'text' => 'text-gray-800', 'icon' => 'fa-circle', 'label' => ucfirst($u['status'])];
              ?>
              <span class="inline-flex items-center px-4 py-2 rounded-full text-sm font-semibold <?= $status['bg'] ?> <?= $status['text'] ?> shadow-sm">
                <i class="fa-solid <?= $status['icon'] ?> mr-2"></i>
                <?= $status['label'] ?>
              </span>
            </td>
            <td class="py-4 px-6 text-right">
              <div class="flex justify-end space-x-2">
                <button onclick='openEdit(<?= json_encode($u) ?>)' 
                        class="inline-flex items-center px-4 py-2 border border-cyan-300 rounded-lg text-sm font-medium text-cyan-700 bg-cyan-50 hover:bg-cyan-100 hover:border-cyan-400 transition-all duration-200 shadow-sm hover:shadow-md">
                  <i class="fa-solid fa-pen mr-2"></i>
                  Edit
                </button>
                <button onclick='deleteUser(<?= $u['id'] ?>)' 
                        class="inline-flex items-center px-4 py-2 border border-transparent rounded-lg text-sm font-medium text-white bg-gradient-to-r from-red-500 to-red-600 hover:from-red-600 hover:to-red-700 transition-all duration-200 shadow-sm hover:shadow-md">
                  <i class="fa-solid fa-trash mr-2"></i>
                  Delete
                </button>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  
  <!-- Enhanced Pagination -->
  <div class="mt-8 pt-6 border-t border-gray-200">
    <div class="flex flex-col sm:flex-row justify-between items-center gap-4">
      <div class="text-sm text-gray-600 bg-gray-50 px-4 py-2 rounded-lg">
        <i class="fa-solid fa-info-circle mr-2 text-cyan-600"></i>
        Displaying <span class="font-semibold"><?= $pagination['start_item'] ?>-<?= $pagination['end_item'] ?></span> 
        of <span class="font-semibold"><?= $pagination['total_items'] ?></span> total members
      </div>
      
      <div class="pagination-wrapper">
        <?php 
        $extraParams = ['p' => 'dashboard', 'page' => 'addAdminSuperAdmin'];
        if ($search) $extraParams['search'] = $search;
        if ($role) $extraParams['role'] = $role;
        
        echo renderPagination($pagination, url('index.php'), $extraParams, 'pg'); 
        ?>
      </div>
    </div>
  </div>

</div>

</main>

<!-- Edit Modal -->
<div id="editModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
  <div class="bg-white w-full max-w-md rounded-2xl p-6 shadow-xl">
    <div class="flex items-center justify-between mb-6">
      <h2 class="text-xl font-semibold text-gray-800 flex items-center gap-3">
        <div class="rounded-full bg-cyan-100 p-2">
          <i class="fa-solid fa-user-edit text-cyan-600"></i>
        </div>
        Edit Member
      </h2>
      <button type="button" onclick="closeModal()" class="text-gray-400 hover:text-gray-500 transition-colors">
        <i class="fa-solid fa-xmark text-xl"></i>
      </button>
    </div>
    
    <form id="editForm" class="space-y-4">
      <input type="hidden" name="action" value="update">
      <input type="hidden" name="id" id="edit_id">
      <?php csrfTokenField(); ?>
      
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-2">
          Full Name <span class="text-red-500">*</span>
        </label>
        <div class="relative">
          <input id="edit_name" name="name" type="text"
                 class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500 transition-colors"
                 required minlength="2" maxlength="100"
                 placeholder="Enter full name">
          <i class="fa-solid fa-user text-gray-400 absolute right-3 top-3.5"></i>
        </div>
      </div>
      
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-2">
          Email Address <span class="text-red-500">*</span>
        </label>
        <div class="relative">
          <input id="edit_email" name="email" type="email"
                 class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500 transition-colors"
                 required maxlength="255"
                 placeholder="Enter email address">
          <i class="fa-solid fa-envelope text-gray-400 absolute right-3 top-3.5"></i>
        </div>
      </div>
      
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">
            Role <span class="text-red-500">*</span>
          </label>
          <div class="relative">
            <select id="edit_role" name="role" required class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500 appearance-none transition-colors">
              <option value="user">User</option>
              <option value="admin">Admin</option>
              <option value="superadmin">Super Admin</option>
            </select>
            <i class="fa-solid fa-chevron-down text-gray-400 absolute right-3 top-3.5 pointer-events-none"></i>
          </div>
        </div>
        
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">
            Status <span class="text-red-500">*</span>
          </label>
          <div class="relative">
            <select id="edit_status" name="status" required class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500 appearance-none transition-colors">
              <option value="active">Active</option>
              <option value="blocked">Blocked</option>
              <option value="pending">Pending</option>
            </select>
            <i class="fa-solid fa-chevron-down text-gray-400 absolute right-3 top-3.5 pointer-events-none"></i>
          </div>
        </div>
      </div>
      
      <div class="flex justify-end space-x-3 pt-4">
        <button type="button" onclick="closeModal()" 
                class="px-6 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
          Cancel
        </button>
        <button type="submit" 
                class="px-6 py-2.5 border border-transparent rounded-lg text-sm font-medium text-white bg-cyan-600 hover:bg-cyan-700 transition-colors"
                data-original-text="Update Member">
          Update Member
        </button>
      </div>
    </form>
  </div>
</div>

<script>
function openEdit(user){
  document.getElementById('edit_id').value = user.id;
  document.getElementById('edit_name').value = user.name;
  document.getElementById('edit_email').value = user.email;
  document.getElementById('edit_role').value = user.role;
  document.getElementById('edit_status').value = user.status;
  document.getElementById('editModal').classList.remove('hidden');
  document.body.classList.add('overflow-hidden');
}

function closeModal(){
  document.getElementById('editModal').classList.add('hidden');
  document.body.classList.remove('overflow-hidden');
}

// Close modal when clicking outside
document.getElementById('editModal').addEventListener('click', function(e) {
  if (e.target === this) {
    closeModal();
  }
});

/* --- Client-side Validation --- */
function validateForm(form) {
  clearFormErrors(form);
  let isValid = true;
  
  // Get form data
  const formData = new FormData(form);
  const name = formData.get('name')?.trim();
  const email = formData.get('email')?.trim();
  const role = formData.get('role');
  const status = formData.get('status');
  
  // Validate name
  if (!name) {
    showFieldError(form, 'name', 'Full name is required');
    isValid = false;
  } else if (!/^[a-zA-Z\s\-'\.]+$/.test(name)) {
    showFieldError(form, 'name', 'Name can only contain letters, spaces, hyphens and apostrophes');
    isValid = false;
  }
  
  // Validate email
  if (!email) {
    showFieldError(form, 'email', 'Email address is required');
    isValid = false;
  } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
    showFieldError(form, 'email', 'Please enter a valid email address');
    isValid = false;
  }
  
  // Validate role
  if (!role || !['user', 'admin', 'superadmin'].includes(role)) {
    showFieldError(form, 'role', 'Please select a valid role');
    isValid = false;
  }
  
  // Validate status
  if (!status || !['active', 'blocked', 'pending'].includes(status)) {
    showFieldError(form, 'status', 'Please select a valid status');
    isValid = false;
  }
  
  return isValid;
}

function showFieldError(form, fieldName, message) {
  const field = form.querySelector(`[name="${fieldName}"]`);
  if (field) {
    // Add error styling to field
    field.classList.add('border-red-500', 'focus:ring-red-500');
    field.classList.remove('border-gray-300', 'focus:ring-cyan-500');
    
    // Create or update error message
    let errorDiv = field.parentElement.querySelector('.field-error');
    if (!errorDiv) {
      errorDiv = document.createElement('div');
      errorDiv.className = 'field-error mt-1 text-sm text-red-600 flex items-center';
      field.parentElement.appendChild(errorDiv);
    }
    errorDiv.innerHTML = `<i class="fa-solid fa-exclamation-circle mr-1"></i>${message}`;
  }
}

function clearFormErrors(form) {
  // Remove error styling from all fields
  const fields = form.querySelectorAll('input, select');
  fields.forEach(field => {
    field.classList.remove('border-red-500', 'focus:ring-red-500');
    field.classList.add('border-gray-300', 'focus:ring-cyan-500');
  });
  
  // Remove all error messages
  const errorDivs = form.querySelectorAll('.field-error');
  errorDivs.forEach(div => div.remove());
}

/* --- AJAX Helper --- */
async function postData(form){
  const submitBtn = form.querySelector('button[type="submit"]');
  const originalText = submitBtn ? submitBtn.innerHTML : '';
  
  // Clear previous errors
  clearFormErrors(form);
  
  // Client-side validation
  if (!validateForm(form)) {
    // Reset button state
    if (submitBtn) {
      submitBtn.disabled = false;
      const originalText = submitBtn.getAttribute('data-original-text') || submitBtn.innerHTML;
      submitBtn.innerHTML = originalText;
    }
    return;
  }
  
  try {
    const res = await fetch('',{method:'POST',body:new FormData(form)});
    const result = await res.text();
    
    if(result === 'success') {
      // Show success message with email confirmation
      if(form.id === 'addForm') {
        showNotification('Member created successfully and credentials email sent!', 'success');
      } else {
        showNotification('Member saved successfully!', 'success');
      }
      
      // Add success animation to form
      form.classList.add('form-success');
      
      // Reset form if it's the add form
      if(form.id === 'addForm') {
        setTimeout(() => {
          // Reset all form fields
          form.reset();
          
          // Manually reset dropdowns to their placeholder values
          const roleSelect = form.querySelector('select[name="role"]');
          const statusSelect = form.querySelector('select[name="status"]');
          
          if (roleSelect) {
            roleSelect.value = '';
          }
          if (statusSelect) {
            statusSelect.value = '';
          }
          
          // Clear any visual states
          form.classList.remove('form-success');
          clearFormErrors(form);
          
          // Reset all field styling to default
          const allFields = form.querySelectorAll('input, select');
          allFields.forEach(field => {
            field.classList.remove('border-red-500', 'focus:ring-red-500');
            field.classList.add('border-gray-300', 'focus:ring-cyan-500');
          });
        }, 300);
      }
      
      // Close modal if it's the edit form
      if(form.id === 'editForm') {
        setTimeout(() => {
          closeModal();
        }, 500);
      }
      
      // Reload page after a short delay to show the success message
      setTimeout(() => {
        location.reload();
      }, 1500);
      
    } else if(result === 'success_no_email') {
      // Show warning when user created but email failed
      showNotification('Member created successfully but email delivery failed. Please inform the user manually.', 'warning');
      
      // Add success animation to form
      form.classList.add('form-success');
      
      // Reset form if it's the add form
      if(form.id === 'addForm') {
        setTimeout(() => {
          form.reset();
          const roleSelect = form.querySelector('select[name="role"]');
          const statusSelect = form.querySelector('select[name="status"]');
          if (roleSelect) roleSelect.value = '';
          if (statusSelect) statusSelect.value = '';
          form.classList.remove('form-success');
          clearFormErrors(form);
          const allFields = form.querySelectorAll('input, select');
          allFields.forEach(field => {
            field.classList.remove('border-red-500', 'focus:ring-red-500');
            field.classList.add('border-gray-300', 'focus:ring-cyan-500');
          });
        }, 300);
      }
      
      // Reload page after a short delay
      setTimeout(() => {
        location.reload();
      }, 2000);
      
    } else if(result.startsWith('error:')) {
      showNotification(result.replace('error: ', ''), 'error');
    }
  } catch(error) {
    showNotification('An error occurred. Please try again.', 'error');
  }
  
  // Reset button state
  if (submitBtn) {
    submitBtn.disabled = false;
    const originalText = submitBtn.getAttribute('data-original-text') || submitBtn.innerHTML;
    submitBtn.innerHTML = originalText;
  }
}

/* --- Notification Function --- */
function showNotification(message, type = 'info') {
  // Remove existing notifications
  const existing = document.querySelector('.notification');
  if (existing) existing.remove();
  
  // Create notification
  const notification = document.createElement('div');
  notification.className = `notification fixed top-4 right-4 z-50 px-6 py-4 rounded-lg shadow-lg transition-all duration-300 transform translate-x-full`;
  
  if (type === 'success') {
    notification.className += ' bg-green-500 text-white';
    notification.innerHTML = `<i class="fa-solid fa-check-circle mr-2"></i>${message}`;
  } else if (type === 'error') {
    notification.className += ' bg-red-500 text-white';
    notification.innerHTML = `<i class="fa-solid fa-exclamation-circle mr-2"></i>${message}`;
  } else if (type === 'warning') {
    notification.className += ' bg-yellow-500 text-white';
    notification.innerHTML = `<i class="fa-solid fa-exclamation-triangle mr-2"></i>${message}`;
  } else {
    notification.className += ' bg-blue-500 text-white';
    notification.innerHTML = `<i class="fa-solid fa-info-circle mr-2"></i>${message}`;
  }
  
  document.body.appendChild(notification);
  
  // Animate in
  setTimeout(() => {
    notification.classList.remove('translate-x-full');
  }, 100);
  
  // Auto remove after 3 seconds
  setTimeout(() => {
    notification.classList.add('translate-x-full');
    setTimeout(() => notification.remove(), 300);
  }, 3000);
}

/* --- Form Events --- */
document.getElementById('addForm').onsubmit = e => {
  e.preventDefault(); 
  const submitBtn = e.target.querySelector('button[type="submit"]');
  if (submitBtn) {
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-2"></i>Adding Member...';
  }
  postData(e.target);
}

document.getElementById('editForm').onsubmit = e => {
  e.preventDefault(); 
  const submitBtn = e.target.querySelector('button[type="submit"]');
  if (submitBtn) {
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-2"></i>Updating...';
  }
  postData(e.target);
}

/* --- Delete --- */
async function deleteUser(id){
  if(!confirm('Are you sure you want to delete this member? This action cannot be undone.')) return;
  
  try {
    const fd = new FormData(); 
    fd.append('action','delete'); 
    fd.append('id',id);
    fd.append('csrf_token', '<?= getCsrfToken() ?>');
    
    const res = await fetch('',{method:'POST',body:fd});
    const result = await res.text();
    
    if(result === 'success') {
      showNotification('Member deleted successfully!', 'success');
      setTimeout(() => {
        location.reload();
      }, 1000);
    } else if(result.startsWith('error:')) {
      showNotification(result.replace('error: ', ''), 'error');
    }
  } catch(error) {
    showNotification('An error occurred while deleting the member.', 'error');
  }
}

// Enhanced interactivity and user experience
document.addEventListener('DOMContentLoaded', function() {
  // Add focus styles to form inputs
  const inputs = document.querySelectorAll('input, select');
  inputs.forEach(input => {
    input.addEventListener('focus', function() {
      this.parentElement.classList.add('ring-2', 'ring-cyan-200');
      // Clear error styling on focus
      this.classList.remove('border-red-500', 'focus:ring-red-500');
      this.classList.add('border-gray-300', 'focus:ring-cyan-500');
    });
    input.addEventListener('blur', function() {
      this.parentElement.classList.remove('ring-2', 'ring-cyan-200');
    });
    
    // Real-time validation on input
    input.addEventListener('input', function() {
      const form = this.closest('form');
      const fieldName = this.name;
      const value = this.value.trim();
      
      // Clear existing error for this field
      const errorDiv = this.parentElement.querySelector('.field-error');
      if (errorDiv) {
        errorDiv.remove();
      }
      
      // Reset field styling
      this.classList.remove('border-red-500', 'focus:ring-red-500');
      this.classList.add('border-gray-300', 'focus:ring-cyan-500');
      
      // Real-time validation
      if (fieldName === 'name' && value) {
        if (!/^[a-zA-Z\s\-'\.]+$/.test(value)) {
          showFieldError(form, fieldName, 'Name can only contain letters, spaces, hyphens and apostrophes');
        }
      } else if (fieldName === 'email' && value) {
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
          showFieldError(form, fieldName, 'Please enter a valid email address');
        }
      }
    });
  });
  
  // Add search input debouncing
  const searchInput = document.querySelector('input[name="search"]');
  if (searchInput) {
    let searchTimeout;
    searchInput.addEventListener('input', function() {
      clearTimeout(searchTimeout);
      const form = this.closest('form');
      searchTimeout = setTimeout(() => {
        // Auto-submit search after 500ms of no typing
        // form.submit();
      }, 500);
    });
  }
  
  // Add table row hover effects
  const tableRows = document.querySelectorAll('#membersTable tbody tr');
  tableRows.forEach(row => {
    row.addEventListener('mouseenter', function() {
      this.style.transform = 'scale(1.01)';
    });
    row.addEventListener('mouseleave', function() {
      this.style.transform = 'scale(1)';
    });
  });
});
</script>
</body>
</html>