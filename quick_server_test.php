<?php
// Quick server compatibility test
echo "<h2>🔧 Server Compatibility Test</h2>";

// PHP Version
echo "<p><strong>PHP Version:</strong> " . phpversion() . "</p>";

// Check if config.php exists
if (file_exists('config.php')) {
    echo "<p>✅ config.php found</p>";
    try {
        require_once 'config.php';
        echo "<p>✅ config.php loaded successfully</p>";
        
        // Test database connection
        if (isset($pdo)) {
            echo "<p>✅ Database connection available</p>";
        } else {
            echo "<p>⚠️ Database connection not found in config.php</p>";
        }
    } catch (Exception $e) {
        echo "<p>❌ Config error: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p>❌ config.php not found</p>";
}

// Check directory permissions
$dirs = ['api', 'public', 'templates', 'modules'];
foreach ($dirs as $dir) {
    if (is_dir($dir)) {
        echo "<p>✅ Directory '$dir' accessible</p>";
    } else {
        echo "<p>⚠️ Directory '$dir' not found</p>";
    }
}

// Check if we can write files (optional)
if (is_writable('.')) {
    echo "<p>✅ Current directory is writable</p>";
} else {
    echo "<p>⚠️ Current directory is not writable</p>";
}

echo "<hr>";
echo "<p><strong>Result:</strong> ";
if (file_exists('config.php') && (isset($pdo) || class_exists('PDO'))) {
    echo "✅ Server is ready for polling status checker!</p>";
    echo "<p><a href='test_polling_status.php'>🚀 Run Polling Status Checker</a></p>";
} else {
    echo "⚠️ Some issues found. Check config.php and database connection.</p>";
}
?>