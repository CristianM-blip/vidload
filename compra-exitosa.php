<?php
/**
 * PayPal Purchase Success Page
 * Enhanced with detailed logging to diagnose PayPal access issues
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start logging immediately
$log_file = __DIR__ . '/logs/paypal_access.log';
$log_dir = dirname($log_file);
if (!is_dir($log_dir)) {
    mkdir($log_dir, 0755, true);
}

function logPayPalAccess($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    $request_uri = $_SERVER['REQUEST_URI'] ?? 'unknown';
    
    $log_entry = "[$timestamp] IP: $ip | UA: $user_agent | URI: $request_uri | $message" . PHP_EOL;
    file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
}

// Log the access attempt
logPayPalAccess("SUCCESS PAGE ACCESSED");

// Log all incoming parameters
if (!empty($_GET)) {
    logPayPalAccess("GET Parameters: " . json_encode($_GET));
}

if (!empty($_POST)) {
    logPayPalAccess("POST Parameters: " . json_encode($_POST));
}

// Log HTTP headers for PayPal identification
if (function_exists('getallheaders')) {
    $headers = getallheaders();
    if ($headers) {
        logPayPalAccess("HTTP Headers: " . json_encode($headers));
    }
} else {
    // Fallback for CLI/CGI environments
    $headers = [];
    foreach ($_SERVER as $key => $value) {
        if (substr($key, 0, 5) == 'HTTP_') {
            $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($key, 5)))))] = $value;
        }
    }
    if (!empty($headers)) {
        logPayPalAccess("HTTP Headers: " . json_encode($headers));
    }
}

// Check if this is a PayPal return
$is_paypal_return = false;
$payment_status = '';
$transaction_id = '';

if (isset($_GET['st']) || isset($_GET['tx']) || isset($_POST['payment_status'])) {
    $is_paypal_return = true;
    logPayPalAccess("IDENTIFIED AS PAYPAL RETURN");
    
    // PayPal return parameters
    $payment_status = $_GET['st'] ?? $_POST['payment_status'] ?? 'unknown';
    $transaction_id = $_GET['tx'] ?? $_POST['txn_id'] ?? 'unknown';
    
    logPayPalAccess("Payment Status: $payment_status");
    logPayPalAccess("Transaction ID: $transaction_id");
}

// Verify PayPal IP ranges (basic check)
function isPayPalIP($ip) {
    // PayPal's known IP ranges (simplified - in production, use complete list)
    $paypal_ranges = [
        '173.0.80.0/20',
        '64.4.240.0/21',
        '66.211.168.0/22',
        '173.0.80.0/20'
    ];
    
    foreach ($paypal_ranges as $range) {
        if (cidr_match($ip, $range)) {
            return true;
        }
    }
    return false;
}

function cidr_match($ip, $range) {
    list($subnet, $bits) = explode('/', $range);
    $ip = ip2long($ip);
    $subnet = ip2long($subnet);
    $mask = -1 << (32 - $bits);
    $subnet &= $mask;
    return ($ip & $mask) == $subnet;
}

$client_ip = $_SERVER['REMOTE_ADDR'] ?? '';
$is_from_paypal = isPayPalIP($client_ip);
logPayPalAccess("PayPal IP check: " . ($is_from_paypal ? "YES" : "NO") . " for IP: $client_ip");

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>¡Compra Exitosa!</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 600px;
            margin: 50px auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .success-container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        .success-icon {
            color: #28a745;
            font-size: 60px;
            margin-bottom: 20px;
        }
        .success-title {
            color: #28a745;
            margin-bottom: 20px;
        }
        .details {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
            text-align: left;
        }
        .button {
            display: inline-block;
            background: #007bff;
            color: white;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 5px;
            margin: 10px;
        }
        .debug-info {
            background: #e9ecef;
            padding: 10px;
            border-radius: 5px;
            font-size: 12px;
            margin-top: 20px;
            text-align: left;
        }
    </style>
</head>
<body>
    <div class="success-container">
        <div class="success-icon">✓</div>
        <h1 class="success-title">¡Pago Completado Exitosamente!</h1>
        
        <?php if ($is_paypal_return): ?>
            <p>Tu pago ha sido procesado correctamente a través de PayPal.</p>
            
            <div class="details">
                <h3>Detalles de la transacción:</h3>
                <p><strong>Estado del pago:</strong> <?php echo htmlspecialchars($payment_status); ?></p>
                <p><strong>ID de transacción:</strong> <?php echo htmlspecialchars($transaction_id); ?></p>
                <p><strong>Fecha:</strong> <?php echo date('Y-m-d H:i:s'); ?></p>
            </div>
            
            <p>Recibirás un email de confirmación en breve. Tus créditos han sido agregados a tu cuenta.</p>
        <?php else: ?>
            <p>¡Gracias por tu compra! Tu pago ha sido procesado correctamente.</p>
            <p>Recibirás una confirmación por email en breve.</p>
        <?php endif; ?>
        
        <div style="margin-top: 30px;">
            <a href="/" class="button">Volver al Inicio</a>
            <a href="/downloads" class="button">Mis Descargas</a>
        </div>
        
        <?php if (isset($_GET['debug']) && $_GET['debug'] == '1'): ?>
            <div class="debug-info">
                <h4>Información de Debug:</h4>
                <p><strong>IP Cliente:</strong> <?php echo htmlspecialchars($client_ip); ?></p>
                <p><strong>Es IP de PayPal:</strong> <?php echo $is_from_paypal ? 'Sí' : 'No'; ?></p>
                <p><strong>User Agent:</strong> <?php echo htmlspecialchars($_SERVER['HTTP_USER_AGENT'] ?? 'N/A'); ?></p>
                <p><strong>Referer:</strong> <?php echo htmlspecialchars($_SERVER['HTTP_REFERER'] ?? 'N/A'); ?></p>
                <p><strong>Timestamp:</strong> <?php echo date('Y-m-d H:i:s'); ?></p>
                
                <?php if (!empty($_GET)): ?>
                    <p><strong>Parámetros GET:</strong></p>
                    <pre><?php print_r($_GET); ?></pre>
                <?php endif; ?>
                
                <?php if (!empty($_POST)): ?>
                    <p><strong>Parámetros POST:</strong></p>
                    <pre><?php print_r($_POST); ?></pre>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Log client-side information
        console.log('Purchase success page loaded');
        console.log('Current URL:', window.location.href);
        console.log('Referer:', document.referrer);
        console.log('User Agent:', navigator.userAgent);
        
        // Send analytics if available
        if (typeof gtag !== 'undefined') {
            gtag('event', 'purchase_success', {
                'event_category': 'ecommerce',
                'event_label': 'paypal',
                'transaction_id': '<?php echo htmlspecialchars($transaction_id); ?>'
            });
        }
    </script>
</body>
</html>

<?php
// Final log entry
logPayPalAccess("SUCCESS PAGE RENDERED SUCCESSFULLY");
?>