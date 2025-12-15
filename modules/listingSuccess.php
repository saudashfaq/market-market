<?php
require_once __DIR__ . '/../config.php';
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: " . url('public/index.php'));
    exit;
}
$listing_id = $_GET['id'];
?>

<div class="bg-gradient-to-br from-green-50 via-emerald-50 to-blue-50 min-h-screen py-12 px-4">
    <div class="max-w-2xl mx-auto">
        <!-- Success Card -->
        <div class="bg-white rounded-3xl shadow-2xl overflow-hidden">
            <!-- Header Section -->
            <div class="bg-gradient-to-r from-green-500 to-emerald-600 px-8 py-6 text-center">
                <div class="w-24 h-24 bg-white rounded-full flex items-center justify-center mx-auto mb-4 shadow-lg">
                    <i class="fas fa-check text-green-500 text-4xl"></i>
                </div>
                <h1 class="text-3xl font-bold text-white mb-2">Submission Successful!</h1>
                <p class="text-green-100 text-lg">Your listing has been received and is now under review</p>
            </div>
            <!-- Content Section -->
            <div class="px-8 py-8">


                <!-- Professional Team Contact Message -->
                <div class="bg-gradient-to-r from-blue-50 to-indigo-50 border-l-4 border-blue-500 rounded-lg p-6 mb-6">
                    <div class="flex items-start">
                        <div class="flex-shrink-0">
                            <i class="fas fa-users text-blue-600 text-xl mt-1"></i>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-lg font-semibold text-gray-800 mb-3">What Happens Next?</h3>
                            <div class="space-y-3 text-gray-700">
                                <p class="flex items-start">
                                    <i class="fas fa-clock text-blue-500 mr-3 mt-1 text-sm"></i>
                                    <span><strong>Review Process:</strong> Our expert team will thoroughly review your listing within the next 24-48 hours to ensure it meets our quality standards.</span>
                                </p>
                                <p class="flex items-start">
                                    <i class="fas fa-phone text-blue-500 mr-3 mt-1 text-sm"></i>
                                    <span><strong>Personal Contact:</strong> A dedicated member from our verification team will reach out to you via phone or email to discuss your listing details and guide you through the next steps.</span>
                                </p>
                                <p class="flex items-start">
                                    <i class="fas fa-shield-check text-blue-500 mr-3 mt-1 text-sm"></i>
                                    <span><strong>Verification:</strong> We may request additional documentation to verify the authenticity and accuracy of your listing information.</span>
                                </p>
                                <p class="flex items-start">
                                    <i class="fas fa-rocket text-blue-500 mr-3 mt-1 text-sm"></i>
                                    <span><strong>Go Live:</strong> Once approved, your listing will be published on our marketplace and made available to potential buyers.</span>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>


                <!-- Action Buttons -->
                <div class="flex flex-col sm:flex-row gap-4">
                    <a href="<?= url('public/index.php?p=dashboard&page=userDashboard') ?>"
                        class="flex-1 inline-flex items-center justify-center px-6 py-4 bg-gradient-to-r from-blue-600 to-blue-700 text-white font-semibold rounded-xl hover:from-blue-700 hover:to-blue-800 transition-all duration-300 shadow-lg hover:shadow-xl transform hover:-translate-y-1">
                        <i class="fas fa-tachometer-alt mr-3"></i>
                        View Dashboard
                    </a>
                    <a href="<?= url('public/index.php') ?>"
                        class="flex-1 inline-flex items-center justify-center px-6 py-4 bg-gradient-to-r from-gray-600 to-gray-700 text-white font-semibold rounded-xl hover:from-gray-700 hover:to-gray-800 transition-all duration-300 shadow-lg hover:shadow-xl transform hover:-translate-y-1">
                        <i class="fas fa-home mr-3"></i>
                        Back to Home
                    </a>
                </div>


            </div>
        </div>
    </div>
</div>