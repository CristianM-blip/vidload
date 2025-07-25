<?php
/**
 * PayPal Store Integration
 * Fixed hardcoded URLs to resolve PayPal redirection issues
 */

require_once __DIR__ . '/config.php';

class PayPalTienda {
    
    private $config;
    private $log_file;
    
    public function __construct() {
        // Use centralized configuration
        $this->config = PayPalConfig::getConfigSummary();
        $this->log_file = PayPalConfig::LOG_DIRECTORY . '/paypal.log';
        
        // Ensure log directory exists
        PayPalConfig::ensureLogDirectory();
        
        $this->logMessage("PayPalTienda initialized with base URL: " . $this->config['base_url']);
        
        // Validate configuration
        $validation = PayPalConfig::validateConfig();
        if ($validation !== true) {
            $this->logMessage("Configuration warnings: " . implode(', ', $validation));
        }
    }
    
    /**
     * Create PayPal links with hardcoded URLs
     * This fixes the dynamic URL generation issues that prevented PayPal from accessing return pages
     */
    public function createLink($amount, $currency = null, $item_name = 'Video Download Credits', $custom_data = '') {
        $currency = $currency ?: $this->config['currency'];
        
        $this->logMessage("Creating PayPal link - Amount: $amount, Currency: $currency, Item: $item_name");
        
        // Get hardcoded URLs from configuration
        $urls = PayPalConfig::getURLs();
        
        // Log the URLs being used
        $this->logMessage("Return URL: " . $urls['success']);
        $this->logMessage("Cancel URL: " . $urls['cancel']);
        $this->logMessage("Notify URL: " . $urls['ipn']);
        
        // PayPal form parameters
        $paypal_params = array(
            'cmd' => '_xclick',
            'business' => $this->config['paypal_email'],
            'item_name' => $item_name,
            'amount' => $amount,
            'currency_code' => $currency,
            'return' => $urls['success'],
            'cancel_return' => $urls['cancel'],
            'notify_url' => $urls['ipn'],
            'custom' => $custom_data,
            'no_shipping' => '1',
            'no_note' => '1'
        );
        
        // Build query string
        $query_string = http_build_query($paypal_params);
        $full_url = $urls['paypal'] . '?' . $query_string;
        
        $this->logMessage("Generated PayPal URL: " . $full_url);
        
        return $full_url;
    }
    
    /**
     * Verify server accessibility from PayPal IPs
     */
    public function verifyServerAccess() {
        $this->logMessage("Verifying server accessibility...");
        
        // Test if our return URL is accessible
        $urls = PayPalConfig::getURLs();
        $return_url = $urls['success'];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $return_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_USERAGENT, 'PayPal IPN Verification');
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            $this->logMessage("CURL Error accessing return URL: " . $error);
            return false;
        }
        
        $this->logMessage("Return URL HTTP Response Code: " . $http_code);
        
        if ($http_code >= 200 && $http_code < 300) {
            $this->logMessage("Server accessibility verification: PASSED");
            return true;
        } else {
            $this->logMessage("Server accessibility verification: FAILED");
            return false;
        }
    }
    
    /**
     * Log messages with timestamp
     */
    private function logMessage($message) {
        $timestamp = date('Y-m-d H:i:s');
        $log_entry = "[$timestamp] $message" . PHP_EOL;
        file_put_contents($this->log_file, $log_entry, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Get recent log entries for debugging
     */
    public function getRecentLogs($lines = 50) {
        if (!file_exists($this->log_file)) {
            return "No log file found.";
        }
        
        $file = file($this->log_file);
        return implode('', array_slice($file, -$lines));
    }
}

// Example usage:
if (basename(__FILE__) == basename($_SERVER['SCRIPT_NAME'])) {
    $paypal = new PayPalTienda();
    
    // Test server accessibility
    $accessible = $paypal->verifyServerAccess();
    echo "Server accessibility test: " . ($accessible ? "PASSED" : "FAILED") . "\n";
    
    // Generate a test link
    $test_link = $paypal->createLink(10.00, 'USD', 'Test Payment');
    echo "Test PayPal link: " . $test_link . "\n";
}
?>