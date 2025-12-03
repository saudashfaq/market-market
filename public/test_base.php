<?php
/**
 * Quick BASE constant test
 */
require_once __DIR__ . '/../config.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>BASE Constant Test</title>
    <style>
        body { font-family: Arial; padding: 20px; background: #f5f5f5; }
        .box { background: white; padding: 20px; border-radius: 8px; margin: 10px 0; }
        .success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; }
        code { background: #f4f4f4; padding: 2px 6px; border-radius: 3px; }
    </style>
</head>
<body>
    <h1>🔧 BASE Constant Test</h1>
    
    <?php if (defined('BASE')): ?>
        <div class="box success">
            <h2>✅ BASE Constant Defined</h2>
            <p><strong>Value:</strong> <code><?= BASE ?></code></p>
        </div>
        
        <div class="box">
            <h3>Test JavaScript File Paths:</h3>
            <ul>
                <li>popup.js: <code><?= BASE ?>public/js/popup.js</code></li>
                <li>path-utils.js: <code><?= BASE ?>public/js/path-utils.js</code></li>
                <li>notifications.js: <code><?= BASE ?>public/js/notifications.js</code></li>
            </ul>
        </div>
        
        <div class="box">
            <h3>Test Links:</h3>
            <p><a href="<?= BASE ?>public/js/popup.js" target="_blank">Open popup.js</a></p>
            <p><a href="<?= BASE ?>public/js/path-utils.js" target="_blank">Open path-utils.js</a></p>
        </div>
        
    <?php else: ?>
        <div class="box error">
            <h2>❌ BASE Constant NOT Defined</h2>
            <p>This is the problem! BASE constant is not being set in config.php</p>
        </div>
    <?php endif; ?>
    
    <div class="box">
        <h3>Server Info:</h3>
        <ul>
            <li><strong>SCRIPT_NAME:</strong> <?= $_SERVER['SCRIPT_NAME'] ?? 'not set' ?></li>
            <li><strong>DOCUMENT_ROOT:</strong> <?= $_SERVER['DOCUMENT_ROOT'] ?? 'not set' ?></li>
            <li><strong>HTTP_HOST:</strong> <?= $_SERVER['HTTP_HOST'] ?? 'not set' ?></li>
            <li><strong>REQUEST_URI:</strong> <?= $_SERVER['REQUEST_URI'] ?? 'not set' ?></li>
        </ul>
    </div>
    
    <div class="box">
        <h3>PHP Error Check:</h3>
        <?php
        error_reporting(E_ALL);
        ini_set('display_errors', 1);
        
        echo "<p>Error reporting enabled. If there are any PHP errors, they will show here.</p>";
        
        // Try to load a JS file path
        $jsPath = BASE . 'public/js/popup.js';
        echo "<p>Constructed JS path: <code>$jsPath</code></p>";
        
        // Check if file exists
        $filePath = __DIR__ . '/js/popup.js';
        if (file_exists($filePath)) {
            echo "<p style='color: green;'>✅ File exists at: <code>$filePath</code></p>";
        } else {
            echo "<p style='color: red;'>❌ File NOT found at: <code>$filePath</code></p>";
        }
        ?>
    </div>
</body>
</html>
