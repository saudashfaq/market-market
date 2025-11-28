<?php
/**
 * Path Helper for consistent URL construction
 * Provides server-side path detection that matches client-side PathDetector logic
 */

class PathHelper {
    
    /**
     * Detect the correct base path for API URLs based on server environment
     */
    public static function detectBasePath() {
        $hostname = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
        
        // Check if we're in a production environment (IP address)
        if (self::isProductionEnvironment($hostname)) {
            return '';
        }
        
        // Check if we're in a development environment with /marketplace/ in path
        if (strpos($requestUri, '/marketplace/') === 0) {
            return '/marketplace';
        }
        
        // Default for development environments
        if (self::isDevelopmentEnvironment($hostname)) {
            return '/marketplace';
        }
        
        // Default fallback
        return '';
    }
    
    /**
     * Build API URL with correct base path
     */
    public static function buildApiUrl($endpoint) {
        $basePath = self::detectBasePath();
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $hostname = $_SERVER['HTTP_HOST'] ?? 'localhost';
        
        // For API endpoints, we need different path handling
        $apiBasePath = $basePath;
        
        // If we're in development and basePath includes /public, remove it for API calls
        if (strpos($basePath, '/public') !== false) {
            $apiBasePath = str_replace('/public', '', $basePath);
        }
        
        // Ensure endpoint starts with /
        if (strpos($endpoint, '/') !== 0) {
            $endpoint = '/' . $endpoint;
        }
        
        return $protocol . '://' . $hostname . $apiBasePath . $endpoint;
    }

    /**
     * Build page URL with correct base path (for email links, etc.)
     */
    public static function buildPageUrl($page) {
        $basePath = self::detectBasePath();
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $hostname = $_SERVER['HTTP_HOST'] ?? 'localhost';
        
        // Ensure page starts with /
        if (strpos($page, '/') !== 0) {
            $page = '/' . $page;
        }
        
        return $protocol . '://' . $hostname . $basePath . $page;
    }
    
    /**
     * Check if we're in a production environment
     */
    private static function isProductionEnvironment($hostname) {
        // Check for IP address pattern
        if (preg_match('/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$/', $hostname)) {
            return true;
        }
        
        // Check for production domain patterns
        $productionDomains = ['marketplace.com', 'marketplace.net', 'marketplace.org'];
        foreach ($productionDomains as $domain) {
            if (strpos($hostname, $domain) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check if we're in a development environment
     */
    private static function isDevelopmentEnvironment($hostname) {
        return $hostname === 'localhost' || 
               $hostname === '127.0.0.1' || 
               strpos($hostname, 'local') !== false;
    }
    
    /**
     * Get environment information for debugging
     */
    public static function getEnvironmentInfo() {
        return [
            'hostname' => $_SERVER['HTTP_HOST'] ?? 'unknown',
            'request_uri' => $_SERVER['REQUEST_URI'] ?? '/',
            'base_path' => self::detectBasePath(),
            'is_production' => self::isProductionEnvironment($_SERVER['HTTP_HOST'] ?? 'localhost'),
            'is_development' => self::isDevelopmentEnvironment($_SERVER['HTTP_HOST'] ?? 'localhost'),
            'protocol' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http'
        ];
    }
}

/**
 * Convenience functions for easy use in templates
 */

/**
 * Get the correct base path for the current environment
 */
function getBasePath() {
    return PathHelper::detectBasePath();
}

/**
 * Build an API URL with the correct base path
 */
function buildApiUrl($endpoint) {
    return PathHelper::buildApiUrl($endpoint);
}

/**
 * Get polling integration URL
 */
function getPollingUrl() {
    return PathHelper::buildApiUrl('/api/polling_integration.php');
}

/**
 * Generate JavaScript code to set the correct base path
 * This can be used in PHP templates to ensure client-side and server-side paths match
 */
function generatePathDetectorConfig() {
    $basePath = PathHelper::detectBasePath();
    $envInfo = PathHelper::getEnvironmentInfo();
    
    return "
    <script>
    // Server-side path configuration for PathDetector
    if (window.pathDetector) {
        // Override PathDetector with server-detected values
        window.pathDetector.cachedBasePath = " . json_encode($basePath) . ";
        window.pathDetector.detectionMethod = 'server-side-override';
        window.pathDetector.isProduction = " . ($envInfo['is_production'] ? 'true' : 'false') . ";
        
        console.log('🔧 PathDetector configured from server-side:', {
            basePath: " . json_encode($basePath) . ",
            isProduction: " . ($envInfo['is_production'] ? 'true' : 'false') . ",
            hostname: " . json_encode($envInfo['hostname']) . "
        });
    }
    </script>";
}
?>