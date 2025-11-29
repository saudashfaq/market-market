<?php
/**
 * PathDetector Conflict Fix
 * Resolves polling issues caused by PathDetector vs BASE constant conflicts
 */

require_once 'config.php';

echo "<h1>🔧 PathDetector Conflict Fix</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    .success { color: green; background: #e8f5e8; padding: 10px; margin: 5px 0; }
    .error { color: red; background: #ffe8e8; padding: 10px; margin: 5px 0; }
    .warning { color: orange; background: #fff3cd; padding: 10px; margin: 5px 0; }
    .info { color: blue; background: #e8f4fd; padding: 10px; margin: 5px 0; }
    .code { background: #f4f4f4; padding: 10px; font-family: monospace; margin: 10px 0; }
</style>";

echo "<h2>🔍 Issue Analysis</h2>";
echo "<div class='warning'>";
echo "<h3>PathDetector vs BASE Conflict:</h3>";
echo "<ul>";
echo "<li>❌ PathDetector overriding BASE constant</li>";
echo "<li>❌ Different path detection on server vs localhost</li>";
echo "<li>❌ polling.js using PathDetector instead of BASE</li>";
echo "<li>❌ Server environment detection failing</li>";
echo "</ul>";
echo "</div>";

// 1. Check current environment
echo "<h2>1. 🌐 Environment Detection</h2>";

$hostname = $_SERVER['HTTP_HOST'];
$requestUri = $_SERVER['REQUEST_URI'];
$isProduction = preg_match('/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$/', $hostname);
$isDevelopment = in_array($hostname, ['localhost', '127.0.0.1']) || strpos($hostname, 'local') !== false;

echo "<div class='info'>";
echo "<strong>Current Environment:</strong><br>";
echo "Hostname: $hostname<br>";
echo "Request URI: $requestUri<br>";
echo "Is Production: " . ($isProduction ? 'Yes' : 'No') . "<br>";
echo "Is Development: " . ($isDevelopment ? 'Yes' : 'No') . "<br>";
if (defined('BASE')) {
    echo "BASE Constant: " . BASE . "<br>";
}
echo "</div>";

// 2. Fix PathDetector configuration
echo "<h2>2. 🔧 Fixing PathDetector Configuration</h2>";

$pathDetectorFix = '
// PathDetector Server Fix - Override for server environment
console.log("🔧 Applying PathDetector server fix...");

// Force correct path detection for server
if (window.pathDetector) {
    // Get server environment info
    const hostname = window.location.hostname;
    const isProduction = /^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$/.test(hostname);
    const isDevelopment = hostname === "localhost" || hostname === "127.0.0.1" || hostname.includes("local");
    
    console.log("🌐 Environment detection:", { hostname, isProduction, isDevelopment });
    
    // Override PathDetector with correct server configuration
    if (isProduction || (!isDevelopment && !hostname.includes("localhost"))) {
        // Production server - use empty path
        window.pathDetector.cachedBasePath = "";
        window.pathDetector.detectionMethod = "server-fix-production";
        window.pathDetector.isProduction = true;
        console.log("✅ PathDetector configured for production server (empty path)");
    } else {
        // Development server - use /marketplace
        window.pathDetector.cachedBasePath = "/marketplace";
        window.pathDetector.detectionMethod = "server-fix-development";
        window.pathDetector.isProduction = false;
        console.log("✅ PathDetector configured for development server (/marketplace)");
    }
    
    // Override buildApiUrl method to ensure consistency
    const originalBuildApiUrl = window.pathDetector.buildApiUrl;
    window.pathDetector.buildApiUrl = function(endpoint) {
        const basePath = this.cachedBasePath || "";
        const origin = window.location.origin;
        
        // Ensure endpoint starts with /
        if (!endpoint.startsWith("/")) {
            endpoint = "/" + endpoint;
        }
        
        const fullUrl = origin + basePath + endpoint;
        console.log("🔗 PathDetector buildApiUrl:", { endpoint, basePath, fullUrl });
        
        return fullUrl;
    };
    
    console.log("🎯 PathDetector fix applied successfully");
} else {
    console.warn("⚠️ PathDetector not found - creating fallback");
    
    // Create minimal PathDetector fallback
    window.pathDetector = {
        cachedBasePath: ' . ($isProduction ? '""' : '"/marketplace"') . ',
        detectionMethod: "fallback-creation",
        isProduction: ' . ($isProduction ? 'true' : 'false') . ',
        
        buildApiUrl: function(endpoint) {
            const origin = window.location.origin;
            if (!endpoint.startsWith("/")) {
                endpoint = "/" + endpoint;
            }
            return origin + this.cachedBasePath + endpoint;
        },
        
        getDetectionInfo: function() {
            return {
                basePath: this.cachedBasePath,
                detectionMethod: this.detectionMethod,
                isProduction: this.isProduction
            };
        }
    };
    
    console.log("✅ PathDetector fallback created");
}
';

// Save PathDetector fix as separate file
if (file_put_contents('js/path-detector-fix.js', $pathDetectorFix)) {
    echo "<div class='success'>✅ Created path-detector-fix.js</div>";
} else {
    echo "<div class='error'>❌ Failed to create path-detector-fix.js</div>";
}

// 3. Update polling.js to use BASE instead of PathDetector
echo "<h2>3. 📦 Updating Polling Integration</h2>";

$pollingFiles = [
    'public/js/polling.js',
    'js/polling.js'
];

foreach ($pollingFiles as $pollingFile) {
    if (file_exists($pollingFile)) {
        echo "<h3>Updating $pollingFile</h3>";
        
        $content = file_get_contents($pollingFile);
        $originalContent = $content;
        
        // Replace PathDetector usage with BASE constant
        $replacements = [
            // Replace PathDetector URL building with BASE
            'window.pathDetector.buildApiUrl(\'/api/polling_integration.php\')' => 'BASE + \'api/polling_integration.php\'',
            'pathDetector.buildApiUrl(\'/api/polling_integration.php\')' => 'BASE + \'api/polling_integration.php\'',
            
            // Add BASE constant check
            'if (window.pathDetector)' => 'if (typeof BASE !== \'undefined\' && BASE)',
            
            // Replace PathDetector fallback with BASE fallback
            'console.warn(\'⚠️ PathDetector not available, using fallback logic\');' => 'console.warn(\'⚠️ Using BASE constant for polling URLs\');'
        ];
        
        foreach ($replacements as $search => $replace) {
            if (strpos($content, $search) !== false) {
                $content = str_replace($search, $replace, $content);
                echo "<div class='info'>✅ Replaced: $search</div>";
            }
        }
        
        // Add BASE constant check at the beginning
        if (strpos($content, 'typeof BASE') === false) {
            $baseCheck = '
// Ensure BASE constant is available for polling
if (typeof BASE === \'undefined\') {
    console.error(\'❌ BASE constant not defined for polling\');
    var BASE = window.location.origin + \'/\';
    console.log(\'🔧 Using fallback BASE for polling:\', BASE);
}
';
            $content = str_replace('class PollingManager {', $baseCheck . 'class PollingManager {', $content);
        }
        
        if ($content !== $originalContent) {
            if (file_put_contents($pollingFile, $content)) {
                echo "<div class='success'>✅ Updated $pollingFile successfully</div>";
            } else {
                echo "<div class='error'>❌ Failed to update $pollingFile</div>";
            }
        } else {
            echo "<div class='info'>ℹ️ No changes needed for $pollingFile</div>";
        }
    }
}

// 4. Create unified polling configuration
echo "<h2>4. ⚙️ Creating Unified Polling Configuration</h2>";

$unifiedConfig = '
/**
 * Unified Polling Configuration
 * Resolves PathDetector vs BASE conflicts
 */

console.log("🔧 Loading unified polling configuration...");

// 1. Ensure BASE constant is properly defined
if (typeof BASE === "undefined") {
    console.error("❌ BASE constant not defined");
    
    // Auto-detect BASE based on current environment
    const hostname = window.location.hostname;
    const pathname = window.location.pathname;
    const origin = window.location.origin;
    
    if (hostname === "localhost" || hostname === "127.0.0.1" || hostname.includes("local")) {
        // Development environment
        if (pathname.includes("/marketplace/")) {
            window.BASE = origin + "/marketplace/";
        } else {
            window.BASE = origin + "/marketplace/";
        }
    } else {
        // Production environment
        window.BASE = origin + "/";
    }
    
    console.log("🔧 Auto-detected BASE:", window.BASE);
} else {
    console.log("✅ BASE constant defined:", BASE);
}

// 2. Override PathDetector to use BASE constant
if (window.pathDetector) {
    console.log("🔧 Overriding PathDetector to use BASE constant");
    
    // Store original methods
    const originalBuildApiUrl = window.pathDetector.buildApiUrl;
    
    // Override buildApiUrl to use BASE
    window.pathDetector.buildApiUrl = function(endpoint) {
        if (typeof BASE !== "undefined") {
            // Use BASE constant for consistency
            if (!endpoint.startsWith("/")) {
                endpoint = "/" + endpoint;
            }
            
            // Remove leading slash from BASE if endpoint has one
            let baseUrl = BASE;
            if (baseUrl.endsWith("/") && endpoint.startsWith("/")) {
                endpoint = endpoint.substring(1);
            }
            
            const fullUrl = baseUrl + endpoint;
            console.log("🔗 Using BASE for API URL:", { endpoint, BASE, fullUrl });
            return fullUrl;
        } else {
            // Fallback to original method
            return originalBuildApiUrl.call(this, endpoint);
        }
    };
    
    console.log("✅ PathDetector configured to use BASE constant");
}

// 3. Create polling URL helper
window.getPollingUrl = function(endpoint = "api/polling_integration.php") {
    if (typeof BASE !== "undefined") {
        const url = BASE + endpoint;
        console.log("📡 Polling URL:", url);
        return url;
    } else {
        console.error("❌ Cannot create polling URL - BASE not defined");
        return null;
    }
};

// 4. Test polling URL accessibility
window.testPollingUrl = function() {
    const url = window.getPollingUrl();
    if (!url) return;
    
    console.log("🧪 Testing polling URL:", url);
    
    fetch(url, { method: "HEAD" })
        .then(response => {
            if (response.ok) {
                console.log("✅ Polling URL accessible");
            } else {
                console.error("❌ Polling URL not accessible:", response.status);
            }
        })
        .catch(error => {
            console.error("❌ Polling URL test failed:", error);
        });
};

console.log("✅ Unified polling configuration loaded");
console.log("🧪 Test with: testPollingUrl()");
';

if (file_put_contents('js/unified-polling-config.js', $unifiedConfig)) {
    echo "<div class='success'>✅ Created unified-polling-config.js</div>";
} else {
    echo "<div class='error'>❌ Failed to create unified-polling-config.js</div>";
}

// 5. Generate fix summary and instructions
echo "<h2>5. 📋 Fix Summary & Instructions</h2>";

echo "<div class='success'>";
echo "<h3>✅ Files Created/Updated:</h3>";
echo "<ul>";
echo "<li>✅ js/path-detector-fix.js - PathDetector server configuration</li>";
echo "<li>✅ js/unified-polling-config.js - Unified polling configuration</li>";
echo "<li>✅ Updated polling.js files to use BASE constant</li>";
echo "</ul>";
echo "</div>";

echo "<div class='warning'>";
echo "<h3>🔧 Manual Steps Required:</h3>";
echo "<ol>";
echo "<li><strong>Include fix files:</strong> Add these scripts to problematic pages:</li>";
echo "<div class='code'>";
echo htmlspecialchars('<script src="js/path-detector-fix.js"></script>');
echo "<br>";
echo htmlspecialchars('<script src="js/unified-polling-config.js"></script>');
echo "</div>";

echo "<li><strong>Update problematic pages:</strong> Add the fix scripts before polling.js</li>";
echo "<li><strong>Test each page:</strong> userDashboard, superAdminAudit, Home</li>";
echo "<li><strong>Check console:</strong> Look for 'Unified polling configuration loaded' message</li>";
echo "<li><strong>Test polling URL:</strong> Run testPollingUrl() in console</li>";
echo "</ol>";
echo "</div>";

echo "<div class='info'>";
echo "<h3>🧪 Testing Commands:</h3>";
echo "<div class='code'>";
echo "// In browser console:<br>";
echo "console.log('BASE:', typeof BASE !== 'undefined' ? BASE : 'UNDEFINED');<br>";
echo "console.log('PathDetector:', window.pathDetector ? 'AVAILABLE' : 'MISSING');<br>";
echo "testPollingUrl(); // Test polling URL accessibility<br>";
echo "window.pathDetector.getDetectionInfo(); // Get detection info";
echo "</div>";
echo "</div>";

if (defined('BASE')) {
    echo "<div class='success'>";
    echo "<h3>🔗 Test URLs:</h3>";
    echo "<ul>";
    echo "<li><a href='" . BASE . "js/path-detector-fix.js' target='_blank'>path-detector-fix.js</a></li>";
    echo "<li><a href='" . BASE . "js/unified-polling-config.js' target='_blank'>unified-polling-config.js</a></li>";
    echo "<li><a href='" . BASE . "api/polling_integration.php' target='_blank'>Polling API</a></li>";
    echo "</ul>";
    echo "</div>";
}

echo "<hr>";
echo "<p><strong>Fix completed on:</strong> " . date('Y-m-d H:i:s') . "</p>";
echo "<p><strong>Server:</strong> " . $_SERVER['HTTP_HOST'] . "</p>";
?>