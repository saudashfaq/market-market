<?php
/**
 * Polling Implementation Status Checker
 * Ye file check karti hai ke polling kis kis file mein implement hai
 * aur kahan properly work kar rahi hai
 */

require_once 'config.php';

class PollingStatusChecker {
    private $results = [];
    private $baseUrl;
    
    public function __construct() {
        $this->baseUrl = 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']);
    }
    
    public function checkAllFiles() {
        echo "<h1>🔍 Polling Implementation Status Report</h1>";
        echo "<style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            .success { color: green; background: #e8f5e8; padding: 10px; margin: 5px 0; }
            .error { color: red; background: #ffe8e8; padding: 10px; margin: 5px 0; }
            .warning { color: orange; background: #fff3cd; padding: 10px; margin: 5px 0; }
            .info { color: blue; background: #e8f4fd; padding: 10px; margin: 5px 0; }
            table { border-collapse: collapse; width: 100%; margin: 20px 0; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #f2f2f2; }
        </style>";
        
        // Check API files
        $this->checkApiFiles();
        
        // Check frontend files
        $this->checkFrontendFiles();
        
        // Check database polling setup
        $this->checkDatabaseSetup();
        
        // Check cron jobs
        $this->checkCronJobs();
        
        // Generate summary report
        $this->generateSummaryReport();
    }
    
    private function checkApiFiles() {
        echo "<h2>📡 API Files Polling Status</h2>";
        
        $apiFiles = [
            'api/polling_integration.php' => 'Main polling integration',
            'api/test_polling.php' => 'Polling test endpoint',
            'api/check_new_listings.php' => 'New listings polling',
            'api/notifications_api.php' => 'Notifications polling',
            'api/messages.php' => 'Messages polling',
            'api/conversations.php' => 'Conversations polling',
            'api/enhanced_bidding_api.php' => 'Bidding polling',
            'api/escrow_api.php' => 'Escrow polling',
            'api/wallet_api.php' => 'Wallet polling'
        ];
        
        echo "<table>";
        echo "<tr><th>File</th><th>Description</th><th>Status</th><th>Polling Features</th></tr>";
        
        foreach ($apiFiles as $file => $description) {
            $this->checkSingleFile($file, $description);
        }
        
        echo "</table>";
    }
    
    private function checkSingleFile($filePath, $description) {
        $status = "❌ Not Found";
        $pollingFeatures = [];
        
        if (file_exists($filePath)) {
            $content = file_get_contents($filePath);
            
            // Check for polling-related code
            $pollingPatterns = [
                'setInterval' => 'JavaScript setInterval',
                'setTimeout' => 'JavaScript setTimeout', 
                'polling' => 'Polling keyword',
                'real-time' => 'Real-time features',
                'auto-refresh' => 'Auto refresh',
                'EventSource' => 'Server-Sent Events',
                'WebSocket' => 'WebSocket connection',
                'fetch(' => 'Fetch API calls',
                '$.ajax' => 'jQuery AJAX',
                'XMLHttpRequest' => 'XMLHttpRequest'
            ];
            
            foreach ($pollingPatterns as $pattern => $feature) {
                if (stripos($content, $pattern) !== false) {
                    $pollingFeatures[] = $feature;
                }
            }
            
            if (!empty($pollingFeatures)) {
                $status = "✅ Polling Implemented";
            } else {
                $status = "⚠️ No Polling Found";
            }
        }
        
        echo "<tr>";
        echo "<td><strong>$filePath</strong></td>";
        echo "<td>$description</td>";
        echo "<td>$status</td>";
        echo "<td>" . implode(', ', $pollingFeatures) . "</td>";
        echo "</tr>";
        
        $this->results[$filePath] = [
            'status' => $status,
            'features' => $pollingFeatures,
            'description' => $description
        ];
    } 
   
    private function checkFrontendFiles() {
        echo "<h2>🖥️ Frontend Files Polling Status</h2>";
        
        // Check public folder for JS files
        $frontendFiles = [];
        
        if (is_dir('public')) {
            $this->scanDirectoryForPolling('public', $frontendFiles);
        }
        
        if (is_dir('templates')) {
            $this->scanDirectoryForPolling('templates', $frontendFiles);
        }
        
        // Check modules
        if (is_dir('modules')) {
            $this->scanDirectoryForPolling('modules', $frontendFiles);
        }
        
        echo "<table>";
        echo "<tr><th>File</th><th>Type</th><th>Polling Status</th><th>Features Found</th></tr>";
        
        foreach ($frontendFiles as $file => $data) {
            echo "<tr>";
            echo "<td><strong>$file</strong></td>";
            echo "<td>{$data['type']}</td>";
            echo "<td>{$data['status']}</td>";
            echo "<td>" . implode(', ', $data['features']) . "</td>";
            echo "</tr>";
        }
        
        echo "</table>";
    }
    
    private function scanDirectoryForPolling($directory, &$results) {
        if (!is_dir($directory)) return;
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory)
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $extension = $file->getExtension();
                $filePath = $file->getPathname();
                
                if (in_array($extension, ['js', 'php', 'html', 'htm'])) {
                    $content = file_get_contents($filePath);
                    $features = [];
                    
                    // Check for polling patterns
                    $patterns = [
                        'setInterval' => 'Auto Refresh Timer',
                        'setTimeout' => 'Delayed Polling',
                        'fetch(' => 'Fetch API',
                        '$.ajax' => 'jQuery AJAX',
                        'XMLHttpRequest' => 'XHR Requests',
                        'EventSource' => 'Server-Sent Events',
                        'WebSocket' => 'WebSocket',
                        'polling' => 'Polling Logic',
                        'real-time' => 'Real-time Updates'
                    ];
                    
                    foreach ($patterns as $pattern => $feature) {
                        if (stripos($content, $pattern) !== false) {
                            $features[] = $feature;
                        }
                    }
                    
                    if (!empty($features)) {
                        $results[$filePath] = [
                            'type' => strtoupper($extension),
                            'status' => '✅ Polling Found',
                            'features' => $features
                        ];
                    }
                }
            }
        }
    }
    
    private function checkDatabaseSetup() {
        echo "<h2>🗄️ Database Polling Setup</h2>";
        
        try {
            // Check if polling-related tables exist
            $tables = [
                'notifications' => 'Notifications table',
                'messages' => 'Messages table', 
                'listings' => 'Listings table',
                'bids' => 'Bids table',
                'escrow_transactions' => 'Escrow table',
                'wallet_transactions' => 'Wallet table'
            ];
            
            echo "<table>";
            echo "<tr><th>Table</th><th>Description</th><th>Status</th><th>Polling Columns</th></tr>";
            
            foreach ($tables as $table => $description) {
                $this->checkTableForPolling($table, $description);
            }
            
            echo "</table>";
            
        } catch (Exception $e) {
            echo "<div class='error'>Database connection error: " . $e->getMessage() . "</div>";
        }
    }
    
    private function checkTableForPolling($tableName, $description) {
        global $pdo;
        
        try {
            // Check if table exists
            $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
            $stmt->execute([$tableName]);
            
            if ($stmt->rowCount() > 0) {
                // Check table structure for polling-friendly columns
                $stmt = $pdo->prepare("DESCRIBE $tableName");
                $stmt->execute();
                $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $pollingColumns = [];
                foreach ($columns as $column) {
                    $columnName = $column['Field'];
                    if (in_array($columnName, ['created_at', 'updated_at', 'timestamp', 'last_modified', 'status', 'is_read', 'is_new'])) {
                        $pollingColumns[] = $columnName;
                    }
                }
                
                $status = !empty($pollingColumns) ? "✅ Polling Ready" : "⚠️ Limited Polling Support";
                
                echo "<tr>";
                echo "<td><strong>$tableName</strong></td>";
                echo "<td>$description</td>";
                echo "<td>$status</td>";
                echo "<td>" . implode(', ', $pollingColumns) . "</td>";
                echo "</tr>";
                
            } else {
                echo "<tr>";
                echo "<td><strong>$tableName</strong></td>";
                echo "<td>$description</td>";
                echo "<td>❌ Table Not Found</td>";
                echo "<td>-</td>";
                echo "</tr>";
            }
            
        } catch (Exception $e) {
            echo "<tr>";
            echo "<td><strong>$tableName</strong></td>";
            echo "<td>$description</td>";
            echo "<td>❌ Error: " . $e->getMessage() . "</td>";
            echo "<td>-</td>";
            echo "</tr>";
        }
    }  
  
    private function checkCronJobs() {
        echo "<h2>⏰ Cron Jobs & Background Tasks</h2>";
        
        $cronFiles = [
            'cron/auction_expiry_cron.php' => 'Auction expiry cron'
        ];
        
        echo "<table>";
        echo "<tr><th>Cron File</th><th>Description</th><th>Status</th><th>Polling Features</th></tr>";
        
        foreach ($cronFiles as $file => $description) {
            $this->checkSingleFile($file, $description);
        }
        
        echo "</table>";
        
        // Check for any other background task files
        echo "<h3>🔍 Scanning for Additional Background Tasks</h3>";
        $this->scanForBackgroundTasks();
    }
    
    private function scanForBackgroundTasks() {
        $directories = ['api', 'modules', 'includes'];
        $backgroundFiles = [];
        
        foreach ($directories as $dir) {
            if (is_dir($dir)) {
                $files = glob($dir . '/*.php');
                foreach ($files as $file) {
                    $content = file_get_contents($file);
                    
                    // Look for background task indicators
                    $indicators = [
                        'cron' => 'Cron job',
                        'background' => 'Background task',
                        'scheduler' => 'Scheduler',
                        'queue' => 'Queue system',
                        'worker' => 'Worker process'
                    ];
                    
                    $foundIndicators = [];
                    foreach ($indicators as $pattern => $type) {
                        if (stripos($content, $pattern) !== false) {
                            $foundIndicators[] = $type;
                        }
                    }
                    
                    if (!empty($foundIndicators)) {
                        $backgroundFiles[$file] = $foundIndicators;
                    }
                }
            }
        }
        
        if (!empty($backgroundFiles)) {
            echo "<table>";
            echo "<tr><th>File</th><th>Background Task Types</th></tr>";
            
            foreach ($backgroundFiles as $file => $types) {
                echo "<tr>";
                echo "<td><strong>$file</strong></td>";
                echo "<td>" . implode(', ', $types) . "</td>";
                echo "</tr>";
            }
            
            echo "</table>";
        } else {
            echo "<div class='info'>No additional background task files found.</div>";
        }
    }
    
    private function generateSummaryReport() {
        echo "<h2>📊 Summary Report</h2>";
        
        $totalFiles = count($this->results);
        $implementedFiles = 0;
        $partialFiles = 0;
        $notImplementedFiles = 0;
        
        foreach ($this->results as $file => $data) {
            if (strpos($data['status'], '✅') !== false) {
                $implementedFiles++;
            } elseif (strpos($data['status'], '⚠️') !== false) {
                $partialFiles++;
            } else {
                $notImplementedFiles++;
            }
        }
        
        echo "<div class='info'>";
        echo "<h3>📈 Polling Implementation Statistics</h3>";
        echo "<ul>";
        echo "<li><strong>Total Files Checked:</strong> $totalFiles</li>";
        echo "<li><strong>✅ Fully Implemented:</strong> $implementedFiles</li>";
        echo "<li><strong>⚠️ Partially Implemented:</strong> $partialFiles</li>";
        echo "<li><strong>❌ Not Implemented:</strong> $notImplementedFiles</li>";
        echo "</ul>";
        echo "</div>";
        
        // Recommendations
        echo "<h3>💡 Recommendations</h3>";
        echo "<div class='warning'>";
        echo "<ul>";
        
        if ($notImplementedFiles > 0) {
            echo "<li>Consider implementing polling in files that don't have it yet</li>";
        }
        
        if ($partialFiles > 0) {
            echo "<li>Enhance polling features in partially implemented files</li>";
        }
        
        echo "<li>Test all polling endpoints to ensure they work correctly</li>";
        echo "<li>Consider implementing WebSocket for real-time features</li>";
        echo "<li>Add proper error handling for polling failures</li>";
        echo "<li>Implement rate limiting to prevent server overload</li>";
        echo "</ul>";
        echo "</div>";
        
        // Test URLs
        echo "<h3>🧪 Test URLs</h3>";
        echo "<div class='info'>";
        echo "<p>Use these URLs to test your polling endpoints:</p>";
        echo "<ul>";
        echo "<li><a href='api/test_polling.php' target='_blank'>Test Polling API</a></li>";
        echo "<li><a href='api/polling_integration.php' target='_blank'>Polling Integration</a></li>";
        echo "<li><a href='api/check_new_listings.php' target='_blank'>Check New Listings</a></li>";
        echo "<li><a href='api/notifications_api.php' target='_blank'>Notifications API</a></li>";
        echo "</ul>";
        echo "</div>";
    }
    
    public function testPollingEndpoints() {
        echo "<h2>🧪 Live Polling Endpoint Tests</h2>";
        
        $endpoints = [
            'api/test_polling.php',
            'api/polling_integration.php', 
            'api/check_new_listings.php',
            'api/notifications_api.php'
        ];
        
        echo "<table>";
        echo "<tr><th>Endpoint</th><th>Response Status</th><th>Response Time</th><th>Content Type</th></tr>";
        
        foreach ($endpoints as $endpoint) {
            $this->testSingleEndpoint($endpoint);
        }
        
        echo "</table>";
    }
    
    private function testSingleEndpoint($endpoint) {
        $url = $this->baseUrl . '/' . $endpoint;
        $startTime = microtime(true);
        
        $context = stream_context_create([
            'http' => [
                'timeout' => 5,
                'method' => 'GET'
            ]
        ]);
        
        $response = @file_get_contents($url, false, $context);
        $endTime = microtime(true);
        $responseTime = round(($endTime - $startTime) * 1000, 2);
        
        $status = $response !== false ? "✅ Success" : "❌ Failed";
        $contentType = $response !== false ? "JSON/HTML" : "Error";
        
        echo "<tr>";
        echo "<td><strong>$endpoint</strong></td>";
        echo "<td>$status</td>";
        echo "<td>{$responseTime}ms</td>";
        echo "<td>$contentType</td>";
        echo "</tr>";
    }
}

// Run the checker
$checker = new PollingStatusChecker();

// Check if we want to run endpoint tests
if (isset($_GET['test_endpoints'])) {
    $checker->testPollingEndpoints();
} else {
    $checker->checkAllFiles();
    
    echo "<br><div class='info'>";
    echo "<a href='?test_endpoints=1' style='background: #007cba; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>🧪 Test Live Endpoints</a>";
    echo "</div>";
}

echo "<br><div class='info'>";
echo "<p><strong>Generated on:</strong> " . date('Y-m-d H:i:s') . "</p>";
echo "<p><strong>Server:</strong> " . $_SERVER['HTTP_HOST'] . "</p>";
echo "</div>";
?>