  <div class="max-w-4xl mx-auto mt-10">
    <div class="bg-white rounded-lg shadow-md">
      <div class="flex items-center justify-between px-6 py-4 border-b">
        <h2 class="text-2xl font-semibold">Settings</h2>
        <button class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 flex items-center gap-2">
          <i class="fa-solid fa-floppy-disk"></i>
          Save Changes
        </button>
      </div>

      <div class="px-6 pt-6">
        <div class="flex gap-6 border-b pb-4">
          <button class="tab-btn text-sm text-gray-800 pb-3 border-b-2 border-blue-600" data-tab="general">General</button>
          <button class="tab-btn text-sm text-gray-500 hover:text-gray-700 pb-3" data-tab="security">Security</button>
          <button class="tab-btn text-sm text-gray-500 hover:text-gray-700 pb-3" data-tab="payments">Payments</button>
          <button class="tab-btn text-sm text-gray-500 hover:text-gray-700 pb-3" data-tab="bidding">Bidding Control</button>
          <button class="tab-btn text-sm text-gray-500 hover:text-gray-700 pb-3" data-tab="notifications">Notifications</button>
        </div>
      </div>

      <div class="px-6 py-8">
        <form class="space-y-6 tab-content" id="general">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Platform Name</label>
            <input type="text" placeholder="Digital Marketplace" class="w-full border rounded px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-200" />
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Platform Logo</label>
            <div class="mt-1 flex items-center">
              <label for="file-upload" class="relative cursor-pointer rounded-md font-medium text-blue-600 focus-within:outline-none w-full">
                <div class="w-full border-2 border-dashed border-gray-300 rounded-md p-8 text-center hover:border-gray-400 bg-gray-50">
                  <div class="mx-auto flex items-center justify-center h-24 w-full">
                    <div class="text-gray-400">
                      <i class="fa-solid fa-cloud-arrow-up fa-2x"></i>
                    </div>
                  </div>
                  <p class="mt-2 text-sm text-gray-600">Click to upload or drag and drop<br /><span class="text-xs text-gray-400">PNG, JPG up to 2MB</span></p>
                </div>
              </label>
              <input id="file-upload" type="file" accept="image/*" class="sr-only" />
            </div>
            <div id="preview" class="mt-3 hidden">
              <p class="text-sm text-gray-500">Preview:</p>
              <img id="preview-img" src="#" alt="logo preview" class="h-20 w-20 object-contain mt-2 border rounded" />
            </div>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Contact Email</label>
            <input type="email" placeholder="support@marketplace.com" class="w-full border rounded px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-200" />
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Support Phone</label>
            <input type="text" placeholder="+1 (555) 123-4567" class="w-full border rounded px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-200" />
          </div>
        </form>

        <div class="space-y-6 tab-content hidden" id="security">
          <div class="flex items-center justify-between">
            <div>
              <h3 class="font-medium text-gray-800">Two-Factor Authentication</h3>
              <p class="text-sm text-gray-500">Require 2FA for all admin accounts</p>
            </div>
            <label class="inline-flex items-center cursor-pointer">
              <input type="checkbox" checked class="sr-only peer">
              <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:bg-blue-600 transition"></div>
            </label>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Minimum Password Length</label>
            <select class="w-full border rounded px-4 py-2">
              <option>8 characters</option>
              <option>10 characters</option>
              <option>12 characters</option>
            </select>
          </div>

          <div class="flex items-center justify-between">
            <div>
              <h3 class="font-medium text-gray-800">Require Special Characters</h3>
              <p class="text-sm text-gray-500">Passwords must contain special characters</p>
            </div>
            <label class="inline-flex items-center cursor-pointer">
              <input type="checkbox" checked class="sr-only peer">
              <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:bg-blue-600 transition"></div>
            </label>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Session Timeout (minutes)</label>
            <select class="w-full border rounded px-4 py-2">
              <option>15 minutes</option>
              <option>30 minutes</option>
              <option>60 minutes</option>
            </select>
          </div>
        </div>
        <div class="space-y-6 tab-content hidden" id="payments">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Default Commission Rate (%)</label>
            <input type="number" value="7" class="w-20 border rounded px-4 py-2" />
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Auto Payout Period</label>
            <select class="w-full border rounded px-4 py-2">
              <option>Weekly</option>
              <option>Monthly</option>
            </select>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Minimum Payout Amount</label>
            <input type="number" value="100" class="w-full border rounded px-4 py-2" />
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Payment Methods</label>
            <div class="space-y-2">
              <label class="flex items-center gap-2"><input type="checkbox" checked> PayPal</label>
              <label class="flex items-center gap-2"><input type="checkbox" checked> Stripe</label>
              <label class="flex items-center gap-2"><input type="checkbox"> Bank Transfer</label>
              <label class="flex items-center gap-2"><input type="checkbox"> Cryptocurrency</label>
            </div>
          </div>
        </div>

        <div class="space-y-6 tab-content hidden" id="bidding">
          <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">
            <h3 class="font-medium text-blue-900 mb-2">Bidding Control Settings</h3>
            <p class="text-sm text-blue-700">Configure bidding rules, down payment percentages, and reserved amounts for the platform.</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Bid Increment Type</label>
            <select id="bid_increment_type" class="w-full border rounded px-4 py-2">
              <option value="fixed">Fixed Amount ($)</option>
              <option value="percentage">Percentage (%)</option>
            </select>
          </div>

          <div id="fixed_increment_section">
            <label class="block text-sm font-medium text-gray-700 mb-2">Fixed Bid Increment ($)</label>
            <input type="number" id="bid_increment_fixed" value="10.00" step="0.01" min="1" class="w-full border rounded px-4 py-2" />
            <p class="text-xs text-gray-500 mt-1">Each new bid must be at least this amount higher than the current bid</p>
          </div>

          <div id="percentage_increment_section" class="hidden">
            <label class="block text-sm font-medium text-gray-700 mb-2">Percentage Bid Increment (%)</label>
            <input type="number" id="bid_increment_percentage" value="5.00" step="0.01" min="1" max="100" class="w-full border rounded px-4 py-2" />
            <p class="text-xs text-gray-500 mt-1">Each new bid must be this percentage higher than the current bid</p>
          </div>

          <hr class="my-6" />

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Default Minimum Down Payment (%)</label>
            <input type="number" id="default_min_down_payment" value="50.00" step="0.01" min="1" max="100" class="w-full border rounded px-4 py-2" />
            <p class="text-xs text-gray-500 mt-1">Default minimum down payment percentage (sellers can reduce to 1%)</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Down Payment Warning Threshold (%)</label>
            <input type="number" id="down_payment_warning_threshold" value="10.00" step="0.01" min="1" max="100" class="w-full border rounded px-4 py-2" />
            <p class="text-xs text-gray-500 mt-1">Log warning if down payment is below this percentage</p>
          </div>

          <hr class="my-6" />

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Auction Extension Time (minutes)</label>
            <input type="number" id="auction_extension_minutes" value="2" min="1" max="60" class="w-full border rounded px-4 py-2" />
            <p class="text-xs text-gray-500 mt-1">Extend auction by this many minutes if bid placed near end time</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Default Reserved Amount (%)</label>
            <input type="number" id="default_reserved_amount_percentage" value="0.00" step="0.01" min="0" max="100" class="w-full border rounded px-4 py-2" />
            <p class="text-xs text-gray-500 mt-1">Default reserved amount as percentage of starting price (0 = no reserve)</p>
          </div>

          <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mt-4">
            <h4 class="font-medium text-yellow-900 mb-2">⚠️ Important Notes:</h4>
            <ul class="text-sm text-yellow-700 space-y-1 list-disc list-inside">
              <li>Sellers can set their own reserved amount (minimum price)</li>
              <li>Items will NOT sell if highest bid is below reserved amount</li>
              <li>Sellers can reduce down payment to as low as 1%</li>
              <li>All changes are logged securely for audit purposes</li>
            </ul>
          </div>

          <button id="save_bidding_settings" class="w-full bg-blue-600 text-white px-4 py-3 rounded hover:bg-blue-700 font-medium">
            Save Bidding Settings
          </button>
        </div>

        <div class="space-y-6 tab-content hidden" id="notifications">
          <div class="flex items-center justify-between">
            <div>
              <h3 class="font-medium text-gray-800">Email Notifications</h3>
              <p class="text-sm text-gray-500">Send email notifications for important events</p>
            </div>
            <label class="inline-flex items-center cursor-pointer">
              <input type="checkbox" checked class="sr-only peer">
              <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:bg-blue-600 transition"></div>
            </label>
          </div>

          <div class="flex items-center justify-between">
            <div>
              <h3 class="font-medium text-gray-800">SMS Notifications</h3>
              <p class="text-sm text-gray-500">Send SMS notifications for critical alerts</p>
            </div>
            <label class="inline-flex items-center cursor-pointer">
              <input type="checkbox" class="sr-only peer">
              <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:bg-blue-600 transition"></div>
            </label>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Notification Types</label>
            <div class="space-y-2">
              <label class="flex items-center gap-2"><input type="checkbox" checked> New user registrations</label>
              <label class="flex items-center gap-2"><input type="checkbox" checked> New listing submissions</label>
              <label class="flex items-center gap-2"><input type="checkbox" checked> Payment transactions</label>
              <label class="flex items-center gap-2"><input type="checkbox" checked> Dispute reports</label>
              <label class="flex items-center gap-2"><input type="checkbox"> System maintenance</label>
              <label class="flex items-center gap-2"><input type="checkbox" checked> Security alerts</label>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script>
    const tabButtons = document.querySelectorAll('.tab-btn');
    const tabContents = document.querySelectorAll('.tab-content');

    tabButtons.forEach(btn => {
      btn.addEventListener('click', () => {
        tabButtons.forEach(b => b.classList.remove('text-gray-800', 'border-b-2', 'border-blue-600'));
        tabButtons.forEach(b => b.classList.add('text-gray-500'));
        btn.classList.remove('text-gray-500');
        btn.classList.add('text-gray-800', 'border-b-2', 'border-blue-600');

        const tab = btn.getAttribute('data-tab');
        tabContents.forEach(content => {
          content.classList.add('hidden');
        });
        document.getElementById(tab).classList.remove('hidden');
      });
    });
    const fileInput = document.getElementById('file-upload');
    const preview = document.getElementById('preview');
    const previewImg = document.getElementById('preview-img');

    if (fileInput) {
      fileInput.addEventListener('change', (e) => {
        const file = e.target.files[0];
        if (!file) return;
        const url = URL.createObjectURL(file);
        previewImg.src = url;
        preview.classList.remove('hidden');
      });
    }

    // Bidding Control Settings Logic
    const bidIncrementType = document.getElementById('bid_increment_type');
    const fixedSection = document.getElementById('fixed_increment_section');
    const percentageSection = document.getElementById('percentage_increment_section');

    if (bidIncrementType) {
      bidIncrementType.addEventListener('change', (e) => {
        if (e.target.value === 'fixed') {
          fixedSection.classList.remove('hidden');
          percentageSection.classList.add('hidden');
        } else {
          fixedSection.classList.add('hidden');
          percentageSection.classList.remove('hidden');
        }
      });
    }

    // Load current bidding settings
    async function loadBiddingSettings() {
      try {
        const response = await fetch('/api/enhanced_bidding_api.php?action=get_bidding_settings');
        const data = await response.json();

        if (data.success) {
          document.getElementById('bid_increment_type').value = data.settings.bid_increment_type || 'fixed';
          document.getElementById('bid_increment_fixed').value = data.settings.bid_increment_fixed || '10.00';
          document.getElementById('bid_increment_percentage').value = data.settings.bid_increment_percentage || '5.00';
          document.getElementById('default_min_down_payment').value = data.settings.default_min_down_payment || '50.00';
          document.getElementById('down_payment_warning_threshold').value = data.settings.down_payment_warning_threshold || '10.00';
          document.getElementById('auction_extension_minutes').value = data.settings.auction_extension_minutes || '2';
          document.getElementById('default_reserved_amount_percentage').value = data.settings.default_reserved_amount_percentage || '0.00';

          // Trigger change event to show correct section
          bidIncrementType.dispatchEvent(new Event('change'));
        }
      } catch (error) {
        console.error('Error loading bidding settings:', error);
      }
    }

    // Save bidding settings
    const saveBiddingBtn = document.getElementById('save_bidding_settings');
    if (saveBiddingBtn) {
      saveBiddingBtn.addEventListener('click', async () => {
        const settings = {
          bid_increment_type: document.getElementById('bid_increment_type').value,
          bid_increment_fixed: document.getElementById('bid_increment_fixed').value,
          bid_increment_percentage: document.getElementById('bid_increment_percentage').value,
          default_min_down_payment: document.getElementById('default_min_down_payment').value,
          down_payment_warning_threshold: document.getElementById('down_payment_warning_threshold').value,
          auction_extension_minutes: document.getElementById('auction_extension_minutes').value,
          default_reserved_amount_percentage: document.getElementById('default_reserved_amount_percentage').value
        };

        try {
          saveBiddingBtn.disabled = true;
          saveBiddingBtn.textContent = 'Saving...';

          const response = await fetch('/api/enhanced_bidding_api.php?action=update_bidding_settings', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json'
            },
            body: JSON.stringify(settings)
          });

          const data = await response.json();

          if (data.success) {
            alert('✅ Bidding settings saved successfully!');
          } else {
            alert('❌ Error: ' + data.message);
          }
        } catch (error) {
          alert('❌ Error saving settings: ' + error.message);
        } finally {
          saveBiddingBtn.disabled = false;
          saveBiddingBtn.textContent = 'Save Bidding Settings';
        }
      });
    }

    // Load settings on page load
    loadBiddingSettings();

    // Ensure API_BASE_PATH is set
    // Define BASE constant globally
    // const BASE = "<?php echo BASE; ?>"; // Already defined in dashboard.php
    console.log('🔧 BASE constant defined:', BASE);

    if (!window.API_BASE_PATH) {
      const path = window.location.pathname;
      window.API_BASE_PATH = (path.includes('/marketplace/') ? '/marketplace' : '') + '/api';
      console.log('🔧 [Settings] API_BASE_PATH:', window.API_BASE_PATH);
    }

    // Polling Integration for SuperAdmin Settings
    console.log('🚀 SuperAdmin Settings polling initialization started');

    if (typeof startPolling !== 'undefined') {
      console.log('✅ Starting polling for settings page');

      try {
        startPolling({
          // Monitor for system-wide changes
          orders: (newOrders) => {
            if (newOrders.length > 0) {
              console.log('💳 New orders detected, settings may need attention');
            }
          }
        });

        console.log('✅ Polling started successfully for SuperAdmin Settings');
      } catch (error) {
        console.error('❌ Error starting polling:', error);
      }
    } else {
      console.warn('⚠️ startPolling function not found - polling.js may not be loaded');
    }
  </script>