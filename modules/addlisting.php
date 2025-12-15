<?php
require_once __DIR__ . '/../middlewares/auth.php';
require_login();
require_profile_completion();
?>
<div class="container mx-auto px-4 py-8 max-w-6xl">
    <div class="mb-8">
        <h1 class="text-2xl font-bold text-center text-gray-800">Create New Listing</h1>
        <!-- <p class="text-gray-600 mt-2">Submit details of your website, channel, app, or SaaS for verification and marketplace approval.</p> -->
    </div>

    <div class="flex flex-col lg:flex-row gap-8">
        <div class="lg:w-2/3">
            <div id="form-step-1" class="form-step active bg-white rounded-lg shadow-md p-6 mb-6">
                <h2 class="text-xl font-semibold text-gray-800 mb-4">1 Basic Information</h2>
                <div class="mb-6">
                    <label for="listing-title" class="block text-sm font-medium text-gray-700 mb-1">Listing Title *</label>
                    <input type="text" id="listing-title" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500" placeholder="e.g., Profitable E-commerce Store in Fashion Niche">
                </div>
                <div class="mb-6">
                    <label for="category" class="block text-sm font-medium text-gray-700 mb-1">Category *</label>
                    <select id="category" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Select a category</option>
                        <option value="ecommerce">E-commerce</option>
                        <option value="saas">SaaS</option>
                        <option value="content">Content Website</option>
                        <option value="app">Mobile App</option>
                        <option value="youtube">YouTube Channel</option>
                    </select>
                </div>
                <div class="mb-6">
                    <label for="url" class="block text-sm font-medium text-gray-700 mb-1">Website/Channel URL *</label>
                    <input type="text" id="url" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500" placeholder="https://example.com or https://youtube.com/channel/...">
                </div>
                <div class="mb-6">
                    <label for="subcategory" class="block text-sm font-medium text-gray-700 mb-1">Sub-category *</label>
                    <select id="subcategory" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Select sub-category</option>
                        <option value="fashion">Fashion</option>
                        <option value="tech">Technology</option>
                        <option value="health">Health & Fitness</option>
                        <option value="finance">Finance</option>
                        <option value="education">Education</option>
                    </select>
                </div>
            </div>
            <div id="form-step-2" class="form-step bg-white rounded-lg shadow-md p-6 mb-6">
                <h2 class="text-xl font-semibold text-gray-800 mb-4">2 Financial Details</h2>
                <div class="mb-6">
                    <label for="monthly-revenue" class="block text-sm font-medium text-gray-700 mb-1">Average Monthly Revenue ($) *</label>
                    <input type="number" id="monthly-revenue" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500" placeholder="e.g., 5000">
                </div>
                <div class="mb-6">
                    <label for="monthly-profit" class="block text-sm font-medium text-gray-700 mb-1">Average Monthly Profit ($) *</label>
                    <input type="number" id="monthly-profit" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500" placeholder="e.g., 3500">
                </div>
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Revenue Streams</label>
                    <div class="space-y-2">
                        <div class="flex items-center">
                            <input type="checkbox" id="ads" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                            <label for="ads" class="ml-2 block text-sm text-gray-700">Advertising</label>
                        </div>
                        <div class="flex items-center">
                            <input type="checkbox" id="affiliate" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                            <label for="affiliate" class="ml-2 block text-sm text-gray-700">Affiliate Marketing</label>
                        </div>
                        <div class="flex items-center">
                            <input type="checkbox" id="subscription" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                            <label for="subscription" class="ml-2 block text-sm text-gray-700">Subscription</label>
                        </div>
                        <div class="flex items-center">
                            <input type="checkbox" id="product-sales" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                            <label for="product-sales" class="ml-2 block text-sm text-gray-700">Product Sales</label>
                        </div>
                    </div>
                </div>
            </div>
            <div id="form-step-3" class="form-step bg-white rounded-lg shadow-md p-6 mb-6">
                <h2 class="text-xl font-semibold text-gray-800 mb-4">3 Upload Proofs</h2>
                <p class="text-sm text-gray-600 mb-6">Upload specific proof documents based on your listing type. All proofs are required for verification.</p>

                <!-- Cover Photo -->
                <div class="mb-8">
                    <h3 class="text-lg font-medium text-gray-700 mb-2 flex items-center">
                        <i class="fas fa-image text-blue-500 mr-2"></i>
                        Cover Photo/Thumbnail
                    </h3>
                    <p class="text-xs text-gray-500 mb-3">Upload a professional cover image that represents your business</p>
                    <div class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center hover:border-blue-400 transition-colors">
                        <i class="fas fa-cloud-upload-alt text-gray-400 text-3xl mb-2"></i>
                        <p class="text-sm text-gray-600">Upload cover image</p>
                        <p class="text-xs text-gray-500 mt-1">PNG, JPG up to 5MB</p>
                        <button type="button" class="mt-4 px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 text-sm">Select File</button>
                    </div>
                </div>

                <!-- Website/App Proofs -->
                <div id="website-proofs" class="proof-section">
                    <div class="border-2 border-dashed border-gray-300 rounded-md p-6 text-center">
                        <i class="fas fa-chart-line text-gray-400 text-3xl mb-2"></i>
                        <p class="text-sm text-gray-600">GA Screenshots</p>
                        <button type="button" class="mt-4 px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 text-sm">Upload Files</button>
                    </div>
                </div>
                <div class="mb-6">
                    <h3 class="text-lg font-medium text-gray-700 mb-2">Other Files</h3>
                    <div class="border-2 border-dashed border-gray-300 rounded-md p-6 text-center">
                        <i class="fas fa-file text-gray-400 text-3xl mb-2"></i>
                        <p class="text-sm text-gray-600">Optional files</p>
                        <button type="button" class="mt-4 px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 text-sm">Upload Files</button>
                    </div>
                </div>
            </div>
            <div id="form-step-4" class="form-step bg-white rounded-lg shadow-md p-6 mb-6">
                <h2 class="text-xl font-semibold text-gray-800 mb-4">4 Pricing Setup</h2>
                <div class="mb-6">
                    <label for="buy-now-price" class="block text-sm font-medium text-gray-700 mb-1">Buy Now Price ($) *</label>
                    <input type="number" id="buy-now-price" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500" value="150000">
                </div>
                <div class="mb-6">
                    <div class="flex items-center">
                        <input type="checkbox" id="allow-offers" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                        <label for="allow-offers" class="ml-2 block text-sm font-medium text-gray-700">Allow Offers</label>
                    </div>
                    <p class="text-sm text-gray-500 mt-1 ml-6">Let buyers make offers below asking price</p>
                </div>

                <hr class="my-6">
                <div class="mb-6">
                    <label for="listing-duration" class="block text-sm font-medium text-gray-700 mb-1">Listing Duration (days)</label>
                    <input type="number" id="listing-duration" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500" value="30">
                </div>
                <div class="mb-6">
                    <label for="min-offer" class="block text-sm font-medium text-gray-700 mb-1">Minimum Offer Allowed ($)</label>
                    <input type="number" id="min-offer" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500" value="100000">
                </div>
            </div>
            <div id="form-step-5" class="form-step bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-semibold text-gray-800 mb-4">5 Description</h2>
                <div class="mb-6">
                    <label for="full-description" class="block text-sm font-medium text-gray-700 mb-1">Full Description *</label>
                    <textarea id="full-description" rows="10" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500" placeholder="Provide a comprehensive description of your listing. Include:
- Business overview and history
- Revenue sources and monetization
- Traffic sources and marketing strategies
- Key metrics and growth trends
- Unique selling points
- Reason for selling
- What's included in the sale
- Future growth opportunities"></textarea>
                </div>
            </div>
            <div class="flex justify-between mt-8">
                <button id="prev-btn" class="px-6 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 hidden">
                    <i class="fas fa-arrow-left mr-2"></i> Back
                </button>
                <button id="next-btn" class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                    Save & Continue <i class="fas fa-arrow-right ml-2"></i>
                </button>
            </div>
        </div>
        <div class="lg:w-1/3">
            <div class="bg-white rounded-lg shadow-md p-6 sticky top-8">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">How it Works</h3>

                <ol class="list-decimal pl-5 space-y-3 text-sm text-gray-600">
                    <li>Basic Information</li>
                    <li>Financial Details</li>
                    <li>Upload Proofs</li>
                    <li>Pricing Setup</li>
                    <li>Description</li>
                </ol>

                <div class="mt-4 text-sm text-gray-500">
                    <p>Review takes 2-3 business days</p>
                </div>

                <div class="mt-8">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Pro Tips</h3>
                    <ul class="space-y-2 text-sm text-gray-600">
                        <li class="flex items-start">
                            <i class="fas fa-lightbulb text-yellow-500 mt-1 mr-2"></i>
                            <span>Use high-quality images to showcase your business</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-lightbulb text-yellow-500 mt-1 mr-2"></i>
                            <span>Be transparent about financials and traffic</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-lightbulb text-yellow-500 mt-1 mr-2"></i>
                            <span>Highlight growth potential and unique advantages</span>
                        </li>
                    </ul>
                </div>

                <div class="mt-8 pt-6 border-t border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Talk with Us</h3>
                    <button class="w-full px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 flex items-center justify-center">
                        <i class="fas fa-comment-alt mr-2"></i> Contact Support
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>