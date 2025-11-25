<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/flash_helper.php';
require_once __DIR__ . '/../includes/log_helper.php';
require_once __DIR__ . '/../includes/validation_helper.php';
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF validation
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        setErrorMessage('Invalid request. Please try again.');
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
    }

    $user = current_user();
    $user_id = $user['id'] ?? null;

    if (!$user_id) die("Unauthorized: Please log in first.");

    $listing_id = $_POST['id'] ?? null;
    if (!$listing_id) die("Listing ID missing.");

    $type = $_POST['type'] ?? '';
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $url = trim($_POST['url'] ?? '');
    $traffic_trend = $_POST['traffic_trend'] ?? '';
    $monthly_revenue = $_POST['monthly_revenue'] ?? 0;
    $asking_price = $_POST['asking_price'] ?? 0;
    $site_age = $_POST['site_age'] ?? '';
    $category = $_POST['category'] ?? '';
    $monetization_methods = $_POST['monetization'] ?? '';
    $subscribers = $_POST['subscribers'] ?? null;
    $videos_count = $_POST['videos_count'] ?? null;
    $faceless = isset($_POST['faceless']) ? 1 : 0;

    try {
        // 1️⃣ Update listing details and change status to pending for re-review
        $stmt = $pdo->prepare("
            UPDATE listings SET
                name = ?, email = ?, url = ?, traffic_trend = ?,
                monthly_revenue = ?, asking_price = ?, site_age = ?, category = ?,
                monetization_methods = ?, subscribers = ?, videos_count = ?, faceless = ?,
                status = 'pending', updated_at = NOW()
            WHERE id = ? AND user_id = ?
        ");
        $stmt->execute([
            $name, $email, $url, $traffic_trend,
            $monthly_revenue, $asking_price, $site_age, $category,
            $monetization_methods, $subscribers, $videos_count, $faceless,
            $listing_id, $user_id
        ]);

        // 1.5️⃣ Update categories and labels
        $categories = $_POST['categories'] ?? '';
        $labels = $_POST['labels'] ?? '';

        // Delete existing categories and labels
        try {
            $pdo->prepare("DELETE FROM listing_categories WHERE listing_id = ?")->execute([$listing_id]);
            $pdo->prepare("DELETE FROM listing_labels WHERE listing_id = ?")->execute([$listing_id]);
        } catch (Exception $e) {
            // Tables might not exist, continue
        }

        // Insert new categories
        if (!empty($categories)) {
            $catIds = explode(',', $categories);
            try {
                $stmtCat = $pdo->prepare("INSERT INTO listing_categories (listing_id, category_id) VALUES (?, ?)");
                foreach ($catIds as $catId) {
                    $catId = (int)trim($catId);
                    if ($catId > 0) {
                        $stmtCat->execute([$listing_id, $catId]);
                    }
                }
            } catch (Exception $e) {
                // Table might not exist, continue
            }
        }

        // Insert new labels
        if (!empty($labels)) {
            $labelIds = explode(',', $labels);
            try {
                $stmtLabel = $pdo->prepare("INSERT INTO listing_labels (listing_id, label_id) VALUES (?, ?)");
                foreach ($labelIds as $labelId) {
                    $labelId = (int)trim($labelId);
                    if ($labelId > 0) {
                        $stmtLabel->execute([$listing_id, $labelId]);
                    }
                }
            } catch (Exception $e) {
                // Table might not exist, continue
            }
        }

        // 1.6️⃣ Update question answers
        if (!empty($_POST)) {
            try {
                // Delete existing answers
                $pdo->prepare("DELETE FROM listing_answers WHERE listing_id = ?")->execute([$listing_id]);
                
                // Insert new answers
                $stmtAnswer = $pdo->prepare("INSERT INTO listing_answers (listing_id, question_id, answer) VALUES (?, ?, ?)");
                foreach ($_POST as $key => $value) {
                    if (strpos($key, 'question_') === 0) {
                        $questionId = (int)str_replace('question_', '', $key);
                        if ($questionId > 0) {
                            $answer = is_array($value) ? implode(',', $value) : $value;
                            if (!empty(trim($answer))) {
                                $stmtAnswer->execute([$listing_id, $questionId, trim($answer)]);
                            }
                        }
                    }
                }
            } catch (Exception $e) {
                // Table might not exist, continue
            }
        }

        // ✅ Log the update action
        log_action(
            "Listing Updated",
            "User updated listing ID {$listing_id} ({$name}) - Status changed to pending for re-review",
            "listing",
            $user_id
        );

        // 2️⃣ Delete selected files if any
        if (!empty($_POST['delete_files'])) {
            foreach ($_POST['delete_files'] as $filePath) {
                $fullPath = __DIR__ . '/../public/' . $filePath;
                if (file_exists($fullPath)) unlink($fullPath);
                $stmt = $pdo->prepare("DELETE FROM listing_proofs WHERE listing_id = ? AND file_path = ?");
                $stmt->execute([$listing_id, $filePath]);
            }

            // ✅ Log deleted proofs
            log_action(
                "Listing Proof Deleted",
                "User deleted proof files for listing ID {$listing_id}",
                "listing",
                $user_id
            );
        }

        // 3️⃣ Upload new files
        if (!empty($_FILES['proof_files']['name'][0])) {
            $uploadDir = __DIR__ . '/../public/uploads/proofs/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

            $allowedTypes = ['image/jpeg', 'image/png', 'application/pdf'];
            $maxSize = 10 * 1024 * 1024; // 10 MB

            foreach ($_FILES['proof_files']['name'] as $key => $fileName) {
                $tmp = $_FILES['proof_files']['tmp_name'][$key];
                $size = $_FILES['proof_files']['size'][$key];
                $error = $_FILES['proof_files']['error'][$key];

                if ($error !== UPLOAD_ERR_OK) continue;
                if ($size > $maxSize) continue;

                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $realType = finfo_file($finfo, $tmp);
                finfo_close($finfo);
                if (!in_array($realType, $allowedTypes)) continue;

                $safeName = time() . '_' . bin2hex(random_bytes(5)) . '_' .
                    preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($fileName));
                $targetPath = $uploadDir . $safeName;

                if (move_uploaded_file($tmp, $targetPath)) {
                    chmod($targetPath, 0644);
                    $relPath = 'uploads/proofs/' . $safeName;
                    $stmt = $pdo->prepare("INSERT INTO listing_proofs (listing_id, file_path) VALUES (?, ?)");
                    $stmt->execute([$listing_id, $relPath]);
                }
            }

            // ✅ Log new uploads
            log_action(
                "Listing Proof Uploaded",
                "User uploaded new proof files for listing ID {$listing_id}",
                "listing",
                $user_id
            );
        }

        // Notify admins about listing update
        require_once __DIR__ . '/../includes/notification_helper.php';
        notifyNewListing($listing_id, $name, $_SESSION['user']['name'] ?? 'User');
        
        // Notify user about successful update
        createNotification(
            $user_id,
            'listing',
            'Listing Updated',
            "Your listing '{$name}' has been updated and is pending review",
            $listing_id,
            'listing'
        );
        
        setSuccessMessage("Listing updated successfully! Your listing status has been changed to 'Pending' and will be reviewed again by our team.");
        header("Location: index.php?p=dashboard&page=my_listing");
        exit;

    } catch (Exception $e) {
        setErrorMessage("Error updating listing: " . $e->getMessage());
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
    }
}
?>
