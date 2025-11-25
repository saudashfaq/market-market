<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/flash_helper.php';
require_once __DIR__ . '/../includes/log_helper.php';
require_once __DIR__ . '/../includes/validation_helper.php';
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Debug: Log all received data
    error_log("=== FORM SUBMISSION DEBUG ===");
    error_log("POST data: " . print_r($_POST, true));
    error_log("FILES data: " . print_r($_FILES, true));
    error_log("Session user: " . print_r($_SESSION['user'] ?? 'NOT SET', true));
    
    $user_id = $_SESSION['user']['id'] ?? null;
    if (!$user_id) die("Unauthorized: Please log in first.");

    // CSRF validation
    $csrfToken = $_POST['csrf_token'] ?? '';
    error_log("CSRF Token received: " . $csrfToken);
    
    if (!validateCsrfToken($csrfToken)) {
        error_log("CSRF validation failed for token: " . $csrfToken);
        setErrorMessage('Invalid request. Please try again.');
        header("Location: " . url('index.php?p=addWebList'));
        exit;
    }

    // Create validator with form data
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
        $validator->custom('asking_price', function($value) {
            return is_numeric($value) && $value > 0;
        }, 'Asking price must be a valid positive number');
    }

    // Type-specific validation
    if ($type === 'youtube') {
        // Only validate subscribers if it's provided (not required)
        if (!empty($_POST['subscribers'])) {
            $validator->custom('subscribers', function($value) {
                return is_numeric($value) && $value >= 0;
            }, 'Subscriber count must be a valid number');
        }
    }

    // URL format validation based on type
    if ($type === 'youtube') {
        $validator->custom('url', function($value) {
            return strpos($value, 'youtube.com') !== false || strpos($value, 'youtu.be') !== false;
        }, 'Please enter a valid YouTube channel URL');
    } elseif ($type === 'website') {
        $validator->custom('url', function($value) {
            return filter_var($value, FILTER_VALIDATE_URL) !== false;
        }, 'Please enter a valid website URL');
    }

    // If validation fails, store errors and redirect
    if ($validator->fails()) {
        error_log("Validation failed with errors: " . print_r($validator->getErrors(), true));
        $validator->storeErrors();
        $redirectPage = ($type === 'youtube') ? 'addYTList' : 'addWebList';
        setErrorMessage('Please fix the validation errors and try again.');
        header("Location: " . url("index.php?p={$redirectPage}"));
        exit;
    }
    
    error_log("Validation passed successfully!");

    // Extract validated data
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $url = trim($_POST['url']);
    $traffic_trend = $_POST['traffic_trend'] ?? '';
    $monthly_revenue = $_POST['monthly_revenue'] ?? 0;
    $asking_price = $_POST['asking_price'] ?? 0;
    $site_age = $_POST['site_age'] ?? '';
    $category = $_POST['category'] ?? ''; 
    $monetization_methods = $_POST['monetization'] ?? '';
    $subscribers = $_POST['subscribers'] ?? null;
    $videos_count = $_POST['videos_count'] ?? null;
    $faceless = isset($_POST['faceless']) ? (int)$_POST['faceless'] : 0;
    $status = 'pending';

    $selectedCategories = $_POST['categories'] ?? ''; 
    $selectedLabels = $_POST['labels'] ?? '';
    
    // ✅ BIDDING CONTROL FIELDS
    $reserved_amount = !empty($_POST['reserved_amount']) ? floatval($_POST['reserved_amount']) : 0.00;
    $min_down_payment_percentage = !empty($_POST['min_down_payment_percentage']) ? floatval($_POST['min_down_payment_percentage']) : 50.00;
    $buy_now_price = !empty($_POST['buy_now_price']) ? floatval($_POST['buy_now_price']) : null;
    $auto_extend_enabled = isset($_POST['auto_extend_enabled']) ? 1 : 0;
    
    // Calculate auction end time if duration is provided
    $auction_end_time = null;
    $auction_duration_days = !empty($_POST['auction_duration_days']) ? intval($_POST['auction_duration_days']) : null;
    if ($auction_duration_days && $auction_duration_days > 0) {
        $auction_end_time = date('Y-m-d H:i:s', strtotime("+{$auction_duration_days} days"));
    }

    // Debug: Log the received data
    error_log("Selected Categories: " . $selectedCategories);
    error_log("Selected Labels: " . $selectedLabels);
    error_log("Bidding Controls - Reserved: $reserved_amount, Min Down: $min_down_payment_percentage%, Buy Now: " . ($buy_now_price ?? 'null'));
    error_log("POST data: " . print_r($_POST, true));

    try {
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
            $auction_end_time  // original_end_time same as auction_end_time initially
        ]);

        $listing_id = $pdo->lastInsertId();

        // Send notification to superadmin in background
        register_shutdown_function(function() use ($pdo, $listing_id, $name, $category, $asking_price, $user_id, $type) {
            if (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            }
            
            try {
                require_once __DIR__ . '/../includes/email_helper.php';
                
                // Get seller details
                $stmt = $pdo->prepare("SELECT name, email FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $seller = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($seller) {
                    sendSuperAdminNotification(
                        'New Listing Submitted - Review Required',
                        'New Listing Awaiting Approval',
                        'A new listing has been submitted to your marketplace and requires your review and approval.',
                        [
                            'Listing ID' => '#' . $listing_id,
                            'Listing Title' => $name,
                            'Type' => ucfirst($type),
                            'Category' => $category ?: 'Not specified',
                            'Asking Price' => '$' . number_format($asking_price, 2),
                            'Seller Name' => $seller['name'],
                            'Seller Email' => $seller['email'],
                            'Submitted' => date('F j, Y \a\t g:i A')
                        ],
                        url('index.php?p=dashboard&page=listingverification')
                    );
                    error_log("✅ SuperAdmin notified about new listing #$listing_id");
                }
            } catch (Exception $e) {
                error_log("❌ Failed to send superadmin listing notification: " . $e->getMessage());
            }
        });

        // ✅ Save question answers (all types supported)
        foreach ($_POST as $key => $value) {
            if (strpos($key, 'question_') === 0) {
                $questionId = str_replace('question_', '', $key);

                // Validate question ID is numeric
                if (!is_numeric($questionId)) {
                    error_log("Invalid question ID: " . $questionId);
                    continue;
                }

                // Handle multi-select or checkbox answers
                if (is_array($value)) {
                    $answer = implode(',', array_map('trim', $value));
                } else {
                    $answer = trim($value);
                }

                if ($answer === '') continue;

                try {
                    $stmtAns = $pdo->prepare("
                        INSERT INTO listing_answers (listing_id, question_id, answer)
                        VALUES (:listing_id, :question_id, :answer)
                    ");
                    $stmtAns->execute([
                        ':listing_id' => $listing_id,
                        ':question_id' => (int)$questionId,
                        ':answer' => $answer
                    ]);
                    error_log("Saved answer for question {$questionId}: {$answer}");
                } catch (Exception $e) {
                    error_log("Error saving question answer for question {$questionId}: " . $e->getMessage());
                }
            }
        }


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

        // Debug: Log file upload information
        error_log("Files received: " . print_r($_FILES, true));
        
        if (!empty($_FILES['proof_files']['name'][0])) {
            $uploadDir = __DIR__ . '/../public/uploads/proofs/';
            if (!is_dir($uploadDir)) {
                if (!mkdir($uploadDir, 0755, true)) {
                    error_log("Failed to create upload directory: " . $uploadDir);
                }
            }

            $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif', 'application/pdf'];
            $maxSize = 10 * 1024 * 1024;
            $uploadedCount = 0;

            foreach ($_FILES['proof_files']['name'] as $key => $fileName) {
                if (empty($fileName)) continue;
                
                $tmp = $_FILES['proof_files']['tmp_name'][$key];
                $type = $_FILES['proof_files']['type'][$key];
                $size = $_FILES['proof_files']['size'][$key];
                $error = $_FILES['proof_files']['error'][$key];

                error_log("Processing file: $fileName, Error: $error, Size: $size, Type: $type");

                if ($error !== UPLOAD_ERR_OK) {
                    error_log("Upload error for file $fileName: " . $error);
                    continue;
                }
                
                if ($size > $maxSize) {
                    error_log("File too large: $fileName ($size bytes)");
                    continue;
                }

                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $realType = finfo_file($finfo, $tmp);
                finfo_close($finfo);
                
                error_log("Real file type for $fileName: $realType");
                
                if (!in_array($realType, $allowedTypes)) {
                    error_log("Invalid file type for $fileName: $realType");
                    continue;
                }

                $safeName = time() . '_' . bin2hex(random_bytes(5)) . '_' .
                            preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($fileName));
                $targetPath = $uploadDir . $safeName;

                if (move_uploaded_file($tmp, $targetPath)) {
                    chmod($targetPath, 0644);
                    $relPath = 'uploads/proofs/' . $safeName;
                    $stmtProof = $pdo->prepare("INSERT INTO listing_proofs (listing_id, file_path) VALUES (?, ?)");
                    $stmtProof->execute([$listing_id, $relPath]);
                    $uploadedCount++;
                    error_log("Successfully uploaded file: $fileName as $safeName");
                } else {
                    error_log("Failed to move uploaded file: $fileName to $targetPath");
                }
            }
            
            error_log("Total files uploaded: $uploadedCount");
        } else {
            error_log("No files received in proof_files array");
        }


        // Send email notification to admin
        require_once __DIR__ . '/../includes/email_helper.php';
        
        // Get admin email from database
        try {
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
                
                if ($emailSent) {
                    error_log("Admin notification email sent successfully for listing ID: {$listing_id}");
                } else {
                    error_log("Failed to send admin notification email for listing ID: {$listing_id}");
                }
            } else {
                error_log("No admin email found in database");
            }
        } catch (Exception $e) {
            error_log("Error sending admin notification: " . $e->getMessage());
        }

        // Create notifications
        require_once __DIR__ . '/../includes/notification_helper.php';
        
        // Notify admins about new listing
        notifyNewListing($listing_id, $name, $_SESSION['user']['name'] ?? 'User');
        
        // Notify user about successful listing creation
        createNotification(
            $user_id,
            'listing',
            'Listing Created Successfully!',
            "Your listing '{$name}' has been submitted and is pending approval",
            $listing_id,
            'listing'
        );
        
        // Notify all admins/superadmins about new listing
        if (function_exists('notifyNewListing')) {
            notifyNewListing($listing_id, $name, $user['name']);
        }

        
        // Clear any old form data from session
        FormValidator::clearOldInput();
        unset($_SESSION['validation_errors']);
        
        // Redirect to professional success page
        header("Location: " . url("index.php?p=listingSuccess&id={$listing_id}&type={$type}"));
        exit;
        
    } catch (Exception $e) {
        error_log("Listing save error: " . $e->getMessage());
        setErrorMessage("Error saving listing: " . $e->getMessage());
        header("Location: " . url('index.php?p=create_listing'));
        exit;
    }

    log_action("Add listing", "User added a new listing: {$name}");
}