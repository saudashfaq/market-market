<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sell Your Digital Asset - Choose Type</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
  <style>
    body { font-family: 'Inter', sans-serif; }
  </style>
</head>
<body class="bg-gradient-to-br from-gray-50 to-blue-50 min-h-screen">

  <!-- Main Section -->
  <section class="min-h-screen flex flex-col items-center justify-center px-6 py-20 relative">
    
    <!-- Header -->
    <div class="text-center mb-16 relative z-10">
      <h1 class="text-3xl sm:text-4xl md:text-5xl font-extrabold text-gray-900 mb-4">
        What Do You Want to
        <span class="text-transparent bg-clip-text bg-gradient-to-r from-blue-600 to-purple-600">Sell?</span>
      </h1>
      <p class="text-gray-600 text-base max-w-2xl mx-auto">
        Select the type of digital asset you want to sell. We'll guide you through the valuation and listing process with our secure platform.
      </p>
      <div class="mt-6 flex justify-center">
        <div class="h-1 w-20 bg-gradient-to-r from-blue-600 to-purple-600 rounded-full"></div>
      </div>
    </div>

    <!-- Choice Cards -->
    <div class="flex flex-col lg:flex-row gap-8 justify-center items-stretch w-full max-w-5xl relative z-10">
      
      <!-- Website Option -->
      <div class="group w-full lg:w-1/2">
        <a href="<?= url('addWebList')?>" class="w-full h-full flex flex-col items-center text-left border-2 border-gray-200 rounded-2xl p-6 bg-white shadow-lg hover:shadow-2xl hover:border-indigo-500 hover:-translate-y-1 transition-all duration-500">
          <div class="flex items-center justify-center w-16 h-16 rounded-full bg-gradient-to-r from-indigo-500 to-purple-500 text-white shadow-lg mb-4 group-hover:scale-105 transition-transform duration-300">
            <i class="fa-solid fa-globe text-2xl"></i>
          </div>
          <div class="text-center">
            <h2 class="text-xl font-bold mb-2 text-gray-800">Website / SaaS</h2>
            <p class="text-gray-600 text-sm leading-relaxed mb-4">
              Sell your website, blog, or SaaS platform with verified analytics, revenue proof, and technical documentation.
            </p>
          </div>
          <div class="mt-auto w-full">
            <div class="flex items-center justify-center text-indigo-600 font-semibold group-hover:translate-x-1 transition-transform duration-300">
              <span>Start Selling Your Website</span>
              <i class="fas fa-arrow-right ml-2"></i>
            </div>
          </div>
        </a>
      </div>

      <!-- YouTube Option -->
      <div class="group w-full lg:w-1/2">
        <a href="<?= url('addYTList')?>" class="w-full h-full flex flex-col items-center text-left border-2 border-gray-200 rounded-2xl p-6 bg-white shadow-lg hover:shadow-2xl hover:border-red-500 hover:-translate-y-1 transition-all duration-500">
          <div class="flex items-center justify-center w-16 h-16 rounded-full bg-gradient-to-r from-red-500 to-orange-500 text-white shadow-lg mb-4 group-hover:scale-105 transition-transform duration-300">
            <i class="fa-brands fa-youtube text-2xl"></i>
          </div>
          <div class="text-center">
            <h2 class="text-xl font-bold mb-2 text-gray-800">YouTube Channel</h2>
            <p class="text-gray-600 text-sm leading-relaxed mb-4">
              Sell your YouTube channel with authentic subscribers, monetized content, and verified audience demographics.
            </p>
          </div>
          <div class="mt-auto w-full">
            <div class="flex items-center justify-center text-red-600 font-semibold group-hover:translate-x-1 transition-transform duration-300">
              <span>Start Selling Your Channel</span>
              <i class="fas fa-arrow-right ml-2"></i>
            </div>
          </div>
        </a>
      </div>

    </div>
  </section>

</body>
</html>
