  <div class="min-h-screen flex items-center justify-center bg-gray-100">
    <div class="bg-white shadow-md rounded-lg w-full max-w-md p-6">
      <h2 class="text-2xl font-bold text-center" style="color:#9333EA;">
        Reset Password
      </h2>
      <p class="text-center text-gray-600 mt-1 mb-6">
        Create a new password for your account
      </p>
      <form>
        <div class="mb-4">
          <label class="block text-gray-700 mb-2">New Password</label>
          <div class="relative">
            <input type="password" placeholder="Enter new password"
              class="w-full border rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-purple-600"
              style="border-color:#9333EA;" />
            <span class="absolute right-3 top-2.5 text-gray-400">
              <i class="fas fa-eye"></i>
            </span>
          </div>
        </div>
        <div class="mb-6">
          <label class="block text-gray-700 mb-2">Confirm Password</label>
          <div class="relative">
            <input type="password" placeholder="Confirm new password"
              class="w-full border rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-purple-600"
              style="border-color:#9333EA;" />
            <span class="absolute right-3 top-2.5 text-gray-400">
              <i class="fas fa-eye"></i>
            </span>
          </div>
        </div>
        <button type="submit" 
          class="w-full text-white font-semibold py-2 px-4 rounded-lg shadow-md transition"
          style="background: linear-gradient(90deg, #9333EA, #2563EB);">
          Reset Password
        </button>
      </form>
      <div class="text-center mt-4">
        <a href="#" class="text-sm font-medium" style="color:#2563EB;">
          ← Back to Login
        </a>
      </div>
      <p class="mt-6 text-xs text-gray-500 text-center">
        <i class="fas fa-shield-alt mr-1"></i>
        Password must be at least 8 characters long and contain a mix of letters and numbers.
      </p>
    </div>
  </div>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
