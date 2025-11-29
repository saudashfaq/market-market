/**
 * PathDetector - Centralized path detection utility for API calls
 * Solves the polling path configuration issue where nginx serves from /var/www/marketplace/public
 * but JavaScript code incorrectly adds /marketplace/ prefix to API URLs
 */
class PathDetector {
  constructor() {
    this.cachedBasePath = null;
    this.detectionMethod = null;
    this.isProduction = null;
    this.debugMode = false;
  }

  /**
   * Enable debug logging for troubleshooting
   */
  enableDebug() {
    this.debugMode = true;
    return this;
  }

  /**
   * Log debug messages if debug mode is enabled
   */
  log(message, data = null) {
    if (this.debugMode) {
      if (data !== null && typeof data === 'object') {
        console.log(`[PathDetector] ${message}`, JSON.stringify(data, null, 2));
      } else {
        console.log(`[PathDetector] ${message}`, data || '');
      }
    }
  }

  /**
   * Detect the correct base path for API calls
   * Returns the base path that should be used to construct API URLs
   */
  detectBasePath() {
    // Return cached result if available
    if (this.cachedBasePath !== null) {
      this.log('Using cached base path:', this.cachedBasePath);
      return this.cachedBasePath;
    }

    this.log('Starting base path detection...');
    
    const origin = window.location.origin;
    const pathname = window.location.pathname;
    const hostname = window.location.hostname;
    
    this.log('Current location:', { origin, pathname, hostname });

    // Method 1: Check if we're in a production environment with nginx root configuration
    if (this.isProductionEnvironment()) {
      this.cachedBasePath = '';
      this.detectionMethod = 'production-nginx-root';
      this.isProduction = true;
      this.log('Detected production environment - using empty base path');
      return this.cachedBasePath;
    }

    // Method 2: Extract base path from current URL structure
    let basePath = this.extractBasePathFromUrl(pathname);
    if (basePath !== null) {
      this.cachedBasePath = basePath;
      this.detectionMethod = 'url-extraction';
      this.isProduction = false;
      this.log('Extracted base path from URL:', basePath);
      return this.cachedBasePath;
    }

    // Method 3: Test common path patterns
    basePath = this.testCommonPaths();
    if (basePath !== null) {
      this.cachedBasePath = basePath;
      this.detectionMethod = 'path-testing';
      this.log('Found base path through testing:', basePath);
      return this.cachedBasePath;
    }

    // Method 4: Environment-specific defaults
    basePath = this.getEnvironmentDefault();
    this.cachedBasePath = basePath;
    this.detectionMethod = 'environment-default';
    this.log('Using environment default:', basePath);
    
    return this.cachedBasePath;
  }

  /**
   * Check if we're running in a production environment
   * Production indicators: IP address, specific hostnames, or nginx-style serving
   */
  isProductionEnvironment() {
    const hostname = window.location.hostname;
    const pathname = window.location.pathname;
    
    // Check for IP address (production server indicator)
    const ipPattern = /^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$/;
    if (ipPattern.test(hostname)) {
      this.log('Detected IP address hostname - likely production');
      return true;
    }

    // Check for production domain patterns
    const productionDomains = [
      'marketplace.com',
      'marketplace.net',
      'marketplace.org'
    ];
    
    if (productionDomains.some(domain => hostname.includes(domain))) {
      this.log('Detected production domain');
      return true;
    }

    // Check if URL structure suggests nginx root configuration
    // If we're at root level without /marketplace/ in path, likely production
    // Server path: /var/www/marketplace/public (nginx root is public folder)
    // URL will be: http://domain.com/modules/... (NOT /marketplace/modules/...)
    if (pathname === '/' || 
        (pathname.startsWith('/modules/') && !pathname.startsWith('/marketplace/')) ||
        (pathname.startsWith('/auth/') && !pathname.startsWith('/marketplace/')) ||
        (pathname.startsWith('/api/') && !pathname.startsWith('/marketplace/'))) {
      this.log('URL structure suggests nginx root configuration (public folder as root)');
      return true;
    }

    return false;
  }

  /**
   * Extract base path from current URL structure
   */
  extractBasePathFromUrl(pathname) {
    this.log('Extracting base path from pathname:', pathname);

    // Look for common patterns that indicate base path
    const patterns = [
      { pattern: '/public/', description: 'public folder pattern' },
      { pattern: '/modules/', description: 'modules folder pattern' },
      { pattern: '/index.php', description: 'index.php pattern' }
    ];

    for (const { pattern, description } of patterns) {
      if (pathname.includes(pattern)) {
        const basePath = pathname.substring(0, pathname.indexOf(pattern));
        this.log(`Found ${description}, base path:`, basePath);
        return basePath;
      }
    }

    // Special handling for /marketplace/ pattern
    if (pathname.startsWith('/marketplace/')) {
      this.log('Found marketplace at start of path');
      
      // For development environments (localhost), check if we need /public/ suffix
      if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1' || window.location.hostname.includes('local')) {
        // Check if we're accessing through /public/ folder structure
        if (pathname.includes('/marketplace/public/') || pathname.includes('/marketplace/test-') || pathname.includes('/marketplace/simple-test') || pathname.includes('/marketplace/production-test')) {
          this.log('Development environment with public folder structure detected, using /marketplace/public as base path');
          return '/marketplace/public';
        } else {
          this.log('Development environment detected, using /marketplace as base path');
          return '/marketplace';
        }
      }
      
      // For production environments, if marketplace is at the start, base path is empty
      this.log('Production environment detected, using empty base path');
      return '';
    }

    this.log('No recognizable patterns found in URL');
    return null;
  }

  /**
   * Test common path patterns to see which one works
   */
  testCommonPaths() {
    this.log('Testing common path patterns...');
    
    const commonPaths = [
      '', // Empty path (nginx root)
      '/marketplace', // Standard marketplace folder
      '/marketplace/public', // Full marketplace path
    ];

    // Note: In a real implementation, we would test these paths
    // For now, we'll use heuristics based on current environment
    const hostname = window.location.hostname;
    
    // If localhost or development environment, likely uses /marketplace
    if (hostname === 'localhost' || hostname === '127.0.0.1' || hostname.includes('local')) {
      this.log('Development environment detected, using /marketplace');
      return '/marketplace';
    }

    // Default to empty path for production-like environments
    this.log('Production-like environment, using empty path');
    return '';
  }

  /**
   * Get environment-specific default path
   */
  getEnvironmentDefault() {
    const hostname = window.location.hostname;
    const pathname = window.location.pathname;
    
    // Development environment defaults
    if (hostname === 'localhost' || hostname === '127.0.0.1' || hostname.includes('local')) {
      this.log('Development environment - defaulting to /marketplace');
      return '/marketplace';
    }

    // If pathname doesn't include /marketplace/, we're in production with nginx root
    if (!pathname.includes('/marketplace/')) {
      this.log('Production environment (nginx root at public folder) - using empty path');
      return '';
    }

    // Production environment default
    this.log('Production environment - defaulting to empty path');
    return '';
  }

  /**
   * Build complete API URL with correct path
   */
  buildApiUrl(endpoint) {
    const basePath = this.detectBasePath();
    const origin = window.location.origin;
    
    // Ensure endpoint starts with /
    if (!endpoint.startsWith('/')) {
      endpoint = '/' + endpoint;
    }

    // For API endpoints, we need different path handling
    let apiBasePath = basePath;
    
    // If we're in development and basePath includes /public, remove it for API calls
    if (basePath.includes('/public')) {
      apiBasePath = basePath.replace('/public', '');
    }

    const fullUrl = origin + apiBasePath + endpoint;
    this.log('Built API URL:', { endpoint, basePath, apiBasePath, fullUrl });
    
    return fullUrl;
  }

  /**
   * Build complete page URL with correct path (for email links, etc.)
   */
  buildPageUrl(page) {
    const basePath = this.detectBasePath();
    const origin = window.location.origin;
    
    // Ensure page starts with /
    if (!page.startsWith('/')) {
      page = '/' + page;
    }

    const fullUrl = origin + basePath + page;
    this.log('Built Page URL:', { page, basePath, fullUrl });
    
    return fullUrl;
  }

  /**
   * Test if an API path is accessible (placeholder for future implementation)
   * In a full implementation, this would make a HEAD request to test the path
   */
  async testApiPath(url) {
    this.log('Testing API path accessibility:', url);
    
    try {
      // For now, we'll just return true
      // In a real implementation, we would make a HEAD request
      // const response = await fetch(url, { method: 'HEAD' });
      // return response.ok;
      
      this.log('API path test passed (placeholder)');
      return true;
    } catch (error) {
      this.log('API path test failed:', error.message);
      return false;
    }
  }

  /**
   * Get detection result information for debugging
   */
  getDetectionInfo() {
    return {
      basePath: this.cachedBasePath,
      detectionMethod: this.detectionMethod,
      isProduction: this.isProduction,
      currentUrl: window.location.href,
      hostname: window.location.hostname,
      pathname: window.location.pathname
    };
  }

  /**
   * Reset cached detection results (useful for testing)
   */
  reset() {
    this.cachedBasePath = null;
    this.detectionMethod = null;
    this.isProduction = null;
    this.log('PathDetector reset');
  }

  /**
   * Create a configured API URL builder function
   */
  createApiUrlBuilder() {
    return (endpoint) => this.buildApiUrl(endpoint);
  }
}

// Create global instance
window.PathDetector = PathDetector;

// Create default instance for immediate use
window.pathDetector = new PathDetector();

// Export for module usage
if (typeof module !== 'undefined' && module.exports) {
  module.exports = PathDetector;
}

// Add some utility functions for backward compatibility
window.PathDetectorUtils = {
  /**
   * Quick function to get the correct API URL
   */
  getApiUrl: function(endpoint) {
    return window.pathDetector.buildApiUrl(endpoint);
  },

  /**
   * Quick function to get the correct page URL (for email links, etc.)
   */
  getPageUrl: function(page) {
    return window.pathDetector.buildPageUrl(page);
  },

  /**
   * Enable debug mode on the global instance
   */
  enableDebug: function() {
    window.pathDetector.enableDebug();
    console.log('PathDetector debug mode enabled');
  },

  /**
   * Get current path detection info
   */
  getInfo: function() {
    return window.pathDetector.getDetectionInfo();
  }
};

console.log('✅ PathDetector utility loaded');
console.log('🔧 Usage: PathDetectorUtils.getApiUrl("/api/polling_integration.php")');
console.log('🐛 Debug: PathDetectorUtils.enableDebug()');