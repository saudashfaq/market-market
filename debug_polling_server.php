<?php
/**
 * Server Polling Debug Tool
 * Ye file check karti hai ke server par polling kyun nahi chal rahi
 */

require_once 'config.php';

echo "<h1>🔍 Server Polling Debug Report</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    .success { color: green; background: #e8f5e8; padding: 10px; margin: 5px 0; }
    .error { color: red; background: #ffe8e8; padding: 10px; margin: 5px 0; }
    .warning { color: orange; background: #fff3cd; padding: 10px; margin: 5px 0; }
    .info { color: blue; background: #e8f4fd; padding: 10px; margin: 5px 0; }
    .code { background: #f4f4f4; padding: 10px; font-family: monospace; margin: 10px 0; }
</style>";

echo "<h2>🌐 Server Environment Check</h2>";

// 1. Check BASE constant
echo "<h3>1. BASE URL Configuration</h3>";
if (defined('BASE')) {
    echo "<div class='success'>✅ BASE constant defined: " . BASE . "</div>";
} else {
    echo "<div class='error'>❌ BASE constant not defined!</div>";
}

// 2. Check if polling.js exists
echo "<h3>2. Polling.js File Check</h3>";
$pollingJsPath = 'js/polling.js';
if (file_exists($pollingJsPath)) {
    echo "<div class='success'>✅ polling.js file exists at: $pollingJsPath</div>";
    
    // Check file size
    $fileSize = filesize($pollingJsPath);
    echo "<div class='info'>📁 File size: " . number_format($fileSize) . " bytes</div>";
    
    // Check if file is readable
    if (is_readable($pollingJsPath)) {
        echo "<div class='success'>✅ polling.js is readable</div>";
    } else {
        echo "<div class='error'>❌ polling.js is not readable - check permissions</div>";
    }
} else {
    echo "<div class='error'>❌ polling.js file not found at: $pollingJsPath</div>";
    
    // Check alternative locations
    $altPaths = ['public/js/polling.js', 'assets/js/polling.js', 'js/polling.min.js'];
    foreach ($altPaths as $altPath) {
        if (file_exists($altPath)) {
            echo "<div class='warning'>⚠️ Found polling.js at alternative location: $altPath</div>";
        }
    }
}

// 3. Check server configuration
echo "<h3>3. Server Configuration</h3>";
echo "<div class='info'>🖥️ Server Software: " . $_SERVER['SERVER_SOFTWARE'] . "</div>";
echo "<div class='info'>🐘 PHP Version: " . phpversion() . "</div>";
echo "<div class='info'>🌍 Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "</div>";
echo "<div class='info'>📂 Script Path: " . __DIR__ . "</div>";

// 4. Check JavaScript console errors
echo "<h3>4. JavaScript Console Test</h3>";
echo "<div class='info'>Open browser console (F12) and check for these errors:</div>";
echo "<div class='code'>
❌ Failed to load resource: js/polling.js<br>
❌ Uncaught ReferenceError: BASE is not defined<br>
❌ Uncaught TypeError: Cannot read property 'pollingManager'<br>
❌ 404 Not Found: polling.js
</div>";

// 5. Generate test URLs
echo "<h3>5. Test URLs</h3>";
$currentUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']);

echo "<div class='info'>🔗 Current URL: $currentUrl</div>";
if (defined('BASE')) {
    echo "<div class='info'>🔗 BASE URL: " . BASE . "</div>";
    echo "<div class='info'>🔗 Polling.js URL: " . BASE . "js/polling.js</div>";
    
    echo "<p><strong>Test these URLs in browser:</strong></p>";
    echo "<ul>";
    echo "<li><a href='" . BASE . "js/polling.js' target='_blank'>Test polling.js direct access</a></li>";
    echo "<li><a href='" . BASE . "api/test_polling.php' target='_blank'>Test polling API</a></li>";
    echo "<li><a href='" . BASE . "modules/dashboard/userDashboard.php' target='_blank'>Test userDashboard</a></li>";
    echo "</ul>";
}

// 6. Check specific problematic files
echo "<h3>6. Problematic Files Analysis</h3>";
$problematicFiles = [
    'modules/dashboard/userDashboard.php' => 'User Dashboard',
    'modules/dashboard/superAdminAudit.php' => 'Super Admin Audit',
    'modules/home.php' => 'Home Page'
];

foreach ($problematicFiles as $file => $name) {
    echo "<h4>📄 $name ($file)</h4>";
    
    if (file_exists($file)) {
        echo "<div class='success'>✅ File exists</div>";
        
        $content = file_get_contents($file);
        
        // Check for polling.js inclusion
        if (strpos($content, 'polling.js') !== false) {
            echo "<div class='success'>✅ polling.js referenced in file</div>";
        } else {
            echo "<div class='error'>❌ polling.js NOT referenced in file</div>";
        }
        
        // Check for BASE usage
        if (strpos($content, 'BASE') !== false) {
            echo "<div class='success'>✅ BASE constant used in file</div>";
        } else {
            echo "<div class='warning'>⚠️ BASE constant not used in file</div>";
        }
        
        // Check for JavaScript errors
        $jsErrors = [];
        if (strpos($content, 'script.src = BASE') !== false && !defined('BASE')) {
            $jsErrors[] = "BASE constant undefined when setting script.src";
        }
        
        if (!empty($jsErrors)) {
            echo "<div class='error'>❌ Potential JS errors: " . implode(', ', $jsErrors) . "</div>";
        }
        
    } else {
        echo "<div class='error'>❌ File not found</div>";
    }
}

// 7. Generate fix recommendations
echo "<h2>🔧 Fix Recommendations</h2>";

echo "<h3>Quick Fixes:</h3>";
echo "<div class='warning'>";
echo "<ol>";
echo "<li><strong>Check BASE constant:</strong> Make sure config.php is properly loaded</li>";
echo "<li><strong>Verify polling.js path:</strong> Ensure js/polling.js exists and is accessible</li>";
echo "<li><strong>Check file permissions:</strong> Ensure web server can read JS files</li>";
echo "<li><strong>Clear browser cache:</strong> Hard refresh (Ctrl+F5) on problematic pages</li>";
echo "<li><strong>Check server logs:</strong> Look for 404 errors for polling.js</li>";
echo "</ol>";
echo "</div>";

// 8. Generate JavaScript debug code
echo "<h3>JavaScript Debug Code:</h3>";
echo "<div class='code'>";
echo htmlspecialchars("
// Add this to problematic pages for debugging:
console.log('🔧 Debug Info:');
console.log('BASE defined:', typeof BASE !== 'undefined' ? BASE : 'UNDEFINED');
console.log('Current URL:', window.location.href);
console.log('Polling.js URL:', (typeof BASE !== 'undefined' ? BASE : '') + 'js/polling.js');

// Test polling.js loading
fetch((typeof BASE !== 'undefined' ? BASE : '') + 'js/polling.js')
  .then(response => {
    console.log('✅ polling.js accessible:', response.ok);
    return response.text();
  })
  .then(content => {
    console.log('📄 polling.js content length:', content.length);
  })
  .catch(error => {
    console.error('❌ polling.js error:', error);
  });
");
echo "</div>";

echo "<hr>";
echo "<p><strong>Generated on:</strong> " . date('Y-m-d H:i:s') . "</p>";
echo "<p><strong>Server:</strong> " . $_SERVER['HTTP_HOST'] . "</p>";
?>

<script>
// Real-time JavaScript debugging
console.log('🔧 Server Polling Debug - JavaScript Check');
console.log('BASE defined:', typeof BASE !== 'undefined' ? BASE : 'UNDEFINED');
console.log('Document location:', document.location.href);

// Test if polling.js can be loaded
if (typeof BASE !== 'undefined') {
    console.log('Testing polling.js at:', BASE + 'js/polling.js');
    
    fetch(BASE + 'js/polling.js')
        .then(response => {
            console.log('✅ polling.js response:', response.status, response.statusText);
            if (!response.ok) {
                console.error('❌ polling.js HTTP error:', response.status);
            }
        })
        .catch(error => {
            console.error('❌ polling.js fetch error:', error);
        });
} else {
    console.error('❌ BASE constant is undefined - this is the main issue!');
}
</script>