<?php
/**
 * PayPal Integration Test Page
 * Test and demonstrate the PayPal integration
 */

require_once __DIR__ . '/scripts/tienda/paypal_tienda.php';

// Initialize PayPal integration
$paypal = new PayPalTienda();

// Test server accessibility
$accessibility_test = $paypal->verifyServerAccess();

// Get configuration summary
$config = PayPalConfig::getConfigSummary();
$validation = PayPalConfig::validateConfig();

// Generate test payment links
$test_links = [
    ['amount' => 5.00, 'description' => '50 Credits Package'],
    ['amount' => 10.00, 'description' => '100 Credits Package'],
    ['amount' => 20.00, 'description' => '200 Credits Package']
];

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PayPal Integration Test</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 20px auto;
            padding: 20px;
            background-color: #f8f9fa;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .status {
            padding: 10px;
            border-radius: 5px;
            margin: 10px 0;
        }
        .status.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .status.warning { background: #fff3cd; color: #856404; border: 1px solid #ffeaa7; }
        .status.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .test-links {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        .payment-option {
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 20px;
            text-align: center;
            background: #f8f9fa;
        }
        .paypal-button {
            background: #0070ba;
            color: white;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 5px;
            display: inline-block;
            margin-top: 10px;
        }
        .config-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        .config-table th, .config-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        .config-table th {
            background: #f8f9fa;
        }
        pre {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            overflow-x: auto;
        }
        .logs {
            max-height: 300px;
            overflow-y: auto;
            background: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            font-family: monospace;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>PayPal Integration Test</h1>
        
        <h2>System Status</h2>
        
        <div class="status <?php echo $accessibility_test ? 'success' : 'error'; ?>">
            <strong>Server Accessibility:</strong> 
            <?php echo $accessibility_test ? 'PASSED - PayPal can access return URLs' : 'FAILED - PayPal cannot access return URLs'; ?>
        </div>
        
        <div class="status <?php echo $validation === true ? 'success' : 'warning'; ?>">
            <strong>Configuration:</strong> 
            <?php 
            if ($validation === true) {
                echo 'All settings configured correctly';
            } else {
                echo 'Warnings: ' . implode(', ', $validation);
            }
            ?>
        </div>
        
        <h2>Configuration Summary</h2>
        <table class="config-table">
            <tr><th>Setting</th><th>Value</th></tr>
            <tr><td>Base URL</td><td><?php echo htmlspecialchars($config['base_url']); ?></td></tr>
            <tr><td>PayPal Email</td><td><?php echo htmlspecialchars($config['paypal_email']); ?></td></tr>
            <tr><td>Environment</td><td><?php echo $config['use_sandbox'] ? 'Sandbox (Testing)' : 'Live (Production)'; ?></td></tr>
            <tr><td>Currency</td><td><?php echo htmlspecialchars($config['currency']); ?></td></tr>
            <tr><td>Credits per Dollar</td><td><?php echo $config['credits_per_dollar']; ?></td></tr>
        </table>
        
        <h2>Test Payment Links</h2>
        <p><strong>Note:</strong> These are test links using PayPal sandbox. No real money will be charged.</p>
        
        <div class="test-links">
            <?php foreach ($test_links as $test): ?>
                <div class="payment-option">
                    <h3><?php echo htmlspecialchars($test['description']); ?></h3>
                    <p><strong>$<?php echo number_format($test['amount'], 2); ?></strong></p>
                    <p><?php echo ($test['amount'] * $config['credits_per_dollar']); ?> Credits</p>
                    <?php 
                    $link = $paypal->createLink($test['amount'], null, $test['description'], 'test_user_123');
                    ?>
                    <a href="<?php echo htmlspecialchars($link); ?>" class="paypal-button" target="_blank">
                        Pay with PayPal
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
        
        <h2>URLs Being Used</h2>
        <table class="config-table">
            <?php $urls = PayPalConfig::getURLs(); ?>
            <tr><th>URL Type</th><th>URL</th></tr>
            <tr><td>Success Return</td><td><a href="<?php echo $urls['success']; ?>" target="_blank"><?php echo htmlspecialchars($urls['success']); ?></a></td></tr>
            <tr><td>Cancel Return</td><td><a href="<?php echo $urls['cancel']; ?>" target="_blank"><?php echo htmlspecialchars($urls['cancel']); ?></a></td></tr>
            <tr><td>IPN Notification</td><td><?php echo htmlspecialchars($urls['ipn']); ?></td></tr>
            <tr><td>PayPal Form</td><td><?php echo htmlspecialchars($urls['paypal']); ?></td></tr>
        </table>
        
        <h2>Recent Logs</h2>
        <div class="logs">
            <?php 
            $recent_logs = $paypal->getRecentLogs(20);
            echo htmlspecialchars($recent_logs);
            ?>
        </div>
        
        <h2>Manual Testing</h2>
        <p>To test the PayPal integration:</p>
        <ol>
            <li>Click on one of the test payment links above</li>
            <li>You'll be redirected to PayPal sandbox</li>
            <li>Use test PayPal account credentials (create one at developer.paypal.com)</li>
            <li>Complete the payment process</li>
            <li>You should be redirected back to the success page</li>
            <li>Check the logs for IPN processing</li>
        </ol>
        
        <div style="margin-top: 30px; font-size: 12px; color: #6c757d;">
            <p>Last updated: <?php echo date('Y-m-d H:i:s'); ?></p>
            <p><a href="?refresh=1">Refresh Status</a> | <a href="/scripts/tienda/config.php">View Raw Config</a></p>
        </div>
    </div>
</body>
</html>