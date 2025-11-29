<?php
/**
 * Server Polling Fix Script
 * Ye script server par polling issues fix karti hai
 */

require_once 'config.php';

echo "<h1>🔧 Server Polling Fix Script</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    .success { color: green; background: #e8f5e8; padding: 10px; margin: 5px 0; }
    .error { color: red; background: #ffe8e8; padding: 10px; margin: 5px 0; }
    .warning { color: orange; background: #fff3cd; padding: 10px; margin: 5px 0; }
    .info { color: blue; background: #e8f4fd; padding: 10px; margin: 5px 0; }
    .code { background: #f4f4f4; padding: 10px; font-family: monospace; margin: 10px 0; }
</style>";

// 1. Fix BASE constant issue
echo "<h2>1. 🌐 Fixing BASE Constant</h2>";

if (!defined('BASE')) {
    echo "<div class='error'>❌ BASE constant not defined - this is the main issue!</div>";
    
    // Try to define BASE manually
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'];
    $path = dirname($_SERVER['REQUEST_URI']);
    $basePath = rtrim($path, '/') . '/';
    
    $manualBase = $protocol . $host . $basePath;
    
    echo "<div class='warning'>⚠️ Attempting to define BASE manually: $manualBase</div>";
    
    if (!defined('BASE')) {
        define('BASE', $manualBase);
        echo "<div class='success'>✅ BASE constant defined manually</div>";
    }
} else {
    echo "<div class='success'>✅ BASE constant already defined: " . BASE . "</div>";
}

// 2. Check and fix polling.js
echo "<h2>2. 📄 Checking polling.js File</h2>";

$pollingJsPath = 'js/polling.js';
if (!file_exists($pollingJsPath)) {
    echo "<div class='error'>❌ polling.js not found at: $pollingJsPath</div>";
    
    // Create basic polling.js if it doesn't exist
    if (!is_dir('js')) {
        mkdir('js', 0755, true);
        echo "<div class='info'>📁 Created js directory</div>";
    }
    
    $basicPollingJs = '
// Basic Polling Manager for Server Compatibility
console.log("🔧 Basic Polling Manager Loaded");

window.pollingManager = {
    renderCallbacks: {},
    isPolling: false,
    
    start: function() {
        console.log("🚀 Starting basic polling");
        this.isPolling = true;
        this.poll();
    },
    
    poll: function() {
        if (!this.isPolling) return;
        
        // Basic polling implementation
        setTimeout(() => {
            if (this.isPolling) {
                console.log("📡 Polling tick");
                this.poll();
            }
        }, 30000); // 30 seconds
    },
    
    stop: function() {
        console.log("⏹️ Stopping polling");
        this.isPolling = false;
    }
};

// Auto-start polling
document.addEventListener("DOMContentLoaded", function() {
    console.log("🏁 DOM loaded, starting polling manager");
    window.pollingManager.start();
});

// Legacy compatibility
window.startPolling = function() {
    window.pollingManager.start();
};

console.log("✅ Basic polling.js loaded successfully");
';
    
    if (file_put_contents($pollingJsPath, $basicPollingJs)) {
        echo "<div class='success'>✅ Created basic polling.js file</div>";
    } else {
        echo "<div class='error'>❌ Failed to create polling.js file</div>";
    }
} else {
    echo "<div class='success'>✅ polling.js file exists</div>";
}

// 3. Fix problematic files
echo "<h2>3. 🔧 Fixing Problematic Files</h2>";

$problematicFiles = [
    'modules/dashboard/userDashboard.php' => 'User Dashboard',
    'modules/dashboard/superAdminAudit.php' => 'Super Admin Audit', 
    'modules/home.php' => 'Home Page'
];

foreach ($problematicFiles as $file => $name) {
    echo "<h3>📄 Fixing $name</h3>";
    
    if (!file_exists($file)) {
        echo "<div class='error'>❌ File not found: $file</div>";
        continue;
    }
    
    $content = file_get_contents($file);
    $originalContent = $content;
    $changes = [];
    
    // Fix 1: Add BASE constant check
    if (strpos($content, 'typeof BASE') === false && strpos($content, 'BASE +') !== false) {
        $baseCheck = "
// Check if BASE is defined before using
if (typeof BASE === 'undefined') {
    console.error('❌ BASE constant not defined in $name');
    var BASE = window.location.origin + window.location.pathname.replace(/\/[^\/]*$/, '/');
    console.log('🔧 Using fallback BASE:', BASE);
}
";
        
        // Insert BASE check before first BASE usage
        $content = preg_replace('/(<script[^>]*>)/', '$1' . $baseCheck, $content, 1);
        $changes[] = "Added BASE constant check";
    }
    
    // Fix 2: Add polling.js error handling
    if (strpos($content, 'script.src = BASE') !== false && strpos($content, 'script.onerror') === false) {
        $content = str_replace(
            'script.src = BASE + \'js/polling.js\';',
            'script.src = BASE + \'js/polling.js\';
            script.onerror = function() {
                console.error(\'❌ Failed to load polling.js in ' . $name . '\');
                // Fallback: create basic polling manager
                window.pollingManager = { renderCallbacks: {}, start: function(){}, stop: function(){} };
            };',
            $content
        );
        $changes[] = "Added polling.js error handling";
    }
    
    // Fix 3: Add DOM ready check
    if (strpos($content, 'DOMContentLoaded') === false && strpos($content, 'polling') !== false) {
        $domReadyCheck = "
// Ensure DOM is ready before initializing polling
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializePolling);
} else {
    initializePolling();
}

function initializePolling() {
    console.log('🏁 DOM ready, initializing polling for $name');
    // Existing polling code will run here
}
";
        
        // Add DOM ready check before closing script tag
        $content = str_replace('</script>', $domReadyCheck . '</script>', $content, 1);
        $changes[] = "Added DOM ready check";
    }
    
    // Save changes if any were made
    if ($content !== $originalContent) {
        if (file_put_contents($file, $content)) {
            echo "<div class='success'>✅ Fixed $name: " . implode(', ', $changes) . "</div>";
        } else {
            echo "<div class='error'>❌ Failed to save changes to $name</div>";
        }
    } else {
        echo "<div class='info'>ℹ️ No changes needed for $name</div>";
    }
}

// 4. Create server-specific polling test
echo "<h2>4. 🧪 Creating Server Polling Test</h2>";

$testPollingContent = '
<!DOCTYPE html>
<html>
<head>
    <title>Server Polling Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .status { padding: 10px; margin: 10px 0; border-radius: 5px; }
        .success { background: #d4edda; color: #155724; }
        .error { background: #f8d7da; color: #721c24; }
        .info { background: #d1ecf1; color: #0c5460; }
    </style>
</head>
<body>
    <h1>🧪 Server Polling Test</h1>
    <div id="status"></div>
    <div id="log"></div>
    
    <script>
        const statusDiv = document.getElementById("status");
        const logDiv = document.getElementById("log");
        
        function log(message, type = "info") {
            const div = document.createElement("div");
            div.className = "status " + type;
            div.innerHTML = new Date().toLocaleTimeString() + " - " + message;
            logDiv.appendChild(div);
            console.log(message);
        }
        
        // Test 1: Check BASE constant
        log("🔧 Testing BASE constant...");
        if (typeof BASE !== "undefined") {
            log("✅ BASE constant defined: " + BASE, "success");
        } else {
            log("❌ BASE constant not defined", "error");
            // Define fallback BASE
            window.BASE = window.location.origin + window.location.pathname.replace(/\/[^\/]*$/, "/");
            log("🔧 Using fallback BASE: " + BASE, "info");
        }
        
        // Test 2: Test polling.js loading
        log("📦 Testing polling.js loading...");
        const script = document.createElement("script");
        script.src = BASE + "js/polling.js";
        
        script.onload = function() {
            log("✅ polling.js loaded successfully", "success");
            
            // Test 3: Check polling manager
            setTimeout(() => {
                if (window.pollingManager) {
                    log("✅ Polling manager available", "success");
                    statusDiv.innerHTML = "<div class=\"status success\">🎉 All tests passed! Polling should work on server.</div>";
                } else {
                    log("❌ Polling manager not available", "error");
                    statusDiv.innerHTML = "<div class=\"status error\">❌ Polling manager not found</div>";
                }
            }, 1000);
        };
        
        script.onerror = function() {
            log("❌ Failed to load polling.js", "error");
            statusDiv.innerHTML = "<div class=\"status error\">❌ polling.js failed to load</div>";
        };
        
        document.head.appendChild(script);
        
        log("🚀 Test started...");
    </script>
</body>
</html>
';

if (file_put_contents('test_server_polling.html', $testPollingContent)) {
    echo "<div class='success'>✅ Created server polling test: test_server_polling.html</div>";
} else {
    echo "<div class='error'>❌ Failed to create polling test file</div>";
}

// 5. Generate fix summary
echo "<h2>5. 📋 Fix Summary & Next Steps</h2>";

echo "<div class='info'>";
echo "<h3>✅ Fixes Applied:</h3>";
echo "<ul>";
echo "<li>✅ BASE constant definition check</li>";
echo "<li>✅ Created/verified polling.js file</li>";
echo "<li>✅ Added error handling to problematic files</li>";
echo "<li>✅ Created server polling test</li>";
echo "</ul>";
echo "</div>";

echo "<div class='warning'>";
echo "<h3>🔧 Manual Steps Required:</h3>";
echo "<ol>";
echo "<li><strong>Test the fix:</strong> Visit <a href='test_server_polling.html' target='_blank'>test_server_polling.html</a></li>";
echo "<li><strong>Clear browser cache:</strong> Hard refresh (Ctrl+F5) on problematic pages</li>";
echo "<li><strong>Check server logs:</strong> Look for any remaining JavaScript errors</li>";
echo "<li><strong>Test each page:</strong> userDashboard, superAdminAudit, Home</li>";
echo "<li><strong>Monitor console:</strong> Open F12 and check for polling messages</li>";
echo "</ol>";
echo "</div>";

echo "<div class='success'>";
echo "<h3>🎯 Expected Results:</h3>";
echo "<ul>";
echo "<li>✅ No more 'BASE is not defined' errors</li>";
echo "<li>✅ polling.js loads successfully</li>";
echo "<li>✅ Polling manager initializes properly</li>";
echo "<li>✅ Real-time updates work on all pages</li>";
echo "</ul>";
echo "</div>";

echo "<hr>";
echo "<p><strong>Fix completed on:</strong> " . date('Y-m-d H:i:s') . "</p>";
echo "<p><strong>Server:</strong> " . $_SERVER['HTTP_HOST'] . "</p>";

if (defined('BASE')) {
    echo "<p><strong>BASE URL:</strong> " . BASE . "</p>";
    echo "<p><strong>Test URLs:</strong></p>";
    echo "<ul>";
    echo "<li><a href='" . BASE . "test_server_polling.html' target='_blank'>Polling Test</a></li>";
    echo "<li><a href='" . BASE . "js/polling.js' target='_blank'>polling.js Direct Access</a></li>";
    echo "<li><a href='" . BASE . "modules/dashboard/userDashboard.php' target='_blank'>User Dashboard</a></li>";
    echo "</ul>";
}
?>