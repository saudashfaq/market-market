<?php
// Check for export FIRST - before any output
if (isset($_GET['export'])) {
    require_once __DIR__ . '/../../config.php';
    require_once __DIR__ . '/../../includes/export_helper.php';
    
    ob_start();
    require_login();
    ob_end_clean();
    
    $pdo = db();
    
    // Get all listings for export
    $exportSql = "
        SELECT l.id, l.name, l.type, l.status, l.asking_price, l.monthly_revenue,
               u.name as seller_name, u.email as seller_email, l.created_at
        FROM listings l
        LEFT JOIN users u ON l.user_id = u.id
        ORDER BY l.created_at DESC
    ";
    
    $exportStmt = $pdo->query($exportSql);
    $exportData = $exportStmt->fetchAll(PDO::FETCH_ASSOC);
    
    handleExportRequest($exportData, 'Listings Management Report');
    exit;
}
?>

  <div class="p-6">
    <div class="flex justify-between items-center mb-6">
      <h1 class="text-2xl font-bold text-gray-800">Listings Management</h1>
      <div class="flex items-center gap-3">
        <?php require_once __DIR__ . '/../../includes/export_helper.php'; echo getExportButton('listings'); ?>
        <button class="flex items-center gap-2 bg-gradient-to-r from-blue-500 to-purple-500 text-white px-4 py-2 rounded-lg shadow hover:opacity-90">
          <i class="fa fa-plus"></i> Add Listing
        </button>
      </div>
    </div>
    <div class="flex gap-6 bg-gray-900 rounded-lg px-4 py-2 text-white mb-6">
      <button class="px-4 py-2 rounded-md bg-blue-600 font-medium">Pending</button>
      <button class="px-4 py-2 text-gray-400 hover:text-white">Verified</button>
      <button class="px-4 py-2 text-gray-400 hover:text-white">Rejected</button>
    </div>
    <div class="bg-white rounded-lg shadow overflow-x-auto">
      <table class="w-full text-left border-collapse">
        <thead>
          <tr class="bg-gray-100 text-gray-700">
            <th class="px-4 py-3">LISTING ID</th>
            <th class="px-4 py-3">SELLER NAME</th>
            <th class="px-4 py-3">URL/CHANNEL</th>
            <th class="px-4 py-3">REVENUE</th>
            <th class="px-4 py-3">STATUS</th>
            <th class="px-4 py-3">ACTIONS</th>
          </tr>
        </thead>
        <tbody class="text-gray-700">
          <tr class="border-t">
            <td class="px-4 py-3 font-bold">LST001</td>
            <td class="px-4 py-3">John Smith</td>
            <td class="px-4 py-3 text-blue-600 hover:underline cursor-pointer">techblog.com</td>
            <td class="px-4 py-3">$2,500/mo</td>
            <td class="px-4 py-3">
              <span class="bg-yellow-700 text-white px-3 py-1 rounded-full text-sm">pending</span>
            </td>
            <td class="px-4 py-3 flex gap-4">
              <a href="#" class="flex items-center gap-1 text-blue-600 hover:underline">
                <i class="fa fa-eye"></i> View
              </a>
              <a href="#" class="flex items-center gap-1 text-green-600 hover:underline">
                <i class="fa fa-check"></i> Verify
              </a>
              <a href="#" class="flex items-center gap-1 text-red-600 hover:underline">
                <i class="fa fa-times"></i> Reject
              </a>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>