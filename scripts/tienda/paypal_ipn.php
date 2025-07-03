<?php
/**
 * PayPal IPN (Instant Payment Notification) Handler
 * Handles the actual payment verification and processing
 */

// Enable error logging
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../logs/paypal_ipn_errors.log');

// IPN logging
$log_file = __DIR__ . '/../../logs/paypal_ipn.log';
$log_dir = dirname($log_file);
if (!is_dir($log_dir)) {
    mkdir($log_dir, 0755, true);
}

function logIPN($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $log_entry = "[$timestamp] IP: $ip | $message" . PHP_EOL;
    file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
}

logIPN("IPN RECEIVED - Starting processing");

// Read the raw POST data
$raw_post_data = file_get_contents('php://input');
$raw_post_array = explode('&', $raw_post_data);
$myPost = array();

foreach ($raw_post_array as $keyval) {
    $keyval = explode('=', $keyval);
    if (count($keyval) == 2) {
        $myPost[$keyval[0]] = urldecode($keyval[1]);
    }
}

// Read the IPN message sent from PayPal and prepend 'cmd=_notify-validate'
$req = 'cmd=_notify-validate';
if (function_exists('get_magic_quotes_gpc')) {
    $get_magic_quotes_exists = true;
}

foreach ($myPost as $key => $value) {
    if ($get_magic_quotes_exists == true && get_magic_quotes_gpc() == 1) {
        $value = urlencode(stripslashes($value));
    } else {
        $value = urlencode($value);
    }
    $req .= "&$key=$value";
}

logIPN("IPN Data received: " . json_encode($myPost));

// PayPal's URL for IPN verification (sandbox vs live)
$paypal_url = "https://ipnpb.sandbox.paypal.com/cgi-bin/webscr"; // Change to live for production
// $paypal_url = "https://ipnpb.paypal.com/cgi-bin/webscr"; // Live URL

// POST the verification back to PayPal
$ch = curl_init($paypal_url);
curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, $req);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
curl_setopt($ch, CURLOPT_FORBID_REUSE, 1);
curl_setopt($ch, CURLOPT_HTTPHEADER, array('Connection: Close'));
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$res = curl_exec($ch);
$curl_error = curl_error($ch);
curl_close($ch);

if ($curl_error) {
    logIPN("CURL ERROR: " . $curl_error);
    http_response_code(500);
    exit;
}

logIPN("PayPal verification response: " . $res);

// Process the verification result
if (strcmp($res, "VERIFIED") == 0) {
    logIPN("IPN VERIFIED - Processing payment");
    
    // Extract important variables
    $payment_status = $_POST['payment_status'] ?? '';
    $payment_amount = $_POST['mc_gross'] ?? '';
    $payment_currency = $_POST['mc_currency'] ?? '';
    $txn_id = $_POST['txn_id'] ?? '';
    $receiver_email = $_POST['receiver_email'] ?? '';
    $payer_email = $_POST['payer_email'] ?? '';
    $custom_data = $_POST['custom'] ?? '';
    
    logIPN("Payment Status: $payment_status");
    logIPN("Amount: $payment_amount $payment_currency");
    logIPN("Transaction ID: $txn_id");
    logIPN("Payer Email: $payer_email");
    logIPN("Custom Data: $custom_data");
    
    // Verify the payment details
    $expected_email = "your-paypal-email@example.com"; // Replace with your PayPal email
    
    if (strtolower($receiver_email) !== strtolower($expected_email)) {
        logIPN("ERROR: Receiver email mismatch. Expected: $expected_email, Got: $receiver_email");
        http_response_code(400);
        exit;
    }
    
    // Process completed payments
    if ($payment_status == 'Completed') {
        logIPN("PAYMENT COMPLETED - Processing order");
        
        // Here you would typically:
        // 1. Check if transaction ID already exists (prevent duplicates)
        // 2. Add credits to user account
        // 3. Send confirmation email
        // 4. Update database
        
        try {
            // Example: Add credits based on payment amount
            $credits_to_add = calculateCredits($payment_amount);
            
            // Add credits to user account (implement your logic here)
            // addCreditsToUser($payer_email, $credits_to_add, $txn_id);
            
            // Send confirmation email (implement your logic here)
            // sendConfirmationEmail($payer_email, $credits_to_add, $txn_id);
            
            logIPN("ORDER PROCESSED SUCCESSFULLY - Credits added: $credits_to_add");
            
        } catch (Exception $e) {
            logIPN("ERROR processing order: " . $e->getMessage());
        }
        
    } elseif ($payment_status == 'Pending') {
        logIPN("PAYMENT PENDING - Reason: " . ($_POST['pending_reason'] ?? 'Unknown'));
        
    } elseif ($payment_status == 'Refunded' || $payment_status == 'Reversed') {
        logIPN("PAYMENT REFUNDED/REVERSED - Processing refund");
        // Handle refund logic here
        
    } else {
        logIPN("UNHANDLED PAYMENT STATUS: $payment_status");
    }
    
    // Respond to PayPal
    http_response_code(200);
    echo "OK";
    
} elseif (strcmp($res, "INVALID") == 0) {
    logIPN("IPN INVALID - Possible fraud attempt");
    http_response_code(400);
    
} else {
    logIPN("IPN VERIFICATION FAILED - Response: $res");
    http_response_code(500);
}

/**
 * Calculate credits based on payment amount
 */
function calculateCredits($amount) {
    // Example: $1 = 10 credits
    return intval(floatval($amount) * 10);
}

/**
 * Add credits to user account (implement your database logic)
 */
function addCreditsToUser($email, $credits, $txn_id) {
    // Implement your database logic here
    // Example: INSERT INTO user_credits (email, credits, transaction_id, created_at) VALUES (?, ?, ?, NOW())
    return true;
}

/**
 * Send confirmation email (implement your email logic)
 */
function sendConfirmationEmail($email, $credits, $txn_id) {
    // Implement your email sending logic here
    // Example: mail() or PHPMailer
    return true;
}

logIPN("IPN PROCESSING COMPLETED");
?>