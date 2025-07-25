<?php
/**
 * Simple PayPal Fallback Page
 * Minimal page that always works as backup for PayPal returns
 */

// Basic logging
$log_file = __DIR__ . '/logs/paypal_fallback.log';
$log_dir = dirname($log_file);
if (!is_dir($log_dir)) {
    mkdir($log_dir, 0755, true);
}

function logFallbackAccess($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $log_entry = "[$timestamp] IP: $ip | $message" . PHP_EOL;
    file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
}

logFallbackAccess("FALLBACK PAGE ACCESSED");

// Check if this is a cancellation or other scenario
$is_cancelled = isset($_GET['cancelled']) || strpos($_SERVER['REQUEST_URI'] ?? '', 'cancel') !== false;
$page_type = $is_cancelled ? 'cancelled' : 'fallback';

logFallbackAccess("Page type: $page_type");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $is_cancelled ? 'Pago Cancelado' : 'Procesando Pago'; ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 500px;
            margin: 100px auto;
            padding: 20px;
            text-align: center;
            background-color: #f8f9fa;
        }
        .container {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .icon {
            font-size: 50px;
            margin-bottom: 20px;
        }
        .cancelled { color: #ffc107; }
        .processing { color: #17a2b8; }
        .button {
            display: inline-block;
            background: #007bff;
            color: white;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 5px;
            margin: 20px 10px 0 10px;
        }
        .button.secondary {
            background: #6c757d;
        }
        .info-box {
            background: #e9ecef;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if ($is_cancelled): ?>
            <div class="icon cancelled">⚠️</div>
            <h1>Pago Cancelado</h1>
            <p>Has cancelado el proceso de pago. No se ha realizado ningún cargo a tu cuenta.</p>
            
            <div class="info-box">
                <p><strong>¿Qué puedes hacer ahora?</strong></p>
                <ul style="text-align: left; display: inline-block;">
                    <li>Intentar el pago nuevamente</li>
                    <li>Revisar los métodos de pago disponibles</li>
                    <li>Contactar con soporte si tienes problemas</li>
                </ul>
            </div>
            
            <a href="/" class="button">Volver al Inicio</a>
            <a href="/pricing" class="button secondary">Ver Precios</a>
            
        <?php else: ?>
            <div class="icon processing">⏳</div>
            <h1>Procesando Pago</h1>
            <p>Tu pago está siendo procesado. Por favor, espera un momento...</p>
            
            <div class="info-box">
                <p><strong>Información importante:</strong></p>
                <ul style="text-align: left; display: inline-block;">
                    <li>No cierres esta ventana</li>
                    <li>El proceso puede tardar unos minutos</li>
                    <li>Recibirás una confirmación por email</li>
                </ul>
            </div>
            
            <p><small>Si esta página no se actualiza automáticamente, 
            <a href="/compra-exitosa.php">haz clic aquí</a> para verificar el estado de tu pago.</small></p>
            
            <a href="/" class="button">Volver al Inicio</a>
        <?php endif; ?>
        
        <div style="margin-top: 30px; font-size: 12px; color: #6c757d;">
            <p>¿Necesitas ayuda? <a href="/contacto">Contacta con nuestro soporte</a></p>
            <p>Página cargada: <?php echo date('Y-m-d H:i:s'); ?></p>
        </div>
    </div>

    <?php if (!$is_cancelled): ?>
    <script>
        // Auto-refresh every 30 seconds for processing page
        setTimeout(function() {
            console.log('Auto-refreshing to check payment status...');
            window.location.href = '/compra-exitosa.php';
        }, 30000);
        
        // Log page load
        console.log('Simple fallback page loaded');
        console.log('Page type:', '<?php echo $page_type; ?>');
    </script>
    <?php endif; ?>
</body>
</html>

<?php
logFallbackAccess("FALLBACK PAGE RENDERED SUCCESSFULLY");
?>