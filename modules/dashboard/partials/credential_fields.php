<?php
/**
 * Dynamic Credential Fields Based on Listing Category
 */

$category = strtolower($category);
?>

<div class="space-y-4">
    
    <?php if ($category === 'website'): ?>
        <!-- Website Credentials -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
                <i class="fas fa-server text-blue-500 mr-1"></i> Hosting Provider *
            </label>
            <input type="text" name="hosting_provider" required
                   class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                   placeholder="e.g., Hostinger, GoDaddy, Bluehost">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
                <i class="fas fa-user text-blue-500 mr-1"></i> Hosting Username *
            </label>
            <input type="text" name="hosting_username" required
                   class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                   placeholder="Hosting account username">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
                <i class="fas fa-lock text-blue-500 mr-1"></i> Hosting Password *
            </label>
            <div class="relative">
                <input type="password" id="hosting_password" name="hosting_password" required
                       class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent pr-12"
                       placeholder="Hosting account password">
                <button type="button" onclick="togglePassword('hosting_password')"
                        class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-700">
                    <i class="fas fa-eye"></i>
                </button>
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
                <i class="fas fa-globe text-blue-500 mr-1"></i> Domain Registrar
            </label>
            <input type="text" name="domain_registrar"
                   class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                   placeholder="e.g., Namecheap, GoDaddy">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
                <i class="fas fa-user text-blue-500 mr-1"></i> Domain Username
            </label>
            <input type="text" name="domain_username"
                   class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                   placeholder="Domain registrar username">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
                <i class="fas fa-lock text-blue-500 mr-1"></i> Domain Password
            </label>
            <div class="relative">
                <input type="password" id="domain_password" name="domain_password"
                       class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent pr-12"
                       placeholder="Domain registrar password">
                <button type="button" onclick="togglePassword('domain_password')"
                        class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-700">
                    <i class="fas fa-eye"></i>
                </button>
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
                <i class="fas fa-wordpress text-blue-500 mr-1"></i> CMS Admin URL
            </label>
            <input type="url" name="cms_admin_url"
                   class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                   placeholder="e.g., https://example.com/wp-admin">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
                <i class="fas fa-user text-blue-500 mr-1"></i> CMS Username
            </label>
            <input type="text" name="cms_username"
                   class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                   placeholder="WordPress/CMS admin username">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
                <i class="fas fa-lock text-blue-500 mr-1"></i> CMS Password
            </label>
            <div class="relative">
                <input type="password" id="cms_password" name="cms_password"
                       class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent pr-12"
                       placeholder="WordPress/CMS admin password">
                <button type="button" onclick="togglePassword('cms_password')"
                        class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-700">
                    <i class="fas fa-eye"></i>
                </button>
            </div>
        </div>

    <?php elseif ($category === 'youtube'): ?>
        <!-- YouTube Channel Credentials -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
                <i class="fab fa-youtube text-red-500 mr-1"></i> Channel Email *
            </label>
            <input type="email" name="channel_email" required
                   class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                   placeholder="YouTube channel email address">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
                <i class="fas fa-lock text-red-500 mr-1"></i> Channel Password *
            </label>
            <div class="relative">
                <input type="password" id="channel_password" name="channel_password" required
                       class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent pr-12"
                       placeholder="YouTube channel password">
                <button type="button" onclick="togglePassword('channel_password')"
                        class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-700">
                    <i class="fas fa-eye"></i>
                </button>
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
                <i class="fas fa-envelope text-red-500 mr-1"></i> Recovery Email *
            </label>
            <input type="email" name="recovery_email" required
                   class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                   placeholder="Recovery email address">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
                <i class="fas fa-phone text-red-500 mr-1"></i> Recovery Phone
            </label>
            <input type="tel" name="recovery_phone"
                   class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                   placeholder="Recovery phone number">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
                <i class="fas fa-shield-alt text-red-500 mr-1"></i> 2FA Backup Codes
            </label>
            <textarea name="backup_codes" rows="3"
                      class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent font-mono text-sm"
                      placeholder="Paste 2FA backup codes (one per line)"></textarea>
        </div>

    <?php elseif (in_array($category, ['instagram', 'facebook', 'twitter', 'tiktok', 'social'])): ?>
        <!-- Social Media Account Credentials -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
                <i class="fas fa-hashtag text-purple-500 mr-1"></i> Platform *
            </label>
            <select name="platform" required
                    class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                <option value="">Select Platform</option>
                <option value="instagram">Instagram</option>
                <option value="facebook">Facebook</option>
                <option value="twitter">Twitter/X</option>
                <option value="tiktok">TikTok</option>
                <option value="linkedin">LinkedIn</option>
                <option value="other">Other</option>
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
                <i class="fas fa-user text-purple-500 mr-1"></i> Username/Handle *
            </label>
            <input type="text" name="account_username" required
                   class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                   placeholder="@username">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
                <i class="fas fa-envelope text-purple-500 mr-1"></i> Account Email *
            </label>
            <input type="email" name="account_email" required
                   class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                   placeholder="Account email address">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
                <i class="fas fa-lock text-purple-500 mr-1"></i> Account Password *
            </label>
            <div class="relative">
                <input type="password" id="account_password" name="account_password" required
                       class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent pr-12"
                       placeholder="Account password">
                <button type="button" onclick="togglePassword('account_password')"
                        class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-700">
                    <i class="fas fa-eye"></i>
                </button>
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
                <i class="fas fa-envelope text-purple-500 mr-1"></i> Email Password
            </label>
            <div class="relative">
                <input type="password" id="email_password" name="email_password"
                       class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent pr-12"
                       placeholder="Email account password">
                <button type="button" onclick="togglePassword('email_password')"
                        class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-700">
                    <i class="fas fa-eye"></i>
                </button>
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
                <i class="fas fa-phone text-purple-500 mr-1"></i> Phone Number
            </label>
            <input type="tel" name="phone_number"
                   class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                   placeholder="Linked phone number">
        </div>

    <?php else: ?>
        <!-- Generic Credentials for Other Categories -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
                <i class="fas fa-tag text-gray-500 mr-1"></i> Service/Platform Name *
            </label>
            <input type="text" name="service_name" required
                   class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                   placeholder="Name of the service or platform">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
                <i class="fas fa-user text-gray-500 mr-1"></i> Username/Email *
            </label>
            <input type="text" name="username" required
                   class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                   placeholder="Username or email">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
                <i class="fas fa-lock text-gray-500 mr-1"></i> Password *
            </label>
            <div class="relative">
                <input type="password" id="password" name="password" required
                       class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent pr-12"
                       placeholder="Account password">
                <button type="button" onclick="togglePassword('password')"
                        class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-700">
                    <i class="fas fa-eye"></i>
                </button>
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
                <i class="fas fa-link text-gray-500 mr-1"></i> Access URL
            </label>
            <input type="url" name="access_url"
                   class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                   placeholder="Login or access URL">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
                <i class="fas fa-info-circle text-gray-500 mr-1"></i> Additional Access Info
            </label>
            <textarea name="access_info" rows="3"
                      class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                      placeholder="Any other credentials or access information"></textarea>
        </div>
    <?php endif; ?>

</div>
