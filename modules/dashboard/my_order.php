<?php
// Check for export FIRST - before any output
if (isset($_GET['export'])) {
  require_once __DIR__ . '/../../config.php';
  require_once __DIR__ . '/../../includes/export_helper.php';

  ob_start();
  require_login();
  $user = current_user();
  ob_end_clean();

  $pdo = db();

  // Get all orders for export
  $exportSql = "
        SELECT o.id, o.amount, o.status, o.created_at,
               l.name AS listing_name, l.type AS category,
               s.name AS seller_name, s.email AS seller_email
        FROM offers o
        JOIN listings l ON o.listing_id = l.id
        JOIN users s ON o.seller_id = s.id
        WHERE o.user_id = :user_id
        ORDER BY o.created_at DESC
    ";

  $exportStmt = $pdo->prepare($exportSql);
  $exportStmt->execute([':user_id' => $user['id']]);
  $exportData = $exportStmt->fetchAll(PDO::FETCH_ASSOC);

  handleExportRequest($exportData, 'My Orders Report');
  exit;
}

require_once __DIR__ . "/../../config.php";
require_once __DIR__ . '/../../includes/pagination_helper.php';
require_login();

$user = current_user();
$pdo = db();

// Get pagination parameters
$page = getCurrentPage('pg');
$perPage = 10;

// Setup search and filter conditions
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';

$whereClause = 'WHERE o.user_id = :user_id';
$params = [':user_id' => $user['id']];

if ($search) {
  $whereClause .= ' AND (l.name LIKE :search OR s.name LIKE :search)';
  $params[':search'] = '%' . $search . '%';
}
if ($status) {
  $whereClause .= ' AND o.status = :status';
  $params[':status'] = $status;
}

$sql = "
    SELECT o.*, l.name AS listing_name, l.type AS category, s.name AS seller_name
    FROM offers o
    JOIN listings l ON o.listing_id = l.id
    JOIN users s ON o.seller_id = s.id
    $whereClause
    ORDER BY o.created_at DESC
";

$countSql = "
    SELECT COUNT(*) as total
    FROM offers o
    JOIN listings l ON o.listing_id = l.id
    JOIN users s ON o.seller_id = s.id
    $whereClause
";

// Get paginated data
$result = getCustomPaginationData($pdo, $sql, $countSql, $params, $page, $perPage);
$offers = $result['data'];
$pagination = $result['pagination'];
?>

<section class="text-gray-800 body-font py-12 px-4 sm:px-6 lg:px-8">
  <div class="max-w-7xl mx-auto">

    <!-- Header -->
    <div class="mb-10 flex flex-col md:flex-row justify-between items-center gap-4">
      <h1 class="text-2xl sm:text-3xl md:text-4xl font-extrabold text-gray-900 flex items-center gap-2">
        <i class="fas fa-shopping-bag text-blue-600"></i>
        My Orders
      </h1>
      <?php require_once __DIR__ . '/../../includes/export_helper.php';
      echo getExportButton('my_orders'); ?>
    </div>

    <!-- Search and Filter Section -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
      <form method="GET" class="flex flex-wrap gap-4 items-end">
        <input type="hidden" name="p" value="dashboard">
        <input type="hidden" name="page" value="my_order">

        <div class="flex-1 min-w-[200px]">
          <label class="block text-sm font-medium text-gray-700 mb-2">
            <i class="fa fa-search mr-1"></i>Search Orders
          </label>
          <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
            placeholder="Search by listing name or seller..."
            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
        </div>

        <div class="min-w-[150px]">
          <label class="block text-sm font-medium text-gray-700 mb-2">
            <i class="fa fa-filter mr-1"></i>Status
          </label>
          <select name="status" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            <option value="">All Status</option>
            <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
            <option value="accepted" <?= $status === 'accepted' ? 'selected' : '' ?>>Accepted</option>
            <option value="rejected" <?= $status === 'rejected' ? 'selected' : '' ?>>Rejected</option>
            <option value="withdrawn" <?= $status === 'withdrawn' ? 'selected' : '' ?>>Withdrawn</option>
          </select>
        </div>

        <div class="flex gap-2">
          <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors flex items-center">
            <i class="fa fa-search mr-2"></i>Filter
          </button>
          <a href="?p=dashboard&page=my_order" class="px-6 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition-colors flex items-center">
            <i class="fa fa-times mr-2"></i>Clear
          </a>
        </div>
      </form>
    </div>

    <!-- Orders -->
    <div class="space-y-6">
      <?php if (empty($offers)): ?>
        <div class="text-center bg-white rounded-2xl shadow p-8 sm:p-10 border border-gray-100">
          <i class="fas fa-box-open text-4xl text-gray-400 mb-3"></i>
          <h3 class="text-lg font-semibold text-gray-700 mb-2">No Orders Yet</h3>
          <p class="text-gray-500 text-sm">You haven't placed or received any offers yet.</p>
        </div>
      <?php else: ?>
        <?php foreach ($offers as $offer): ?>
          <?php
          $status = $offer['status'];

          $statusConfig = [
            'accepted' => [
              'color' => 'bg-green-100 text-green-700',
              'icon' => 'fas fa-check-circle',
              'label' => 'Accepted by Seller'
            ],
            'pending' => [
              'color' => 'bg-yellow-100 text-yellow-700',
              'icon' => 'fas fa-clock',
              'label' => 'Waiting for Seller'
            ],
            'rejected' => [
              'color' => 'bg-red-100 text-red-700',
              'icon' => 'fas fa-times-circle',
              'label' => 'Rejected'
            ],
            'withdrawn' => [
              'color' => 'bg-gray-100 text-gray-700',
              'icon' => 'fas fa-minus-circle',
              'label' => 'Withdrawn'
            ]
          ];

          $statusInfo = $statusConfig[$status] ?? [
            'color' => 'bg-gray-100 text-gray-600',
            'icon' => 'fas fa-question-circle',
            'label' => ucfirst($status)
          ];
          ?>

          <div class="bg-white rounded-2xl shadow-md hover:shadow-lg transition duration-300 p-5 sm:p-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 border border-gray-100" data-offer-id="<?= $offer['id'] ?>">

            <!-- Left Content -->
            <div class="flex flex-col sm:flex-row gap-4 w-full sm:w-auto">
              <div class="w-12 h-12 sm:w-14 sm:h-14 bg-gradient-to-r from-blue-500 to-indigo-600 rounded-xl flex items-center justify-center text-white text-xl sm:text-2xl shadow-md">
                <i class="fas fa-handshake"></i>
              </div>

              <div class="flex-1">
                <h3 class="text-base sm:text-lg font-semibold text-gray-900 flex items-center flex-wrap">
                  <i class="fas fa-file-alt mr-2 text-gray-400"></i>
                  <?= htmlspecialchars($offer['listing_name']) ?>
                </h3>
                <p class="text-sm text-gray-500 flex items-center flex-wrap mt-1">
                  <i class="fas fa-user-tie mr-1"></i>
                  Seller: <span class="font-medium text-gray-700 ml-1"><?= htmlspecialchars($offer['seller_name']) ?></span>
                  <span class="hidden sm:inline mx-2">â€¢</span>
                  <i class="fas fa-folder mr-1 sm:ml-2"></i>
                  <?= htmlspecialchars(ucfirst($offer['category'])) ?>
                </p>
                <p class="text-xs text-gray-400 mt-1 flex items-center">
                  <i class="fas fa-calendar-alt mr-1"></i>
                  Offer made on <?= date('d M Y', strtotime($offer['created_at'])) ?>
                </p>

                <!-- Actions -->
                <div class="flex flex-wrap gap-2 sm:gap-3 mt-4">
                  <?php if ($status === 'accepted'): ?>
                    <a href="<?= url('index.php?p=payment&id=' . $offer['listing_id']) ?>"
                      class="px-3 sm:px-4 py-1.5 bg-gradient-to-r from-green-600 to-emerald-600 text-white rounded-lg text-xs sm:text-sm font-medium hover:opacity-90 hover:scale-105 transition-transform duration-200 flex items-center">
                      <i class="fas fa-credit-card mr-2"></i>Make Payment
                    </a>
                  <?php elseif ($status === 'pending'): ?>
                    <span class="px-3 sm:px-4 py-1.5 text-xs sm:text-sm font-medium border border-gray-300 rounded-lg text-gray-600 flex items-center">
                      <i class="fas fa-clock mr-2"></i>Waiting for Seller
                    </span>
                  <?php elseif ($status === 'rejected'): ?>
                    <span class="px-3 sm:px-4 py-1.5 text-xs sm:text-sm font-medium border border-gray-300 rounded-lg text-gray-600 flex items-center">
                      <i class="fas fa-times-circle mr-2"></i>Offer Rejected
                    </span>
                  <?php elseif ($status === 'withdrawn'): ?>
                    <span class="px-3 sm:px-4 py-1.5 text-xs sm:text-sm font-medium border border-gray-300 rounded-lg text-gray-600 flex items-center">
                      <i class="fas fa-minus-circle mr-2"></i>Offer Withdrawn
                    </span>
                  <?php endif; ?>

                  <a href="<?= url('index.php?p=dashboard&page=message&seller_id=' . $offer['seller_id'] . '&listing_id=' . $offer['listing_id']) ?>"
                    class="px-3 sm:px-4 py-1.5 text-xs sm:text-sm font-medium border border-gray-300 rounded-lg hover:bg-gray-100 transition flex items-center">
                    <i class="fas fa-comments mr-2"></i>Contact Seller
                  </a>

                  <a href="<?= url('index.php?p=listingDetail&id=' . $offer['listing_id']) ?>"
                    class="px-3 sm:px-4 py-1.5 text-xs sm:text-sm font-medium border border-gray-300 rounded-lg hover:bg-gray-100 transition flex items-center">
                    <i class="fas fa-eye mr-2"></i>View Listing
                  </a>
                </div>
              </div>
            </div>

            <!-- Right Content -->
            <div class="text-left sm:text-right w-full sm:w-auto">
              <p class="text-lg sm:text-xl font-bold text-gray-900 flex items-center justify-start sm:justify-end">
                <i class="fas fa-dollar-sign mr-1 text-green-500"></i><?= number_format($offer['amount'], 2) ?>
              </p>
              <span class="text-xs <?= $statusInfo['color'] ?> px-3 py-1 rounded-full font-medium flex items-center justify-start sm:justify-end mt-2">
                <i class="<?= $statusInfo['icon'] ?> mr-1"></i>
                <?= $statusInfo['label'] ?>
              </span>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <!-- Pagination -->
    <div class="mt-6">
      <?php
      $extraParams = ['p' => 'dashboard', 'page' => 'my_order'];
      if ($search) $extraParams['search'] = $search;
      if ($status) $extraParams['status'] = $status;

      echo renderPagination($pagination, url('index.php'), $extraParams, 'pg');
      ?>
    </div>
  </div>
</section>