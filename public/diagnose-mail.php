<?php
/**
 * Mail Delivery Diagnostic Tool
 * Run this at: http://yoursite.com/public/diagnose-mail.php
 */

require_once __DIR__ . '/../backend/bootstrap.php';
require_once __DIR__ . '/../src/Config.php';
require_once __DIR__ . '/../src/Logger.php';
require_once __DIR__ . '/../src/EmailService.php';
require_once __DIR__ . '/../src/MailTemplates.php';

use App\Config;
use App\Logger;
use App\EmailService;

?>
<!DOCTYPE html>
<html>
<head>
    <title>Mail Delivery Diagnostic</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .section { border: 1px solid #ddd; padding: 15px; margin: 15px 0; }
        .success { background-color: #d4edda; color: #155724; padding: 10px; border-radius: 3px; }
        .error { background-color: #f8d7da; color: #721c24; padding: 10px; border-radius: 3px; }
        .warning { background-color: #fff3cd; color: #856404; padding: 10px; border-radius: 3px; }
        .info { background-color: #d1ecf1; color: #0c5460; padding: 10px; border-radius: 3px; }
        code { background-color: #f4f4f4; padding: 2px 5px; border-radius: 3px; font-family: monospace; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 8px; border-bottom: 1px solid #ddd; }
        th { background-color: #f4f4f4; }
        .label { font-weight: bold; min-width: 150px; }
    </style>
</head>
<body>
<h1>🔧 Mail Delivery Diagnostic</h1>

<?php

// 1. Check PHP mail configuration
echo '<div class="section">';
echo '<h2>1. PHP Mail Configuration</h2>';

$ini_values = [
    'SMTP' => ini_get('SMTP'),
    'smtp_port' => ini_get('smtp_port'),
    'sendmail_from' => ini_get('sendmail_from'),
    'sendmail_path' => ini_get('sendmail_path'),
];

echo '<table>';
foreach ($ini_values as $key => $value) {
    $displayValue = $value ?: '(not set)';
    echo "<tr><td class='label'>$key:</td><td><code>$displayValue</code></td></tr>";
}
echo '</table>';
echo '</div>';

// 2. Check environment configuration
echo '<div class="section">';
echo '<h2>2. Application Mail Configuration</h2>';

$appConfig = [
    'MAIL_DRIVER' => Config::get('MAIL_DRIVER', '(not configured)'),
    'MAIL_FROM_ADDRESS' => Config::get('MAIL_FROM_ADDRESS', '(not configured)'),
    'MAIL_FROM_NAME' => Config::get('MAIL_FROM_NAME', '(not configured)'),
    'MAIL_SMTP_HOST' => Config::get('MAIL_SMTP_HOST', '(not configured - using PHP mail)'),
    'MAIL_SMTP_PORT' => Config::get('MAIL_SMTP_PORT', '(not configured)'),
    'ENABLE_LOGGING' => Config::bool('ENABLE_LOGGING') ? 'YES' : 'NO',
];

echo '<table>';
foreach ($appConfig as $key => $value) {
    // Don't show credentials
    if (strpos($key, 'PASSWORD') === false && strpos($key, 'USERNAME') === false) {
        echo "<tr><td class='label'>$key:</td><td><code>$value</code></td></tr>";
    }
}
echo '</table>';

// Check MAIL_FROM_ADDRESS
$fromEmail = Config::get('MAIL_FROM_ADDRESS');
if (!$fromEmail) {
    echo '<div class="error">❌ MAIL_FROM_ADDRESS is not configured in .env file</div>';
} else if (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
    echo '<div class="error">❌ MAIL_FROM_ADDRESS is not a valid email address</div>';
} else {
    echo '<div class="success">✓ MAIL_FROM_ADDRESS is configured: ' . htmlspecialchars($fromEmail) . '</div>';
}

echo '</div>';

// 3. Check mail function availability
echo '<div class="section">';
echo '<h2>3. Mail Function Status</h2>';

if (function_exists('mail')) {
    echo '<div class="success">✓ mail() function is available</div>';
} else {
    echo '<div class="error">❌ mail() function is not available - email sending not possible</div>';
}

if (extension_loaded('openssl')) {
    echo '<div class="success">✓ OpenSSL extension is loaded (needed for TLS/SSL)</div>';
} else {
    echo '<div class="warning">⚠ OpenSSL extension not loaded - SMTP encryption may not work</div>';
}

if (extension_loaded('sockets')) {
    echo '<div class="success">✓ Sockets extension is loaded</div>';
} else {
    echo '<div class="warning">⚠ Sockets extension not loaded</div>';
}

echo '</div>';

// 4. Check log files
echo '<div class="section">';
echo '<h2>4. Recent Email Logs</h2>';

$logDir = __DIR__ . '/../storage/logs';
if (!is_dir($logDir)) {
    echo '<div class="warning">⚠ Logs directory does not exist</div>';
} else {
    // Show transactions log
    $transactionLog = $logDir . '/transactions.log';
    if (file_exists($transactionLog)) {
        echo '<h3>Recent Email Transactions:</h3>';
        $lines = array_slice(array_reverse(file($transactionLog)), 0, 20);
        echo '<pre style="background-color: #f4f4f4; padding: 10px; overflow-x: auto; max-height: 400px;">';
        foreach ($lines as $line) {
            $decoded = json_decode(trim($line), true);
            if ($decoded && ($decoded['type'] === 'email_sent' || strpos($decoded['message'] ?? '', 'email') !== false)) {
                echo htmlspecialchars(json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) . "\n\n";
            }
        }
        echo '</pre>';
    }
    
    // Show errors log
    $errorLog = $logDir . '/errors.log';
    if (file_exists($errorLog)) {
        echo '<h3>Recent Email Errors:</h3>';
        $lines = array_slice(array_reverse(file($errorLog)), 0, 20);
        $hasEmailErrors = false;
        echo '<pre style="background-color: #fff3cd; padding: 10px; overflow-x: auto; max-height: 400px;">';
        foreach ($lines as $line) {
            $decoded = json_decode(trim($line), true);
            if ($decoded && strpos($decoded['message'] ?? '', 'email') !== false) {
                echo htmlspecialchars(json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) . "\n\n";
                $hasEmailErrors = true;
            }
        }
        if (!$hasEmailErrors) {
            echo '(No email-related errors found)';
        }
        echo '</pre>';
    }
}

echo '</div>';

// 5. Test email sending
echo '<div class="section">';
echo '<h2>5. Send Test Email</h2>';

$testEmail = $_GET['test_email'] ?? '';
if ($testEmail && filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
    echo '<p>Testing email delivery to: <code>' . htmlspecialchars($testEmail) . '</code></p>';
    
    error_clear_last();
    
    $from = Config::get('MAIL_FROM_ADDRESS', 'test@example.com');
    $subject = '[TEST] Mail System Diagnostic - ' . date('Y-m-d H:i:s');
    $body = "This is a test email from your payment system to verify email delivery.\n\n";
    $body .= "Timestamp: " . date('Y-m-d H:i:s') . "\n";
    $body .= "Test Email: " . htmlspecialchars($testEmail) . "\n";
    $body .= "From: " . htmlspecialchars($from) . "\n\n";
    $body .= "If you received this email, your mail system is working correctly.";
    
    $headers = "From: Payment System <{$from}>\r\n";
    $headers .= "Reply-To: {$from}\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $headers .= "X-Mailer: Mail Diagnostic Tool\r\n";
    
    $result = mail($testEmail, $subject, $body, $headers);
    
    echo '<div class="' . ($result ? 'success' : 'error') . '">';
    echo $result ? '✓ mail() returned TRUE - email queued for delivery' : '❌ mail() returned FALSE - email sending failed';
    echo '</div>';
    
    $lastError = error_get_last();
    if ($lastError) {
        echo '<div class="warning">';
        echo '<strong>PHP Error Captured:</strong><br>';
        echo 'Type: ' . $lastError['type'] . '<br>';
        echo 'Message: ' . htmlspecialchars($lastError['message']) . '<br>';
        echo 'File: ' . htmlspecialchars($lastError['file']) . '<br>';
        echo 'Line: ' . $lastError['line'];
        echo '</div>';
    }
    
} else {
    echo '<form method="GET">';
    echo '<label>Enter your email address to send a test email:</label><br>';
    echo '<input type="email" name="test_email" required style="padding: 5px; margin: 10px 0;"><br>';
    echo '<button type="submit" style="padding: 8px 15px; background-color: #007bff; color: white; border: none; border-radius: 3px; cursor: pointer;">Send Test Email</button>';
    echo '</form>';
}

echo '</div>';

// 6. Checklist and recommendations
echo '<div class="section">';
echo '<h2>6. Troubleshooting Checklist</h2>';

echo '<ol style="line-height: 1.8;">';
echo '<li><strong>Check Spam/Junk Folder:</strong> Test emails often end up in spam. Check your email spam folder first.</li>';
echo '<li><strong>Verify MAIL_FROM_ADDRESS:</strong> The "from" address must be valid. Many mail systems reject emails from invalid domains.</li>';
echo '<li><strong>Check Mail Logs:</strong> Look at the "Recent Email Logs" section above for error messages like "Connection refused" or "SMTP failed".</li>';
echo '<li><strong>Test with Different Email:</strong> Try sending to Gmail, Outlook, or another common provider to rule out domain issues.</li>';
echo '<li><strong>Server Mail System:</strong> ';
    if (php_uname('s') === 'Linux') {
        echo 'On Linux, check if sendmail/postfix is running: <code>sudo service postfix status</code>';
    } else {
        echo 'On Windows, configure SMTP in php.ini or use MAIL_SMTP_* settings in .env';
    }
echo '</li>';
echo '<li><strong>Enable Detailed Logging:</strong> Make sure ENABLE_LOGGING=true in .env to capture all email events.</li>';
echo '</ol>';

echo '</div>';

// 7. Common solutions
echo '<div class="section">';
echo '<h2>7. Common Solutions</h2>';

echo '<h3>If mail() returns FALSE:</h3>';
echo '<ul>';
echo '<li>Check PHP error log: <code>tail -f /var/log/php-errors.log</code> (Linux)</li>';
echo '<li>Configure SMTP in php.ini or .env</li>';
echo '<li>Check if sendmail/postfix is running: <code>ps aux | grep mail</code></li>';
echo '</ul>';

echo '<h3>If mail() returns TRUE but email not received:</h3>';
echo '<ul>';
echo '<li>Email likely in spam folder - whitelist sender domain</li>';
echo '<li>Check MAIL_FROM_ADDRESS - domain must have valid DNS/MX records</li>';
echo '<li>Check server firewall - port 25 may be blocked for outgoing mail</li>';
echo '<li>Configure SPF/DKIM records for your domain</li>';
echo '</ul>';

echo '<h3>For SMTP Configuration:</h3>';
echo '<ul>';
echo '<li><strong>Gmail:</strong> MAIL_SMTP_HOST=smtp.gmail.com, PORT=587, ENCRYPTION=tls, use app password</li>';
echo '<li><strong>Mailtrap:</strong> MAIL_SMTP_HOST=smtp.mailtrap.io, PORT=465, ENCRYPTION=ssl</li>';
echo '<li><strong>SendGrid:</strong> MAIL_SMTP_HOST=smtp.sendgrid.net, PORT=587, USERNAME=apikey</li>';
echo '<li><strong>AWS SES:</strong> MAIL_SMTP_HOST=email-smtp.region.amazonaws.com, PORT=587</li>';
echo '</ul>';

echo '</div>';

?>

</body>
</html>
