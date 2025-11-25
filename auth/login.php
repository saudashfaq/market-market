  <?php
  require_once __DIR__ . "/../config.php";
  $activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'login';
  require_once __DIR__ . "/../middlewares/auth.php";
  require_once __DIR__ . "/../includes/validation_helper.php";
  require_once __DIR__ . "/../includes/flash_helper.php";

  // Redirect if already logged in
  redirect_if_authenticated();

  // Get validation errors and old input
  $validationErrors = FormValidator::getStoredErrors();
  $oldInput = FormValidator::getOldInput();
  ?>
  <!DOCTYPE html>
  <html lang="en">
  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
  <link
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css"
    rel="stylesheet"
  />
  <style>
    .shake {
      animation: shake 0.5s ease-in-out;
    }
    
    @keyframes shake {
      0%, 100% { transform: translateX(0); }
      25% { transform: translateX(-5px); }
      75% { transform: translateX(5px); }
    }
    
    .field-error {
      border-color: #ef4444 !important;
      box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1) !important;
    }
    
    .field-success {
      border-color: #10b981 !important;
      box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1) !important;
    }
    
    .password-strength {
      height: 4px;
      border-radius: 2px;
      transition: all 0.3s ease;
    }
  </style>
  </head>
  <body>
  <section class="min-h-screen bg-gradient-to-br from-blue-50 via-purple-50 to-indigo-50 flex items-center justify-center px-4 py-8">
    <div class="flex w-full max-w-6xl mx-auto bg-white rounded-3xl shadow-2xl overflow-hidden min-h-[600px]">
      <div class="hidden lg:flex w-2/5 bg-gradient-to-br from-blue-600 via-purple-600 to-indigo-700 items-center justify-center relative overflow-hidden">
        <div class="absolute inset-0 bg-gradient-to-br from-blue-500/20 via-purple-500/20 to-indigo-500/20"></div>
        <div class="absolute top-10 left-10 w-32 h-32 bg-white/10 rounded-full blur-xl"></div>
        <div class="absolute bottom-20 right-16 w-24 h-24 bg-white/10 rounded-full blur-xl"></div>
        <div class="relative z-10 text-center">
          <div class="w-32 h-32 mx-auto mb-8 bg-white/20 rounded-3xl backdrop-blur-sm flex items-center justify-center">
            <svg class="w-16 h-16 text-white" fill="currentColor" viewbox="0 0 24 24">
              <path d="M12 2L13.09 8.26L22 9L13.09 9.74L12 16L10.91 9.74L2 9L10.91 8.26L12 2Z"></path>
              <path d="M19 15L20.09 18.26L24 19L20.09 19.74L19 23L17.91 19.74L14 19L17.91 18.26L19 15Z"></path>
              <path d="M5 15L6.09 18.26L10 19L6.09 19.74L5 23L3.91 19.74L0 19L3.91 18.26L5 15Z"></path>
            </svg>
          </div>
          <h3 class="text-2xl font-bold text-white mb-2">Digital Marketplace</h3>
          <p class="text-white/80 max-w-xs">Connect buyers and sellers in our innovative platform ecosystem</p>
        </div>
      </div>
      <div class="w-full lg:w-3/5 p-8 lg:p-12 flex items-center justify-center">
        <div class="w-full max-w-md">
          <div class="mb-8 text-center">
            <img src="https://static.shuffle.dev/uploads/files/d5/d579c48ce8a4bf793e2f6a858e038f852d45d0d8/logos/logo-78d34ac57821d853aaf47e300463f4f0.png" alt="Logo" class="h-10 mx-auto mb-4" />
            <h2 class="text-3xl font-bold text-gray-900 mb-2">Welcome Back</h2>
            <p class="text-gray-600">Sign in to your marketplace account</p>
          </div>
          
          <!-- Display Flash Messages -->
          <?php displayFlashMessages(); ?>
          
          <div class="auth-tabs mb-8">
            <div class="flex bg-gray-100 rounded-xl p-1">
              <a href="index.php?p=login&tab=login"
                class="flex-1 text-center py-3 px-4 text-sm font-semibold rounded-lg transition-all duration-200
                  <?php echo ($activeTab == 'login') ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-600'; ?>">
                Login
              </a>
              <a href="index.php?p=login&tab=signup"
                class="flex-1 text-center py-3 px-4 text-sm font-semibold rounded-lg transition-all duration-200
                  <?php echo ($activeTab == 'signup') ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-600'; ?>">
                Sign Up
              </a>
            </div>
          </div>
          
          <?php if ($activeTab == 'login'): ?>
            <div class="auth-tab-content" id="login-content">
            <form class="space-y-6" action="<?= url('index.php?p=auth_login') ?>" method="post" id="loginForm" novalidate>
                <?php csrfTokenField(); ?>   
                
                <!-- Email Field -->
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">Email Address</label>
                  <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                      <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207"></path>
                      </svg>
                    </div>
                    <input type="email" 
                           name="email" 
                           id="login-email"
                           class="<?= inputErrorClass('email', $validationErrors, 'w-full pl-10 pr-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200') ?>" 
                           placeholder="Enter your email" 
                           value="<?= oldValue('email') ?>"
                           autocomplete="email" />
                  </div>
                  <?php displayFieldError('email', $validationErrors); ?>
                </div>
                
                <!-- Password Field -->
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">Password</label>
                  <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                      <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 002 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                      </svg>
                    </div>
                    <input type="password" 
                           name="password" 
                           id="login-password"
                           class="<?= inputErrorClass('password', $validationErrors, 'w-full pl-10 pr-12 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200') ?>" 
                           placeholder="Enter your password"
                           autocomplete="current-password" />
                    <button type="button" 
                            onclick="togglePassword('login-password')" 
                            class="absolute inset-y-0 right-0 pr-3 flex items-center">
                      <svg class="h-5 w-5 text-gray-400 hover:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                      </svg>
                    </button>
                  </div>
                  <?php displayFieldError('password', $validationErrors); ?>
                </div>
                
                <div class="flex items-center justify-between">
                  <a href="../public/forgot-password.php" class="text-sm font-medium text-blue-600 hover:text-blue-500 transition-colors duration-200">Forgot password?</a>
                </div>
                
                <button type="submit" class="w-full bg-gradient-to-r from-blue-600 to-purple-600 text-white font-semibold py-3 px-4 rounded-xl hover:from-blue-700 hover:to-purple-700 transform hover:scale-[1.02] transition-all duration-200 shadow-lg">
                  Sign In
                </button>
              </form>
              
              <!-- Social Login Divider -->
              <div class="relative my-6">
                <div class="absolute inset-0 flex items-center">
                  <div class="w-full border-t border-gray-300"></div>
                </div>
                <div class="relative flex justify-center text-sm">
                  <span class="px-4 bg-white text-gray-500">Or continue with</span>
                </div>
              </div>
              
              <!-- Social Login Buttons -->
              <div class="grid grid-cols-2 gap-4">
                <a href="<?= url('index.php?p=auth_google_callback') ?>" 
                   class="flex items-center justify-center px-4 py-3 border border-gray-300 rounded-xl hover:bg-gray-50 transition-all duration-200 group">
                  <svg class="w-5 h-5 mr-2" viewBox="0 0 24 24">
                    <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                    <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                    <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                    <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                  </svg>
                  <span class="text-sm font-medium text-gray-700">Google</span>
                </a>
                
                <a href="<?= url('index.php?p=auth_facebook_callback') ?>" 
                   class="flex items-center justify-center px-4 py-3 border border-gray-300 rounded-xl hover:bg-gray-50 transition-all duration-200 group">
                  <svg class="w-5 h-5 mr-2" fill="#1877F2" viewBox="0 0 24 24">
                    <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                  </svg>
                  <span class="text-sm font-medium text-gray-700">Facebook</span>
                </a>
              </div>
            </div>
          <?php endif; ?>
          
          <?php if ($activeTab == 'signup'): ?>
            <div class="auth-tab-content" id="signup-content">
              <form class="space-y-6" action="./index.php?p=auth_submit" method="post" id="signupForm" novalidate>
                <?php csrfTokenField(); ?>
                
                <!-- Full Name Field -->
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">Full Name</label>
                  <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                      <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                      </svg>
                    </div>
                    <input type="text" 
                           name="name" 
                           id="signup-name"
                           class="<?= inputErrorClass('name', $validationErrors, 'w-full pl-10 pr-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200') ?>" 
                           placeholder="Enter your full name" 
                           value="<?= oldValue('name') ?>"
                           autocomplete="name" />
                  </div>
                  <?php displayFieldError('name', $validationErrors); ?>
                </div>
                
                <!-- Email Field -->
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">Email Address</label>
                  <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                      <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207"></path>
                      </svg>
                    </div>
                    <input type="email" 
                           name="email" 
                           id="signup-email"
                           class="<?= inputErrorClass('email', $validationErrors, 'w-full pl-10 pr-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200') ?>" 
                           placeholder="Enter your email" 
                           value="<?= oldValue('email') ?>"
                           autocomplete="email" />
                  </div>
                  <?php displayFieldError('email', $validationErrors); ?>
                </div>
                
                <!-- Password Field -->
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">Password</label>
                  <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                      <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 002 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                      </svg>
                    </div>
                    <input type="password" 
                           name="password" 
                           id="signup-password"
                           class="<?= inputErrorClass('password', $validationErrors, 'w-full pl-10 pr-12 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200') ?>" 
                           placeholder="Create a password"
                           autocomplete="new-password" />
                    <button type="button" 
                            onclick="togglePassword('signup-password')" 
                            class="absolute inset-y-0 right-0 pr-3 flex items-center">
                      <svg class="h-5 w-5 text-gray-400 hover:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                      </svg>
                    </button>
                  </div>
                  <!-- Password Strength Indicator -->
                  <div class="mt-2" id="signup-strength-container" style="display: none;">
                    <div class="password-strength bg-gray-200" id="signup-strength-bar" style="height: 4px; border-radius: 2px; transition: all 0.3s ease;"></div>
                    <p class="text-xs text-gray-500 mt-1" id="signup-strength-text">Password strength</p>
                  </div>
                  <?php displayFieldError('password', $validationErrors); ?>
                  <!-- Password Requirements -->
                  <div class="mt-2 space-y-1" id="signup-password-requirements" style="display: none;">
                    <p class="text-xs font-medium text-gray-600 mb-1">Password must contain:</p>
                    <div class="flex items-center text-xs" id="req-length">
                      <svg class="w-4 h-4 mr-1 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                      </svg>
                      <span class="text-gray-500">At least 6 characters</span>
                    </div>
                    <div class="flex items-center text-xs" id="req-letter">
                      <svg class="w-4 h-4 mr-1 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                      </svg>
                      <span class="text-gray-500">One letter (a-z or A-Z)</span>
                    </div>
                    <div class="flex items-center text-xs" id="req-number">
                      <svg class="w-4 h-4 mr-1 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                      </svg>
                      <span class="text-gray-500">One number (0-9)</span>
                    </div>
                  </div>
                </div>
                
                <!-- Confirm Password Field -->
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">Confirm Password</label>
                  <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                      <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 002 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                      </svg>
                    </div>
                    <input type="password" 
                           name="confirm_password" 
                           id="signup-confirm-password"
                           class="<?= inputErrorClass('confirm_password', $validationErrors, 'w-full pl-10 pr-12 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200') ?>" 
                           placeholder="Confirm your password"
                           autocomplete="new-password" />
                    <button type="button" 
                            onclick="togglePassword('signup-confirm-password')" 
                            class="absolute inset-y-0 right-0 pr-3 flex items-center">
                      <svg class="h-5 w-5 text-gray-400 hover:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                      </svg>
                    </button>
                  </div>
                  <?php displayFieldError('confirm_password', $validationErrors); ?>
                </div>
                
                <button type="submit" class="w-full bg-gradient-to-r from-blue-600 to-purple-600 text-white font-semibold py-3 px-4 rounded-xl hover:from-blue-700 hover:to-purple-700 transform hover:scale-[1.02] transition-all duration-200 shadow-lg">
                  Create Account
                </button>
              </form>
              
              <!-- Social Login Divider -->
              <div class="relative my-6">
                <div class="absolute inset-0 flex items-center">
                  <div class="w-full border-t border-gray-300"></div>
                </div>
                <div class="relative flex justify-center text-sm">
                  <span class="px-4 bg-white text-gray-500">Or sign up with</span>
                </div>
              </div>
              
              <!-- Social Login Buttons -->
              <div class="grid grid-cols-2 gap-4">
                <a href="<?= url('index.php?p=auth_google_callback') ?>" 
                   class="flex items-center justify-center px-4 py-3 border border-gray-300 rounded-xl hover:bg-gray-50 transition-all duration-200 group">
                  <svg class="w-5 h-5 mr-2" viewBox="0 0 24 24">
                    <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                    <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                    <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                    <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                  </svg>
                  <span class="text-sm font-medium text-gray-700">Google</span>
                </a>
                
                <a href="<?= url('index.php?p=auth_facebook_callback') ?>" 
                   class="flex items-center justify-center px-4 py-3 border border-gray-300 rounded-xl hover:bg-gray-50 transition-all duration-200 group">
                  <svg class="w-5 h-5 mr-2" fill="#1877F2" viewBox="0 0 24 24">
                    <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                  </svg>
                  <span class="text-sm font-medium text-gray-700">Facebook</span>
                </a>
              </div>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </section>

  <script>
    // Toggle password visibility
    function togglePassword(fieldId) {
        const field = document.getElementById(fieldId);
        field.type = field.type === 'password' ? 'text' : 'password';
    }
    
    // Client-side validation
    document.addEventListener('DOMContentLoaded', function() {
        // Password strength checker for signup
        const signupPassword = document.getElementById('signup-password');
        if (signupPassword) {
            const strengthBar = document.getElementById('signup-strength-bar');
            const strengthText = document.getElementById('signup-strength-text');
            const strengthContainer = document.getElementById('signup-strength-container');
            const requirementsContainer = document.getElementById('signup-password-requirements');
            const reqLength = document.getElementById('req-length');
            const reqLetter = document.getElementById('req-letter');
            const reqNumber = document.getElementById('req-number');
            
            signupPassword.addEventListener('input', function() {
                const password = this.value;
                
                // Show/hide strength indicator and requirements
                if (password.length > 0) {
                    strengthContainer.style.display = 'block';
                    requirementsContainer.style.display = 'block';
                } else {
                    strengthContainer.style.display = 'none';
                    requirementsContainer.style.display = 'none';
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
                
                let strength = 0;
                
                // Simple strength calculation
                if (password.length >= 6) strength++;
                if (password.length >= 8) strength++;
                if (password.match(/[a-z]/) && password.match(/[A-Z]/)) strength++;
                if (password.match(/[0-9]/)) strength++;
                if (password.match(/[^a-zA-Z0-9]/)) strength++;
                
                const colors = ['#ef4444', '#f59e0b', '#eab308', '#84cc16', '#22c55e'];
                const texts = ['Weak', 'Fair', 'Good', 'Strong', 'Very Strong'];
                const widths = ['20%', '40%', '60%', '80%', '100%'];
                
                strengthBar.style.width = widths[strength - 1] || '20%';
                strengthBar.style.backgroundColor = colors[strength - 1] || colors[0];
                strengthText.textContent = texts[strength - 1] || texts[0];
                strengthText.style.color = colors[strength - 1] || colors[0];
            });
        }
        
        function updateRequirement(element, isMet) {
            const svg = element.querySelector('svg');
            const span = element.querySelector('span');
            
            if (isMet) {
                // Show checkmark
                svg.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>';
                svg.classList.remove('text-gray-400');
                svg.classList.add('text-green-500');
                span.classList.remove('text-gray-500');
                span.classList.add('text-green-600');
            } else {
                // Show X mark
                svg.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>';
                svg.classList.remove('text-green-500');
                svg.classList.add('text-gray-400');
                span.classList.remove('text-green-600');
                span.classList.add('text-gray-500');
            }
        }
        
        // Login form validation
        const loginForm = document.getElementById('loginForm');
        if (loginForm) {
            loginForm.addEventListener('submit', function(e) {
                let isValid = true;
                
                // Clear previous errors
                clearFieldErrors();
                
                // Email validation
                const email = document.getElementById('login-email');
                if (!email.value.trim()) {
                    showFieldError(email, 'Email is required');
                    isValid = false;
                } else if (!isValidEmail(email.value)) {
                    showFieldError(email, 'Please enter a valid email address');
                    isValid = false;
                }
                
                // Password validation
                const password = document.getElementById('login-password');
                if (!password.value.trim()) {
                    showFieldError(password, 'Password is required');
                    isValid = false;
                }
                
                if (!isValid) {
                    e.preventDefault();
                    shakeForm(loginForm);
                }
            });
        }
        
        // Signup form validation
        const signupForm = document.getElementById('signupForm');
        if (signupForm) {
            signupForm.addEventListener('submit', function(e) {
                let isValid = true;
                
                // Clear previous errors
                clearFieldErrors();
                
                // Name validation
                const name = document.getElementById('signup-name');
                if (!name.value.trim()) {
                    showFieldError(name, 'Full name is required');
                    isValid = false;
                } else if (name.value.trim().length < 2) {
                    showFieldError(name, 'Name must be at least 2 characters long');
                    isValid = false;
                }
                
                // Email validation
                const email = document.getElementById('signup-email');
                if (!email.value.trim()) {
                    showFieldError(email, 'Email is required');
                    isValid = false;
                } else if (!isValidEmail(email.value)) {
                    showFieldError(email, 'Please enter a valid email address');
                    isValid = false;
                }
                
                // Password validation
                const password = document.getElementById('signup-password');
                if (!password.value) {
                    showFieldError(password, 'Password is required');
                    isValid = false;
                } else if (password.value.length < 6) {
                    showFieldError(password, 'Password must be at least 6 characters long');
                    isValid = false;
                }
                
                // Confirm password validation
                const confirmPassword = document.getElementById('signup-confirm-password');
                if (!confirmPassword.value) {
                    showFieldError(confirmPassword, 'Please confirm your password');
                    isValid = false;
                } else if (password.value !== confirmPassword.value) {
                    showFieldError(confirmPassword, 'Passwords do not match');
                    isValid = false;
                }
                
                if (!isValid) {
                    e.preventDefault();
                    shakeForm(signupForm);
                }
            });
        }
        
        // Real-time validation
        const inputs = document.querySelectorAll('input[type="email"], input[type="password"], input[type="text"]');
        inputs.forEach(input => {
            input.addEventListener('blur', function() {
                validateField(this);
            });
            
            input.addEventListener('input', function() {
                // Remove error styling on input
                if (this.classList.contains('field-error')) {
                    this.classList.remove('field-error');
                    const errorDiv = this.parentNode.parentNode.querySelector('.text-red-600');
                    if (errorDiv) {
                        errorDiv.remove();
                    }
                }
            });
        });
    });
    
    function validateField(field) {
        const fieldName = field.name;
        const value = field.value.trim();
        
        // Clear existing errors
        field.classList.remove('field-error', 'field-success');
        const existingError = field.parentNode.parentNode.querySelector('.text-red-600');
        if (existingError) {
            existingError.remove();
        }
        
        let isValid = true;
        let errorMessage = '';
        
        switch (fieldName) {
            case 'name':
                if (!value) {
                    errorMessage = 'Full name is required';
                    isValid = false;
                } else if (value.length < 2) {
                    errorMessage = 'Name must be at least 2 characters long';
                    isValid = false;
                }
                break;
                
            case 'email':
                if (!value) {
                    errorMessage = 'Email is required';
                    isValid = false;
                } else if (!isValidEmail(value)) {
                    errorMessage = 'Please enter a valid email address';
                    isValid = false;
                }
                break;
                
            case 'password':
                if (!field.value) {
                    errorMessage = 'Password is required';
                    isValid = false;
                } else if (field.id === 'signup-password' && field.value.length < 6) {
                    errorMessage = 'Password must be at least 6 characters long';
                    isValid = false;
                }
                break;
                
            case 'confirm_password':
                const passwordField = document.getElementById('signup-password');
                if (!field.value) {
                    errorMessage = 'Please confirm your password';
                    isValid = false;
                } else if (passwordField && field.value !== passwordField.value) {
                    errorMessage = 'Passwords do not match';
                    isValid = false;
                }
                break;
        }
        
        if (!isValid) {
            showFieldError(field, errorMessage);
        } else if (value) {
            field.classList.add('field-success');
        }
    }
    
    function showFieldError(field, message) {
        field.classList.add('field-error');
        
        const errorDiv = document.createElement('div');
        errorDiv.className = 'mt-1 text-sm text-red-600 flex items-center';
        errorDiv.innerHTML = `
            <svg class="w-4 h-4 mr-1 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
            </svg>
            ${message}
        `;
        
        field.parentNode.parentNode.appendChild(errorDiv);
    }
    
    function clearFieldErrors() {
        const errorDivs = document.querySelectorAll('.text-red-600');
        errorDivs.forEach(div => div.remove());
        
        const errorFields = document.querySelectorAll('.field-error');
        errorFields.forEach(field => field.classList.remove('field-error'));
    }
    
    function isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }
    
    function shakeForm(form) {
        form.classList.add('shake');
        setTimeout(() => {
            form.classList.remove('shake');
        }, 500);
    }
  </script>
  </body>
  </html>