<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/validation_helper.php';
require_once __DIR__ . '/../../includes/flash_helper.php';
require_login();

$pdo = db();
$user = current_user();
$user_id = $user['id'];
$success = false;

// Check if password change is required
$passwordChangeRequired = isset($_SESSION['password_change_required']) && $_SESSION['password_change_required'] === true;

// Clear the session flag after reading it
if ($passwordChangeRequired) {
    unset($_SESSION['password_change_required']);
}

// Get validation errors and old input
$validationErrors = FormValidator::getStoredErrors();

// Check if user is OAuth user (has oauth_provider column)
$isOAuthUser = false;
$oauthProvider = null;
try {
  $stmt = $pdo->prepare("SELECT oauth_provider FROM users WHERE id = ?");
  $stmt->execute([$user_id]);
  $result = $stmt->fetch(PDO::FETCH_ASSOC);
  if ($result && !empty($result['oauth_provider'])) {
    $isOAuthUser = true;
    $oauthProvider = ucfirst($result['oauth_provider']); // Google, Facebook, etc.
  }
} catch (PDOException $e) {
  // Column doesn't exist, user is regular user
  $isOAuthUser = false;
}

// ✅ Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // CSRF validation
  if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
    setErrorMessage('Invalid request. Please try again.');
    header("Location: " . $_SERVER['REQUEST_URI']);
    exit;
  }

  // Create validator with form data
  $validator = new FormValidator($_POST);
  
  // Validate basic fields
  $validator
    ->required('name', 'Full name is required')
    ->name('name', 'Name can only contain letters, spaces, hyphens and apostrophes');
  
  // Only validate email for non-OAuth users
  if (!$isOAuthUser) {
    $validator
      ->required('email', 'Email address is required')
      ->email('email', 'Please enter a valid email address');
  }

  // Validate password change if provided (only for non-OAuth users)
  $current_password = $_POST['current_password'] ?? '';
  $new_password = $_POST['new_password'] ?? '';
  $confirm_password = $_POST['confirm_password'] ?? '';
  
  if (!$isOAuthUser && (!empty($current_password) || !empty($new_password) || !empty($confirm_password))) {
    $validator
      ->required('current_password', 'Current password is required to change password')
      ->required('new_password', 'New password is required')
      ->minLength('new_password', 6, 'New password must be at least 6 characters long')
      ->required('confirm_password', 'Please confirm your new password')
      ->confirmPassword('new_password', 'confirm_password', 'New passwords do not match');
  }

  // ✅ Fetch current user from DB (fresh)
  $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
  $stmt->execute([$user_id]);
  $user = $stmt->fetch(PDO::FETCH_ASSOC);

  // Check if email is already taken by another user (only for non-OAuth users)
  if ($validator->passes() && !$isOAuthUser) {
    $email = trim($_POST['email']);
    if ($email !== $user['email']) {
      $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
      $stmt->execute([$email, $user_id]);
      if ($stmt->fetch()) {
        $validator->custom('email', function() { return false; }, 'This email address is already taken');
      }
    }
  }

  // Validate current password if password change is requested (only for non-OAuth users)
  if ($validator->passes() && !$isOAuthUser && !empty($current_password)) {
    if (!password_verify($current_password, $user['password'])) {
      $validator->custom('current_password', function() { return false; }, 'Current password is incorrect');
    }
  }

  // If validation fails, store errors and reload
  if ($validator->fails()) {
    $validator->storeErrors();
    header("Location: " . $_SERVER['REQUEST_URI']);
    exit;
  }

  $name = trim($_POST['name']);
  $profile_pic = null;
  
  // Handle email change for non-OAuth users
  $emailChanged = false;
  if (!$isOAuthUser) {
    $newEmail = trim($_POST['email']);
    if ($newEmail !== $user['email']) {
      // Email is being changed - require verification
      require_once __DIR__ . '/../../includes/email_verification_helper.php';
      
      $token = generateEmailVerificationToken();
      $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));
      
      // Store pending email and token
      $stmt = $pdo->prepare("
        UPDATE users 
        SET pending_email = ?,
            email_verification_token = ?,
            email_verification_expires_at = ?
        WHERE id = ?
      ");
      $stmt->execute([$newEmail, $token, $expiresAt, $user_id]);
      
      // Send verification email to new address
      sendEmailVerificationLink($newEmail, $user['name'], $token);
      
      // Send notification to old email
      sendEmailChangeNotification($user['email'], $user['name'], $newEmail);
      
      $emailChanged = true;
    }
  }

  // ✅ Handle profile image upload
 // ✅ Handle profile image upload
if (!empty($_FILES['profile_pic']['name'])) {
  $targetDir = dirname(__DIR__, 2) . '/public/uploads/profile_pics/';
  if (!file_exists($targetDir)) mkdir($targetDir, 0777, true);

  $ext = strtolower(pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION));
  $filename = 'user_' . $user_id . '_' . time() . '.' . $ext;
  $targetFile = $targetDir . $filename;

  if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $targetFile)) {
    // store relative path for DB
    $profile_pic = 'uploads/profile_pics/' . $filename;
  }
} else {
  $profile_pic = $user['profile_pic']; // keep old one if unchanged
}
  // ✅ Update name and profile picture (email handled separately above)
  $stmt = $pdo->prepare("UPDATE users SET name = ?, profile_pic = ? WHERE id = ?");
  $stmt->execute([$name, $profile_pic, $user_id]);

  // ✅ Handle password change (only for non-OAuth users)
  if (!$isOAuthUser && $current_password && $new_password && $new_password === $confirm_password) {
    if (password_verify($current_password, $user['password'])) {
      $newHash = password_hash($new_password, PASSWORD_DEFAULT);
      
      // Update password and reset requires_password_change flag
      try {
        // Check if requires_password_change column exists
        $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'requires_password_change'");
        if ($stmt->rowCount() > 0) {
          // Column exists, update both password and flag
          $pdo->prepare("UPDATE users SET password = ?, requires_password_change = 0 WHERE id = ?")->execute([$newHash, $user_id]);
        } else {
          // Column doesn't exist, just update password
          $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$newHash, $user_id]);
        }
      } catch (Exception $e) {
        // Fallback to just updating password
        $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$newHash, $user_id]);
      }
      
      // Send email notification about password change
      // TODO: Implement email notification
    }
  }

  // ✅ Fetch fresh data again
  $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
  $stmt->execute([$user_id]);
  $user = $stmt->fetch(PDO::FETCH_ASSOC);

  $_SESSION['user'] = $user; // refresh session
  $success = true;
  
  // Set appropriate success message
  if ($emailChanged) {
    setFlashMessage('success', 'Profile updated! A verification email has been sent to your new email address. Please check your inbox and verify within 24 hours.');
  } else {
    setFlashMessage('success', 'Profile updated successfully!');
  }
} else {
  // ✅ First-time page load
  $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
  $stmt->execute([$user_id]);
  $user = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>

<div class="container mx-auto p-6 bg-gray-50 min-h-screen">
  <?php if ($success): ?>
    <div class="bg-green-100 text-green-800 px-4 py-2 rounded mb-6">
      ✅ Profile updated successfully!
    </div>
  <?php endif; ?>

  <?php if ($passwordChangeRequired): ?>
  <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-6 rounded-r-lg shadow-md">
    <div class="flex items-start">
      <div class="flex-shrink-0">
        <i class="fas fa-exclamation-triangle text-yellow-400 text-2xl"></i>
      </div>
      <div class="ml-3 flex-1">
        <h3 class="text-sm font-medium text-yellow-800 mb-1">🔒 Password Change Required</h3>
        <p class="text-sm text-yellow-700 leading-relaxed">
          For security reasons, you must change your default password before you can access other features.
          Please use the form below to set a new, secure password.
        </p>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- Profile Update Form -->
  <form action="" method="POST" enctype="multipart/form-data" class="space-y-8">
    <?php csrfTokenField(); ?>
    <!-- Header -->
    <div class="flex flex-col sm:flex-row justify-between items-center mb-6 gap-4">
      <h2 class="text-2xl font-bold text-gray-800 flex items-center text-center sm:text-left">
        <i class="fas fa-user-cog text-blue-600 mr-3"></i> Profile Settings
      </h2>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- ✅ Profile Picture Section -->
      <div class="bg-white p-6 rounded-xl shadow flex flex-col items-center text-center">
        <div class="relative w-32 h-32 rounded-full overflow-hidden border border-gray-300 bg-gray-100 flex items-center justify-center">
          <img
            id="previewImage"
            src="<?= !empty($user['profile_pic']) ? BASE . htmlspecialchars($user['profile_pic']) : BASE . 'assets/img/default-user.png' ?>"
            alt="Profile"
            class="w-full h-full object-cover <?= empty($user['profile_pic']) ? 'hidden' : '' ?>">
          <i id="defaultIcon" class="fa fa-user text-gray-400 text-6xl <?= !empty($user['profile_pic']) ? 'hidden' : '' ?>"></i>
        </div>

        <label for="profile_pic" class="cursor-pointer bg-gradient-to-r from-blue-500 to-purple-600 text-white px-4 py-2 rounded-lg text-sm shadow hover:opacity-90 transition flex items-center mt-3">
          <i class="fas fa-upload mr-2"></i> Upload Photo
        </label>
        <input type="file" id="profile_pic" name="profile_pic" accept="image/*" class="hidden">

        <h3 class="text-lg sm:text-xl font-semibold flex items-center justify-center mt-4">
          <i class="fas fa-user-tie mr-2 text-blue-500"></i> <?= htmlspecialchars($user['name']) ?>
        </h3>
        <p class="text-gray-500 text-sm sm:text-base flex items-center mt-1">
          <i class="fas fa-envelope mr-2 text-gray-400"></i> <?= htmlspecialchars($user['email']) ?>
        </p>
      </div>

      <!-- ✅ Right Content -->
      <div class="lg:col-span-2 bg-white p-6 rounded-xl shadow">
        <div class="flex mb-6 space-x-6 border-b border-gray-200">
          <h2 class="text-blue-600 font-semibold pb-2 flex items-center text-sm sm:text-base">
            <i class="fas fa-user-edit mr-2"></i> Personal Info
          </h2>
        </div>

        <!-- Personal Info Form -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div class="md:col-span-2">
            <label class="block text-sm font-medium text-gray-700 flex items-center">
              <i class="fas fa-signature mr-2 text-blue-500"></i> Full Name
            </label>
            <div class="flex items-center border rounded-lg mt-1 focus-within:ring-2 focus-within:ring-blue-500 <?= isset($validationErrors['name']) ? 'border-red-500' : '' ?>">
              <i class="fas fa-user ml-3 text-gray-400"></i>
              <input type="text" name="name" value="<?= htmlspecialchars(oldValue('name', $user['name'])) ?>" class="w-full p-2 outline-none text-sm sm:text-base">
            </div>
            <?php displayFieldError('name', $validationErrors); ?>
          </div>

          <div class="md:col-span-2">
            <label class="block text-sm font-medium text-gray-700 flex items-center">
              <i class="fas fa-envelope mr-2 text-blue-500"></i> Email Address
              <?php if ($isOAuthUser): ?>
                <span class="ml-2 text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full">
                  <i class="fab fa-<?= strtolower($oauthProvider) ?> mr-1"></i><?= $oauthProvider ?>
                </span>
              <?php endif; ?>
            </label>
            <?php if ($isOAuthUser): ?>
              <!-- OAuth User - Email Disabled -->
              <div class="flex items-center border border-gray-200 bg-gray-50 rounded-lg mt-1">
                <i class="fas fa-envelope ml-3 text-gray-400"></i>
                <input type="email" value="<?= htmlspecialchars($user['email']) ?>" disabled class="w-full p-2 outline-none text-sm sm:text-base bg-gray-50 text-gray-600 cursor-not-allowed">
                <i class="fas fa-lock ml-2 mr-3 text-gray-400"></i>
              </div>
              <p class="text-xs text-gray-500 mt-1 flex items-center">
                <i class="fas fa-info-circle mr-1"></i>
                Email is managed by <?= $oauthProvider ?> and cannot be changed here
              </p>
            <?php else: ?>
              <!-- Regular User - Email Editable -->
              <?php if (!empty($user['pending_email'])): ?>
                <!-- Pending Email Verification -->
                <div class="bg-yellow-50 border-2 border-yellow-300 rounded-lg p-4 mt-1">
                  <div class="flex items-start">
                    <i class="fas fa-clock text-yellow-600 mt-1 mr-3"></i>
                    <div class="flex-1">
                      <p class="text-sm font-semibold text-yellow-900 mb-1">
                        Email Change Pending Verification
                      </p>
                      <p class="text-xs text-yellow-800 mb-2">
                        <strong>Current:</strong> <?= htmlspecialchars($user['email']) ?><br>
                        <strong>New (Pending):</strong> <?= htmlspecialchars($user['pending_email']) ?>
                      </p>
                      <p class="text-xs text-yellow-700 mb-3">
                        <i class="fas fa-info-circle mr-1"></i>
                        Please check your inbox at <strong><?= htmlspecialchars($user['pending_email']) ?></strong> and click the verification link.
                      </p>
                      <div class="flex flex-col sm:flex-row gap-2">
                        <a href="index.php?p=dashboard&page=resend_email_verification" 
                           class="inline-flex items-center justify-center px-4 py-2 bg-blue-600 text-white text-xs font-semibold rounded-lg hover:bg-blue-700 transition-colors duration-200">
                          <i class="fas fa-paper-plane mr-2"></i>
                          Resend Verification Email
                        </a>
                        <a href="index.php?p=dashboard&page=cancel_email_change" 
                           class="inline-flex items-center justify-center px-4 py-2 border-2 border-red-600 text-red-600 text-xs font-semibold rounded-lg hover:bg-red-50 transition-colors duration-200">
                          <i class="fas fa-times mr-2"></i>
                          Cancel Email Change
                        </a>
                      </div>
                    </div>
                  </div>
                </div>
                <!-- Show current email (read-only during pending verification) -->
                <div class="flex items-center border border-gray-300 bg-gray-50 rounded-lg mt-2">
                  <i class="fas fa-envelope ml-3 text-gray-400"></i>
                  <input type="email" value="<?= htmlspecialchars($user['email']) ?>" disabled class="w-full p-2 outline-none text-sm sm:text-base bg-gray-50 text-gray-600">
                </div>
                <p class="text-xs text-gray-500 mt-1">
                  Current email will remain active until new email is verified
                </p>
              <?php else: ?>
                <!-- Normal Email Input -->
                <div class="flex items-center border rounded-lg mt-1 focus-within:ring-2 focus-within:ring-blue-500 <?= isset($validationErrors['email']) ? 'border-red-500' : '' ?>">
                  <i class="fas fa-envelope ml-3 text-gray-400"></i>
                  <input type="email" name="email" value="<?= htmlspecialchars(oldValue('email', $user['email'])) ?>" class="w-full p-2 outline-none text-sm sm:text-base">
                </div>
                <?php displayFieldError('email', $validationErrors); ?>
                <p class="text-xs text-gray-500 mt-1 flex items-center">
                  <i class="fas fa-shield-alt mr-1"></i>
                  Changing email will require verification
                </p>
              <?php endif; ?>
            <?php endif; ?>
          </div>
        </div>

        <!-- Change Password Section -->
        <div class="mt-10 border-t pt-6">
          <h3 class="text-lg font-semibold mb-4 text-gray-800 flex items-center">
            <i class="fas fa-key mr-2 text-purple-500"></i> Password & Security
          </h3>

          <?php if ($isOAuthUser): ?>
            <!-- OAuth User - Password Info -->
            <div class="bg-gradient-to-r from-blue-50 to-indigo-50 border-2 border-blue-200 rounded-xl p-6">
              <div class="flex items-start">
                <div class="flex-shrink-0">
                  <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                    <i class="fab fa-<?= strtolower($oauthProvider) ?> text-blue-600 text-xl"></i>
                  </div>
                </div>
                <div class="ml-4 flex-1">
                  <h4 class="text-lg font-semibold text-blue-900 mb-2">
                    Signed in with <?= $oauthProvider ?>
                  </h4>
                  <p class="text-sm text-blue-700 mb-3">
                    Your account is secured by <?= $oauthProvider ?>. Password management is handled by your <?= $oauthProvider ?> account.
                  </p>
                  <a href="<?= $oauthProvider === 'Google' ? 'https://myaccount.google.com/security' : 'https://www.facebook.com/settings?tab=security' ?>" 
                     target="_blank" 
                     class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors duration-200 text-sm font-medium">
                    <i class="fas fa-external-link-alt mr-2"></i>
                    Manage in <?= $oauthProvider ?> Account
                  </a>
                </div>
              </div>
            </div>
          <?php else: ?>
            <!-- Regular User - Password Change Form -->
            <div class="space-y-4">
              <div class="flex items-center border rounded-lg focus-within:ring-2 focus-within:ring-blue-500 <?= isset($validationErrors['current_password']) ? 'border-red-500' : '' ?>">
                <i class="fas fa-lock ml-3 text-gray-400"></i>
                <input type="password" id="current_password" name="current_password" placeholder="Current Password" class="w-full p-2 outline-none text-sm sm:text-base">
                <button type="button" onclick="togglePasswordVisibility('current_password')" class="px-3 text-gray-500 hover:text-gray-700">
                  <i class="fas fa-eye" id="current_password-icon"></i>
                </button>
              </div>
              <?php displayFieldError('current_password', $validationErrors); ?>

              <div>
                <div class="flex items-center border rounded-lg focus-within:ring-2 focus-within:ring-blue-500 <?= isset($validationErrors['new_password']) ? 'border-red-500' : '' ?>">
                  <i class="fas fa-lock-open ml-3 text-gray-400"></i>
                  <input type="password" id="new_password" name="new_password" placeholder="New Password (min 6 characters)" class="w-full p-2 outline-none text-sm sm:text-base">
                  <button type="button" onclick="togglePasswordVisibility('new_password')" class="px-3 text-gray-500 hover:text-gray-700">
                    <i class="fas fa-eye" id="new_password-icon"></i>
                  </button>
                </div>
                <?php displayFieldError('new_password', $validationErrors); ?>
                
                <!-- Password Strength Indicator (Hidden by default) -->
                <div id="password-strength-container" class="mt-2 hidden">
                  <div class="flex items-center justify-between mb-1">
                    <span class="text-xs font-medium text-gray-600">Password Strength:</span>
                    <span id="strength-text" class="text-xs font-semibold"></span>
                  </div>
                  <div class="w-full bg-gray-200 rounded-full h-2 overflow-hidden">
                    <div id="strength-bar" class="h-full transition-all duration-300 rounded-full" style="width: 0%"></div>
                  </div>
                  <div class="mt-2 space-y-1 text-xs">
                    <div id="req-length" class="flex items-center text-gray-500">
                      <i class="fas fa-circle text-xs mr-2"></i>
                      <span>At least 6 characters</span>
                    </div>
                    <div id="req-letter" class="flex items-center text-gray-500">
                      <i class="fas fa-circle text-xs mr-2"></i>
                      <span>Contains letters</span>
                    </div>
                    <div id="req-number" class="flex items-center text-gray-500">
                      <i class="fas fa-circle text-xs mr-2"></i>
                      <span>Contains numbers</span>
                    </div>
                  </div>
                </div>
              </div>

              <div class="flex items-center border rounded-lg focus-within:ring-2 focus-within:ring-blue-500 <?= isset($validationErrors['confirm_password']) ? 'border-red-500' : '' ?>">
                <i class="fas fa-shield-alt ml-3 text-gray-400"></i>
                <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm New Password" class="w-full p-2 outline-none text-sm sm:text-base">
                <button type="button" onclick="togglePasswordVisibility('confirm_password')" class="px-3 text-gray-500 hover:text-gray-700">
                  <i class="fas fa-eye" id="confirm_password-icon"></i>
                </button>
              </div>
              <?php displayFieldError('confirm_password', $validationErrors); ?>
              
              <p class="text-xs text-gray-500 flex items-center">
                <i class="fas fa-info-circle mr-1"></i>
                Leave blank if you don't want to change your password
              </p>
            </div>
          <?php endif; ?>
        </div>

        <!-- ✅ Submit Button -->
        <div class="mt-8 text-right">
          <button type="submit"
            class="bg-gradient-to-r from-blue-500 to-purple-600 text-white px-6 py-2 rounded-lg shadow hover:opacity-90 transition flex items-center justify-center mx-auto sm:mx-0">
            <i class="fas fa-save mr-2"></i> Update Profile
          </button>
        </div>
      </div>
    </div>
  </form>
</div>

<script>
  // Profile picture preview
  document.getElementById('profile_pic').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
      const reader = new FileReader();
      reader.onload = function(event) {
        const img = document.getElementById('previewImage');
        const icon = document.getElementById('defaultIcon');
        img.src = event.target.result;
        img.classList.remove('hidden');
        icon.classList.add('hidden');
      };
      reader.readAsDataURL(file);
    }
  });

  // Toggle password visibility
  function togglePasswordVisibility(fieldId) {
    const field = document.getElementById(fieldId);
    const icon = document.getElementById(fieldId + '-icon');
    
    if (field.type === 'password') {
      field.type = 'text';
      icon.classList.remove('fa-eye');
      icon.classList.add('fa-eye-slash');
    } else {
      field.type = 'password';
      icon.classList.remove('fa-eye-slash');
      icon.classList.add('fa-eye');
    }
  }

  // Password strength checker
  const newPasswordField = document.getElementById('new_password');
  const strengthContainer = document.getElementById('password-strength-container');
  
  if (newPasswordField) {
    newPasswordField.addEventListener('input', function() {
      const password = this.value;
      const strengthBar = document.getElementById('strength-bar');
      const strengthText = document.getElementById('strength-text');
      const reqLength = document.getElementById('req-length');
      const reqLetter = document.getElementById('req-letter');
      const reqNumber = document.getElementById('req-number');
      
      // Show/hide strength indicator based on input
      if (password.length > 0) {
        strengthContainer.classList.remove('hidden');
      } else {
        strengthContainer.classList.add('hidden');
        return;
      }
      
      // Check requirements
      const hasLength = password.length >= 6;
      const hasLetter = /[a-zA-Z]/.test(password);
      const hasNumber = /[0-9]/.test(password);
      
      // Update requirement indicators
      updateRequirement(reqLength, hasLength);
      updateRequirement(reqLetter, hasLetter);
      updateRequirement(reqNumber, hasNumber);
      
      // Calculate strength
      let strength = 0;
      if (hasLength) strength += 33;
      if (hasLetter) strength += 33;
      if (hasNumber) strength += 34;
      
      // Update strength bar
      strengthBar.style.width = strength + '%';
      
      // Update colors and text
      if (strength === 0) {
        strengthBar.style.backgroundColor = '#e5e7eb';
        strengthText.textContent = '';
        strengthText.className = 'text-xs font-semibold';
      } else if (strength < 50) {
        strengthBar.style.backgroundColor = '#ef4444';
        strengthText.textContent = 'Weak';
        strengthText.className = 'text-xs font-semibold text-red-600';
      } else if (strength < 100) {
        strengthBar.style.backgroundColor = '#f59e0b';
        strengthText.textContent = 'Medium';
        strengthText.className = 'text-xs font-semibold text-orange-600';
      } else {
        strengthBar.style.backgroundColor = '#10b981';
        strengthText.textContent = 'Strong';
        strengthText.className = 'text-xs font-semibold text-green-600';
      }
    });
  }
  
  function updateRequirement(element, isMet) {
    const icon = element.querySelector('i');
    const text = element.querySelector('span');
    
    if (isMet) {
      icon.classList.remove('fa-circle');
      icon.classList.add('fa-check-circle');
      element.classList.remove('text-gray-500');
      element.classList.add('text-green-600');
    } else {
      icon.classList.remove('fa-check-circle');
      icon.classList.add('fa-circle');
      element.classList.remove('text-green-600');
      element.classList.add('text-gray-500');
    }
  }
</script>