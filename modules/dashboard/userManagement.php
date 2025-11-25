<?php
// Check for export FIRST - before any output
if (isset($_GET['export'])) {
    require_once __DIR__ . '/../../config.php';
    require_once __DIR__ . '/../../includes/export_helper.php';
    
    ob_start();
    require_login();
    ob_end_clean();
    
    $pdo = db();
    
    // Get all users for export
    $exportSql = "
        SELECT id, name, email, role, status, created_at
        FROM users
        ORDER BY created_at DESC
    ";
    
    $exportStmt = $pdo->query($exportSql);
    $exportData = $exportStmt->fetchAll(PDO::FETCH_ASSOC);
    
    handleExportRequest($exportData, 'Users Management Report');
    exit;
}
?>
  <div class="p-6">
    <div class="flex justify-between items-center mb-6">
      <h1 class="text-2xl font-bold text-gray-800">User Management</h1>
      <div class="flex items-center gap-3">
        <?php require_once __DIR__ . '/../../includes/export_helper.php'; echo getExportButton('users'); ?>
        <button class="flex items-center gap-2 bg-gradient-to-r from-blue-500 to-purple-500 text-white px-4 py-2 rounded-lg shadow hover:opacity-90">
          <i class="fa fa-user-plus"></i> Add User
        </button>
      </div>
    </div>
    <div class="bg-white rounded-lg shadow p-4 mb-6 flex flex-col md:flex-row gap-4">
      <div class="flex-1">
        <input type="text" placeholder="Search by name, email, or ID..." 
          class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring focus:ring-blue-200">
      </div>
      <select class="px-4 py-2 border rounded-lg">
        <option>All Roles</option>
        <option>Buyer</option>
        <option>Seller</option>
        <option>Both</option>
      </select>
      <select class="px-4 py-2 border rounded-lg">
        <option>All Status</option>
        <option>Active</option>
        <option>Blocked</option>
      </select>
    </div>
    <div class="bg-white rounded-lg shadow overflow-x-auto">
      <table class="w-full text-left border-collapse">
        <thead>
          <tr class="bg-gray-100 text-gray-700">
            <th class="px-4 py-3">User ID</th>
            <th class="px-4 py-3">Name</th>
            <th class="px-4 py-3">Email</th>
            <th class="px-4 py-3">Role</th>
            <th class="px-4 py-3">Status</th>
            <th class="px-4 py-3">Actions</th>
          </tr>
        </thead>
        <tbody class="text-gray-700">
          <tr class="border-t">
            <td class="px-4 py-3 font-medium">USR-001 <div class="text-xs text-gray-400">Joined 1/15/2024</div></td>
            <td class="px-4 py-3">John Smith</td>
            <td class="px-4 py-3">john.smith@techcorp.com</td>
            <td class="px-4 py-3"><span class="bg-blue-100 text-blue-600 px-3 py-1 rounded-full text-sm">Buyer</span></td>
            <td class="px-4 py-3"><span class="bg-green-100 text-green-600 px-3 py-1 rounded-full text-sm">Active</span></td>
            <td class="px-4 py-3 flex gap-3">
              <i class="fa fa-eye text-gray-500 hover:text-blue-500 cursor-pointer"></i>
              <i class="fa fa-check text-gray-500 hover:text-green-500 cursor-pointer"></i>
              <i class="fa fa-ban text-gray-500 hover:text-red-500 cursor-pointer"></i>
              <i class="fa fa-key text-gray-500 hover:text-purple-500 cursor-pointer"></i>
            </td>
          </tr>
          <tr class="border-t">
            <td class="px-4 py-3 font-medium">USR-002 <div class="text-xs text-gray-400">Joined 1/10/2024</div></td>
            <td class="px-4 py-3">Sarah Johnson</td>
            <td class="px-4 py-3">sarah@saasbuilder.com</td>
            <td class="px-4 py-3"><span class="bg-pink-100 text-pink-600 px-3 py-1 rounded-full text-sm">Seller</span></td>
            <td class="px-4 py-3"><span class="bg-green-100 text-green-600 px-3 py-1 rounded-full text-sm">Active</span></td>
            <td class="px-4 py-3 flex gap-3">
              <i class="fa fa-eye text-gray-500 hover:text-blue-500 cursor-pointer"></i>
              <i class="fa fa-check text-gray-500 hover:text-green-500 cursor-pointer"></i>
              <i class="fa fa-ban text-gray-500 hover:text-red-500 cursor-pointer"></i>
              <i class="fa fa-key text-gray-500 hover:text-purple-500 cursor-pointer"></i>
            </td>
          </tr>
          <tr class="border-t">
            <td class="px-4 py-3 font-medium">USR-003 <div class="text-xs text-gray-400">Joined 1/8/2024</div></td>
            <td class="px-4 py-3">Mike Chen</td>
            <td class="px-4 py-3">mike.chen@digitalventures.com</td>
            <td class="px-4 py-3"><span class="bg-orange-100 text-orange-600 px-3 py-1 rounded-full text-sm">Both</span></td>
            <td class="px-4 py-3"><span class="bg-green-100 text-green-600 px-3 py-1 rounded-full text-sm">Active</span></td>
            <td class="px-4 py-3 flex gap-3">
              <i class="fa fa-eye text-gray-500 hover:text-blue-500 cursor-pointer"></i>
              <i class="fa fa-check text-gray-500 hover:text-green-500 cursor-pointer"></i>
              <i class="fa fa-ban text-gray-500 hover:text-red-500 cursor-pointer"></i>
              <i class="fa fa-key text-gray-500 hover:text-purple-500 cursor-pointer"></i>
            </td>
          </tr>
          <tr class="border-t">
            <td class="px-4 py-3 font-medium">USR-004 <div class="text-xs text-gray-400">Joined 1/5/2024</div></td>
            <td class="px-4 py-3">Emma Wilson</td>
            <td class="px-4 py-3">emma.wilson@startup.com</td>
            <td class="px-4 py-3"><span class="bg-blue-100 text-blue-600 px-3 py-1 rounded-full text-sm">Buyer</span></td>
            <td class="px-4 py-3"><span class="bg-red-100 text-red-600 px-3 py-1 rounded-full text-sm">Blocked</span></td>
            <td class="px-4 py-3 flex gap-3">
              <i class="fa fa-eye text-gray-500 hover:text-blue-500 cursor-pointer"></i>
              <i class="fa fa-check text-gray-500 hover:text-green-500 cursor-pointer"></i>
              <i class="fa fa-lock text-gray-500 hover:text-red-500 cursor-pointer"></i>
              <i class="fa fa-key text-gray-500 hover:text-purple-500 cursor-pointer"></i>
            </td>
          </tr>
          <tr class="border-t">
            <td class="px-4 py-3 font-medium">USR-005 <div class="text-xs text-gray-400">Joined 1/12/2024</div></td>
            <td class="px-4 py-3">David Rodriguez</td>
            <td class="px-4 py-3">david@contentcreator.com</td>
            <td class="px-4 py-3"><span class="bg-pink-100 text-pink-600 px-3 py-1 rounded-full text-sm">Seller</span></td>
            <td class="px-4 py-3"><span class="bg-green-100 text-green-600 px-3 py-1 rounded-full text-sm">Active</span></td>
            <td class="px-4 py-3 flex gap-3">
              <i class="fa fa-eye text-gray-500 hover:text-blue-500 cursor-pointer"></i>
              <i class="fa fa-check text-gray-500 hover:text-green-500 cursor-pointer"></i>
              <i class="fa fa-ban text-gray-500 hover:text-red-500 cursor-pointer"></i>
              <i class="fa fa-key text-gray-500 hover:text-purple-500 cursor-pointer"></i>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
