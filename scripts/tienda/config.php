<?php
/**
 * PayPal Configuration
 * Centralized configuration for PayPal integration
 */

class PayPalConfig {
    
    // IMPORTANT: Update these values for your production environment
    
    // Domain configuration (hardcoded to fix redirection issues)
    const BASE_URL = 'https://vidload.onrender.com'; // Replace with your actual domain
    
    // PayPal configuration
    const PAYPAL_EMAIL = 'your-paypal-email@example.com'; // Replace with your PayPal email
    const USE_SANDBOX = true; // Set to false for production
    
    // URLs (hardcoded to ensure PayPal can access them)
    const SUCCESS_URL = self::BASE_URL . '/compra-exitosa.php';
    const CANCEL_URL = self::BASE_URL . '/compra-simple.php';
    const IPN_URL = self::BASE_URL . '/scripts/tienda/paypal_ipn.php';
    
    // PayPal endpoints
    const PAYPAL_SANDBOX_URL = 'https://www.sandbox.paypal.com/cgi-bin/webscr';
    const PAYPAL_LIVE_URL = 'https://www.paypal.com/cgi-bin/webscr';
    const IPN_SANDBOX_URL = 'https://ipnpb.sandbox.paypal.com/cgi-bin/webscr';
    const IPN_LIVE_URL = 'https://ipnpb.paypal.com/cgi-bin/webscr';
    
    // Logging configuration
    const LOG_DIRECTORY = __DIR__ . '/../../logs';
    const ENABLE_DETAILED_LOGGING = true;
    
    // Currency and pricing
    const DEFAULT_CURRENCY = 'USD';
    const CREDITS_PER_DOLLAR = 10; // 1 USD = 10 credits
    
    // Security settings
    const VERIFY_SSL = true;
    const MAX_LOG_SIZE = 10485760; // 10MB
    
    /**
     * Get PayPal form URL based on environment
     */
    public static function getPayPalURL() {
        return self::USE_SANDBOX ? self::PAYPAL_SANDBOX_URL : self::PAYPAL_LIVE_URL;
    }
    
    /**
     * Get IPN verification URL based on environment
     */
    public static function getIPNURL() {
        return self::USE_SANDBOX ? self::IPN_SANDBOX_URL : self::IPN_LIVE_URL;
    }
    
    /**
     * Get all hardcoded URLs for PayPal
     */
    public static function getURLs() {
        return [
            'success' => self::SUCCESS_URL,
            'cancel' => self::CANCEL_URL,
            'ipn' => self::IPN_URL,
            'paypal' => self::getPayPalURL(),
            'ipn_verify' => self::getIPNURL()
        ];
    }
    
    /**
     * Validate configuration
     */
    public static function validateConfig() {
        $errors = [];
        
        if (self::PAYPAL_EMAIL === 'your-paypal-email@example.com') {
            $errors[] = 'PayPal email not configured';
        }
        
        if (self::BASE_URL === 'https://vidload.onrender.com' && !self::USE_SANDBOX) {
            $errors[] = 'Base URL not configured for production';
        }
        
        if (!filter_var(self::PAYPAL_EMAIL, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid PayPal email format';
        }
        
        // Test URL accessibility
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, self::SUCCESS_URL);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_NOBODY, true); // HEAD request only
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code < 200 || $http_code >= 400) {
            $errors[] = "Success URL not accessible (HTTP $http_code)";
        }
        
        return empty($errors) ? true : $errors;
    }
    
    /**
     * Create log directory if it doesn't exist
     */
    public static function ensureLogDirectory() {
        if (!is_dir(self::LOG_DIRECTORY)) {
            mkdir(self::LOG_DIRECTORY, 0755, true);
        }
    }
    
    /**
     * Get configuration summary for debugging
     */
    public static function getConfigSummary() {
        return [
            'base_url' => self::BASE_URL,
            'paypal_email' => self::PAYPAL_EMAIL,
            'use_sandbox' => self::USE_SANDBOX,
            'urls' => self::getURLs(),
            'currency' => self::DEFAULT_CURRENCY,
            'credits_per_dollar' => self::CREDITS_PER_DOLLAR,
            'logging_enabled' => self::ENABLE_DETAILED_LOGGING
        ];
    }
}

// Initialize logging directory
PayPalConfig::ensureLogDirectory();

// Export configuration as JSON for debugging (if accessed directly)
if (basename(__FILE__) == basename($_SERVER['SCRIPT_NAME'])) {
    header('Content-Type: application/json');
    
    $config = PayPalConfig::getConfigSummary();
    $validation = PayPalConfig::validateConfig();
    
    echo json_encode([
        'config' => $config,
        'validation' => $validation === true ? 'OK' : $validation,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT);
}
?>