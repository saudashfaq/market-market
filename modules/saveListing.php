// ------------------------------------------------------------
// Begin script execution – wrap everything in a top‑level try/catch
// ------------------------------------------------------------
try {
// Existing includes (unchanged)
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/flash_helper.php';
require_once __DIR__ . '/../includes/log_helper.php';
require_once __DIR__ . '/../includes/validation_helper.php';
$pdo = db();



if ($_SERVER['REQUEST_METHOD'] === 'POST') {


// Log incoming data (sanitized for privacy)


$user_id = $_SESSION['user']['id'] ?? null;
if (!$user_id) {

die('Unauthorized: Please log in first.');
}


// CSRF validation
$csrfToken = $_POST['csrf_token'] ?? '';


if (!validateCsrfToken($csrfToken)) {

setErrorMessage('Invalid request. Please try again.');
header('Location: ' . url('public/index.php?p=addWebList'));
exit;
}

// -----------------------------------------------------------------
// Validation (kept as before) – we add debug after each step
// -----------------------------------------------------------------
$validator = new FormValidator($_POST);
$type = $_POST['type'] ?? '';


// Basic validation for all listing types
$validator
->required('name', 'Listing name is required')
->required('email', 'Email address is required')
->email('email', 'Please enter a valid email address')
->required('url', 'URL is required');

// Only validate asking_price if it's provided
if (!empty($_POST['asking_price'])) {
$validator->custom('asking_price', function ($value) {
return is_numeric($value) && $value > 0;
}, 'Asking price must be a valid positive number');
}

// Type‑specific validation
if ($type === 'youtube') {
if (!empty($_POST['subscribers'])) {
$validator->custom('subscribers', function ($value) {
return is_numeric($value) && $value >= 0;
}, 'Subscriber count must be a valid number');
}
}

// URL format validation based on type
if ($type === 'youtube') {
$validator->custom('url', function ($value) {
return strpos($value, 'youtube.com') !== false || strpos($value, 'youtu.be') !== false;
}, 'Please enter a valid YouTube channel URL');
} elseif ($type === 'website') {
$validator->custom('url', function ($value) {
return filter_var($value, FILTER_VALIDATE_URL) !== false;
}, 'Please enter a valid website URL');
}

// If validation fails, store errors and redirect
if ($validator->fails()) {

$validator->storeErrors();
$redirectPage = ($type === 'youtube') ? 'addYTList' : 'addWebList';
setErrorMessage('Please fix the validation errors and try again.');
header('Location: ' . url("public/index.php?p={$redirectPage}"));
exit;
}


// -----------------------------------------------------------------
// Extract validated data (add debug for each variable)
// -----------------------------------------------------------------
$name = trim($_POST['name']);
$email = trim($_POST['email']);
$url = trim($_POST['url']);
$traffic_trend = !empty($_POST['traffic_trend']) ? $_POST['traffic_trend'] : null;
$monthly_revenue = $_POST['monthly_revenue'] ?? 0;
$asking_price = $_POST['asking_price'] ?? 0;
$site_age = !empty($_POST['site_age']) ? $_POST['site_age'] : null;
$category = !empty($_POST['category']) ? $_POST['category'] : null;
$monetization_methods = $_POST['monetization'] ?? '';
$subscribers = $_POST['subscribers'] ?? null;
$videos_count = $_POST['videos_count'] ?? null;
$faceless = isset($_POST['faceless']) ? (int)$_POST['faceless'] : 0;
$status = 'pending';
$selectedCategories = $_POST['categories'] ?? '';
$selectedLabels = $_POST['labels'] ?? '';

// ✅ Bidding control fields
$reserved_amount = !empty($_POST['reserved_amount']) ? floatval($_POST['reserved_amount']) : 0.00;
$min_down_payment_percentage = !empty($_POST['min_down_payment_percentage']) ? floatval($_POST['min_down_payment_percentage']) : 50.00;
$buy_now_price = !empty($_POST['buy_now_price']) ? floatval($_POST['buy_now_price']) : null;
$auto_extend_enabled = isset($_POST['auto_extend_enabled']) ? 1 : 0;

// Auction end time
$auction_end_time = null;
$auction_duration_days = !empty($_POST['auction_duration_days']) ? intval($_POST['auction_duration_days']) : null;
if ($auction_duration_days && $auction_duration_days > 0) {
$auction_end_time = date('Y-m-d H:i:s', strtotime("+{$auction_duration_days} days"));
}

// Debug dump of extracted data


// -----------------------------------------------------------------
// Insert listing record
// -----------------------------------------------------------------
$stmt = $pdo->prepare("
INSERT INTO listings (
user_id, type, name, email, url, traffic_trend,
monthly_revenue, asking_price, site_age, category,
monetization_methods, subscribers, videos_count, faceless, status,
reserved_amount, min_down_payment_percentage, buy_now_price,
auto_extend_enabled, auction_end_time, original_end_time,
created_at
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
");
$stmt->execute([
$user_id,
$type,
$name,
$email,
$url,
$traffic_trend,
$monthly_revenue,
$asking_price,
$site_age,
$category,
$monetization_methods,
$subscribers,
$videos_count,
$faceless,
$status,
$reserved_amount,
$min_down_payment_percentage,
$buy_now_price,
$auto_extend_enabled,
$auction_end_time,
$auction_end_time // original_end_time same as auction_end_time initially
]);
$listing_id = $pdo->lastInsertId();


// -----------------------------------------------------------------
// Proof file handling – detailed logging for each file
// -----------------------------------------------------------------
if (!empty($_FILES['proof_files']['name'][0])) {
$uploadDir = __DIR__ . '/../public/uploads/proofs/';
if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {

}

$allowedTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif', 'image/webp', 'application/pdf'];
$maxSize = 10 * 1024 * 1024; // 10 MB
$uploadedCount = 0;

foreach ($_FILES['proof_files']['name'] as $key => $fileName) {
if (empty($fileName)) continue;

$tmp = $_FILES['proof_files']['tmp_name'][$key];
$type = $_FILES['proof_files']['type'][$key];
$size = $_FILES['proof_files']['size'][$key];
$error = $_FILES['proof_files']['error'][$key];



if ($error !== UPLOAD_ERR_OK) {

continue;
}
if ($size > $maxSize) {

continue;
}

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$realType = finfo_file($finfo, $tmp);
finfo_close($finfo);


if (!in_array($realType, $allowedTypes)) {

continue;
}

$safeName = time() . '_' . bin2hex(random_bytes(5)) . '_' .
preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($fileName));
$targetPath = $uploadDir . $safeName;

if (move_uploaded_file($tmp, $targetPath)) {
chmod($targetPath, 0644);
$relPath = '/uploads/proofs/' . $safeName; // web‑accessible path
$stmtProof = $pdo->prepare("INSERT INTO listing_proofs (listing_id, file_path) VALUES (?, ?)");
try {
$stmtProof->execute([$listing_id, $relPath]);
$uploadedCount++;

} catch (Exception $e) {

}
} else {

}
}

} else {

}

// -----------------------------------------------------------------
// Email notification to admin
// -----------------------------------------------------------------
try {
require_once __DIR__ . '/../includes/email_helper.php';
$adminStmt = $pdo->query("SELECT email, name FROM users WHERE role = 'admin' LIMIT 1");
$admin = $adminStmt->fetch(PDO::FETCH_ASSOC);
if ($admin && !empty($admin['email'])) {
$listingData = [
'id' => $listing_id,
'title' => $name . ' - ' . ucfirst($type),
'category' => $category ?: 'N/A',
'price' => $asking_price
];
$emailSent = sendAdminNewListingEmail($admin['email'], $listingData, $name);

} else {

}
} catch (Exception $e) {

}

// -----------------------------------------------------------------
// Create notifications (admin + user)
// -----------------------------------------------------------------
require_once __DIR__ . '/../includes/notification_helper.php';
notifyNewListing($listing_id, $name, $_SESSION['user']['name'] ?? 'User');

createNotification(
$user_id,
'listing',
'Listing Created',
"Your listing '{$name}' has been submitted and is pending approval",
$listing_id,
'listing'
);

// Notify all admins/superadmins (fallback)
if (function_exists('notifyNewListing')) {
// $user may not be defined – use session name instead
$adminName = $_SESSION['user']['name'] ?? 'User';
notifyNewListing($listing_id, $name, $adminName);
}

// -----------------------------------------------------------------
// Save question answers (if any)
// -----------------------------------------------------------------
foreach ($_POST as $key => $value) {
if (strpos($key, 'question_') === 0) {
$questionId = str_replace('question_', '', $key);
if (!is_numeric($questionId)) {

continue;
}
$answer = is_array($value) ? implode(',', array_map('trim', $value)) : trim($value);
if ($answer === '') continue;
try {
$stmtAns = $pdo->prepare("
INSERT INTO listing_question_answers (listing_id, question_id, answer)
VALUES (:listing_id, :question_id, :answer)
");
$stmtAns->execute([
':listing_id' => $listing_id,
':question_id' => (int)$questionId,
':answer' => $answer
]);

} catch (Exception $e) {

}
}
}

// -----------------------------------------------------------------
// Save selected categories & labels
// -----------------------------------------------------------------
if (!empty($selectedCategories)) {
$catIds = explode(',', $selectedCategories);
$stmtCat = $pdo->prepare("INSERT INTO listing_categories (listing_id, category_id) VALUES (?, ?)");
foreach ($catIds as $catId) {
$stmtCat->execute([$listing_id, (int)$catId]);
}

}

if (!empty($selectedLabels)) {
$labelIds = explode(',', $selectedLabels);
$stmtLabel = $pdo->prepare("INSERT INTO listing_labels (listing_id, label_id) VALUES (?, ?)");
foreach ($labelIds as $labelId) {
$stmtLabel->execute([$listing_id, (int)$labelId]);
}

}

// -----------------------------------------------------------------
// Log activity for Admin Reports
// -----------------------------------------------------------------
log_action(
"Listing Created",
"Created new {$type} listing: {$name}",
"listing",
$user_id,
$user['role'] ?? 'user'
);

// -----------------------------------------------------------------
// Final redirect
// -----------------------------------------------------------------
header("Location: " . url("public/index.php?p=listingSuccess&id={$listing_id}&type={$type}"));
exit;
}
} catch (Exception $e) {

setErrorMessage('Error saving listing: ' . $e->getMessage());
header("Location: " . url('index.php?p=create_listing'));
exit;
}

// Log the action (kept from original code)
log_action("Add listing", "User added a new listing: {$name}");