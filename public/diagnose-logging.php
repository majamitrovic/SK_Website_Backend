<?php
/**
 * Logging Diagnostic Script
 * Place this in public/ folder and access via browser
 * http://yoursite.com/diagnose-logging.php
 */

require_once __DIR__ . '/../src/bootstrap.php';

use App\Logger;
use App\Config;

?>
<!DOCTYPE html>
<html>
<head>
    <title>Logging Diagnostics</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .check { margin: 20px 0; padding: 15px; border: 1px solid #ccc; border-radius: 5px; }
        .pass { background-color: #d4edda; border-color: #28a745; }
        .fail { background-color: #f8d7da; border-color: #dc3545; }
        .info { background-color: #d1ecf1; border-color: #17a2b8; }
        h2 { margin-top: 0; }
        code { background-color: #f4f4f4; padding: 2px 6px; border-radius: 3px; }
    </style>
</head>
<body>
    <h1>🔍 Logging System Diagnostics</h1>

    <?php
    // Check 1: .env file and configuration
    ?>
    <div class="check <?php echo file_exists($root . '/.env') ? 'pass' : 'fail'; ?>">
        <h2>1. Environment File (.env)</h2>
        <?php
        $envPath = $root . '/.env';
        if (file_exists($envPath)) {
            echo "✓ <code>.env</code> file exists<br>";
            echo "Path: <code>$envPath</code>";
        } else {
            echo "✗ <code>.env</code> file NOT found<br>";
            echo "Path: <code>$envPath</code><br>";
            echo "<strong>Action:</strong> Create .env file from .env.example with ENABLE_LOGGING=true";
        }
        ?>
    </div>

    <?php
    // Check 2: Logging enabled
    ?>
    <div class="check <?php echo Config::bool('ENABLE_LOGGING') ? 'pass' : 'fail'; ?>">
        <h2>2. Logging Configuration</h2>
        <?php
        $loggingEnabled = Config::bool('ENABLE_LOGGING');
        $dbLoggingEnabled = Config::bool('ENABLE_DATABASE_LOGGING');
        $dbType = Config::get('DB_TYPE', 'sqlite');
        
        echo "ENABLE_LOGGING: <code>" . ($loggingEnabled ? 'true' : 'false') . "</code><br>";
        echo "ENABLE_DATABASE_LOGGING: <code>" . ($dbLoggingEnabled ? 'true' : 'false') . "</code><br>";
        echo "DB_TYPE: <code>$dbType</code>";
        ?>
    </div>

    <?php
    // Check 3: Storage directory
    ?>
    <div class="check <?php echo is_dir($root . '/storage') ? 'pass' : 'fail'; ?>">
        <h2>3. Storage Directory</h2>
        <?php
        $storagePath = $root . '/storage';
        if (is_dir($storagePath)) {
            echo "✓ <code>storage/</code> directory exists<br>";
            echo "Path: <code>$storagePath</code><br>";
            echo "Writable: <code>" . (is_writable($storagePath) ? 'YES ✓' : 'NO ✗') . "</code>";
        } else {
            echo "✗ <code>storage/</code> directory NOT found";
        }
        ?>
    </div>

    <?php
    // Check 4: Storage logs directory
    ?>
    <div class="check <?php echo is_dir($root . '/storage/logs') ? 'pass' : 'fail'; ?>">
        <h2>4. Storage Logs Directory</h2>
        <?php
        $logsPath = $root . '/storage/logs';
        if (is_dir($logsPath)) {
            echo "✓ <code>storage/logs/</code> directory exists<br>";
            echo "Path: <code>$logsPath</code><br>";
            echo "Writable: <code>" . (is_writable($logsPath) ? 'YES ✓' : 'NO ✗') . "</code>";
        } else {
            echo "✗ <code>storage/logs/</code> directory NOT found<br>";
            echo "<strong>Action:</strong> Create directory or run: <code>mkdir -p storage/logs</code>";
        }
        ?>
    </div>

    <?php
    // Check 5: Logger class
    ?>
    <div class="check <?php echo class_exists('App\Logger') ? 'pass' : 'fail'; ?>">
        <h2>5. Logger Class</h2>
        <?php
        if (class_exists('App\Logger')) {
            echo "✓ Logger class found and loaded<br>";
            $reflectionClass = new ReflectionClass('App\Logger');
            echo "File: <code>" . $reflectionClass->getFileName() . "</code>";
        } else {
            echo "✗ Logger class NOT found";
        }
        ?>
    </div>

    <?php
    // Check 6: Test actual logging
    ?>
    <div class="check info">
        <h2>6. Test Logging</h2>
        <?php
        try {
            // Ensure logs directory exists
            if (!is_dir($logsPath)) {
                @mkdir($logsPath, 0755, true);
            }

            // Test logging a transaction
            Logger::logTransaction([
                'test' => true,
                'timestamp' => gmdate('c'),
                'message' => 'Diagnostic test entry',
            ]);

            echo "✓ Transaction log attempt executed<br>";

            // Test logging an error
            Logger::logError('Diagnostic test error', ['test' => true], 'info');
            echo "✓ Error log attempt executed<br>";

            // Check if files were created
            $transactionLog = $logsPath . '/transactions.log';
            $errorLog = $logsPath . '/errors.log';

            if (file_exists($transactionLog)) {
                echo "✓ <code>transactions.log</code> created successfully<br>";
                echo "File size: " . filesize($transactionLog) . " bytes";
            } else {
                echo "✗ <code>transactions.log</code> was NOT created";
            }

            if (file_exists($errorLog)) {
                echo "✓ <code>errors.log</code> created successfully<br>";
                echo "File size: " . filesize($errorLog) . " bytes";
            } else {
                echo "✗ <code>errors.log</code> was NOT created";
            }
        } catch (Throwable $e) {
            echo "✗ Logging failed with error:<br>";
            echo "<code>" . htmlspecialchars($e->getMessage()) . "</code><br>";
            echo "File: " . htmlspecialchars($e->getFile()) . "<br>";
            echo "Line: " . $e->getLine();
        }
        ?>
    </div>

    <?php
    // Check 7: File permissions
    ?>
    <div class="check info">
        <h2>7. File Permissions</h2>
        <?php
        echo "storage/ permissions: <code>" . substr(sprintf('%o', fileperms($root . '/storage')), -4) . "</code><br>";
        if (is_dir($logsPath)) {
            echo "storage/logs/ permissions: <code>" . substr(sprintf('%o', fileperms($logsPath)), -4) . "</code><br>";
        }
        echo "PHP running as: <code>" . get_current_user() . "</code><br>";
        echo "Web server user: <code>" . (function_exists('posix_getuid') ? posix_getpwuid(posix_geteuid())['name'] : $_SERVER['SERVER_SOFTWARE'] ?? 'unknown') . "</code>";
        ?>
    </div>

    <?php
    // Check 8: Recent files in storage
    ?>
    <div class="check info">
        <h2>8. Files in storage/logs/</h2>
        <?php
        if (is_dir($logsPath)) {
            $files = glob($logsPath . '/*');
            if (empty($files)) {
                echo "No log files found yet";
            } else {
                echo "Found " . count($files) . " file(s):<br>";
                foreach ($files as $file) {
                    $size = filesize($file);
                    echo "- <code>" . basename($file) . "</code> (" . $size . " bytes)<br>";
                }
            }
        } else {
            echo "Logs directory does not exist";
        }
        ?>
    </div>

    <hr>
    <h3>Summary</h3>
    <?php
    $checks = [
        'env' => file_exists($root . '/.env'),
        'logging_enabled' => Config::bool('ENABLE_LOGGING'),
        'storage' => is_dir($root . '/storage') && is_writable($root . '/storage'),
        'logs_dir' => is_dir($logsPath) && is_writable($logsPath),
        'logger_class' => class_exists('App\Logger'),
    ];

    $allPass = !in_array(false, $checks);
    
    echo "<p>" . ($allPass ? "✓ All checks passed! Logging should be working." : "✗ Some checks failed. See above for details.") . "</p>";
    ?>
</body>
</html>
