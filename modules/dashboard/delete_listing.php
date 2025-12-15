<?php
require_once __DIR__ . "/../../middlewares/auth.php";
require_login();
require_once __DIR__ . "/../../includes/log_helper.php";
require_once __DIR__ . "/../../includes/flash_helper.php";

$listing_id = $_GET['id'] ?? null;

if (!$listing_id) {
    setErrorMessage("Invalid request: Listing ID missing.");
    header("Location: " . url("public/index.php?p=dashboard&page=my_listing"));
    exit;
}

$user = current_user();
$pdo = db();

try {
    // ✅ Pehle check karo listing user ki hi hai
    $stmt = $pdo->prepare("SELECT * FROM listings WHERE id = ? AND user_id = ?");
    $stmt->execute([$listing_id, $user['id']]);
    $listing = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$listing) {
        setErrorMessage("Unauthorized or Listing not found.");
        header("Location: " . url("public/index.php?p=dashboard&page=my_listing"));
        exit;
    }

    // ✅ Agar proofs attached hain to delete unke files bhi
    $proofStmt = $pdo->prepare("SELECT file_path FROM listing_proofs WHERE listing_id = ?");
    $proofStmt->execute([$listing_id]);
    $proofs = $proofStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($proofs as $proof) {
        $file = __DIR__ . "/../../public/" . $proof['file_path'];
        if (file_exists($file)) {
            unlink($file);
        }
    }

    // ✅ Delete proofs from DB
    $pdo->prepare("DELETE FROM listing_proofs WHERE listing_id = ?")->execute([$listing_id]);

    // ✅ Delete listing itself
    $pdo->prepare("DELETE FROM listings WHERE id = ? AND user_id = ?")->execute([$listing_id, $user['id']]);

    // ✅ Log action
    log_action("Listing Deleted", "User deleted listing: {$listing['name']} ({$listing['url']})", "listing", $user['id']);

    // Use popup helper for success message
    require_once __DIR__ . "/../../includes/popup_helper.php";
    setSuccessPopup("Listing '{$listing['name']}' has been deleted successfully!", [
        'title' => 'Listing Deleted',
        'autoClose' => true,
        'autoCloseTime' => 3000
    ]);

    header("Location: " . url("public/index.php?p=dashboard&page=my_listing"));
    exit;
} catch (Exception $e) {
    // Use popup helper for error message
    require_once __DIR__ . "/../../includes/popup_helper.php";
    setErrorPopup("Error deleting listing: " . $e->getMessage(), [
        'title' => 'Delete Failed',
        'autoClose' => false
    ]);

    header("Location: " . url("public/index.php?p=dashboard&page=my_listing"));
    exit;
}
