<?php
/**
 * LOGGING INTEGRATION SETUP GUIDE
 * 
 * Follow these steps to integrate the logging system into your existing code.
 */

namespace App;

// ============================================================================
// STEP 1: Add to src/bootstrap.php
// ============================================================================

/*
After existing code in bootstrap.php, add:

use App\Logger;
use App\DatabaseManager;

// Initialize logging
try {
    // Option 1: File-based logging only (recommended)
    Logger::initialize();
    
    // Option 2: With SQLite database (uncomment if needed)
    // if (Config::bool('ENABLE_DATABASE_LOGGING')) {
    //     $db = new DatabaseManager(Config::get('DB_TYPE', 'sqlite'));
    //     Logger::initialize($db);
    // }
} catch (\Exception $e) {
    error_log("Failed to initialize logging: " . $e->getMessage());
}
*/

// ============================================================================
// STEP 2: Update src/PaymentStorage.php
// ============================================================================

/*
Add logging to the append method:

public static function append($file, array $record)
{
    $path = Config::storagePath($file);
    $dir = dirname($path);

    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    $record['recordedAt'] = gmdate('c');
    file_put_contents($path, json_encode($record, JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND | LOCK_EX);
    
    // ADD THIS LINE: Log the transaction
    if (Config::bool('ENABLE_LOGGING')) {
        Logger::logTransaction($record);
    }
}
*/

// ============================================================================
// STEP 3: Add Error Handler
// ============================================================================

/*
Create a new file: src/ErrorHandler.php

```php
<?php

namespace App;

final class ErrorHandler
{
    public static function register()
    {
        set_error_handler([self::class, 'handle']);
        set_exception_handler([self::class, 'handleException']);
    }

    public static function handle($errno, $errstr, $errfile, $errline)
    {
        if (!Config::bool('ENABLE_LOGGING')) {
            return false;
        }

        Logger::logError($errstr, [
            'file' => $errfile,
            'line' => $errline,
            'error_code' => $errno,
        ], 'warning');

        return false;
    }

    public static function handleException(\Throwable $exception)
    {
        if (Config::bool('ENABLE_LOGGING')) {
            Logger::logError(
                $exception->getMessage(),
                [
                    'file' => $exception->getFile(),
                    'line' => $exception->getLine(),
                    'trace' => $exception->getTraceAsString(),
                ],
                'critical'
            );
        }

        http_response_code(500);
        echo json_encode([
            'error' => 'Internal Server Error',
            'message' => $exception->getMessage(),
        ]);
    }
}
```

Then in bootstrap.php, add:
ErrorHandler::register();
*/

// ============================================================================
// STEP 4: Update API Payment Handler
// ============================================================================

/*
In backend/api/pay.php (or wherever you handle payments):

$response = $connector->request('Payment.create', $createPaymentRequest);

// ADD LOGGING
if (Config::bool('ENABLE_LOGGING')) {
    Logger::logApiRequest(
        'Payment.create',
        'POST',
        (array)$createPaymentRequest,
        [
            'status' => $response->isSuccessful() ? 'success' : 'failed',
            'error_codes' => $response->getErrorCodes(),
        ]
    );
}
*/

// ============================================================================
// STEP 5: Update Callback Handler
// ============================================================================

/*
In public/callback.php:

// After validating signature
if (Config::bool('ENABLE_LOGGING')) {
    Logger::logPaymentStatus($paymentId, $status, [
        'transaction_uuid' => $transaction->getTransactionUuid() ?? null,
        'status_code' => $transaction->getStatusCode() ?? null,
        'callback_received_at' => gmdate('c'),
    ]);
}
*/

// ============================================================================
// STEP 6: Update Status Handler
// ============================================================================

/*
In public/status.php (or API endpoint):

// When checking payment status
if (Config::bool('ENABLE_LOGGING')) {
    Logger::logApiRequest(
        'Payment.getStatus',
        'GET',
        ['payment_id' => $paymentId],
        [
            'status' => $payment ? 'found' : 'not_found',
            'payment_status' => $payment ? $payment['status'] : null,
        ]
    );
}
*/

// ============================================================================
// STEP 7: Add to Config Class Methods (Optional)
// ============================================================================

/*
Add these helper methods to src/Config.php:

public static function loggingEnabled()
{
    return self::bool('ENABLE_LOGGING', true);
}

public static function databaseLoggingEnabled()
{
    return self::bool('ENABLE_DATABASE_LOGGING', false);
}

public static function databaseType()
{
    return self::get('DB_TYPE', 'sqlite');
}
*/

// ============================================================================
// STEP 8: Update .env File
// ============================================================================

/*
Copy the new database configuration options from .env.example to your .env:

# Database Configuration
DB_TYPE=sqlite

# Logging Configuration
ENABLE_LOGGING=true
ENABLE_DATABASE_LOGGING=false
*/

// ============================================================================
// STEP 9: File Permissions
// ============================================================================

/*
Ensure storage directory is writable:

chmod 775 storage/
chmod 755 storage/logs/

On Windows, right-click storage/ folder > Properties > Security > Edit
and ensure your web server user has write permissions.
*/

// ============================================================================
// QUICK REFERENCE: Log Types and Methods
// ============================================================================

/*
1. Transaction Logging
   Logger::logTransaction($transactionData);
   
2. Error Logging
   Logger::logError($message, $context, $severity);
   
3. Payment Status
   Logger::logPaymentStatus($paymentId, $status, $details);
   
4. API Requests
   Logger::logApiRequest($endpoint, $method, $data, $response);

5. Query Logs
   Logger::getLogs($logType, $filters);
   
6. Clear Old Logs
   Logger::clearOldLogs(30); // Keep 30 days
*/

// ============================================================================
// EXAMPLE: Complete Integration in a Payment Handler
// ============================================================================

class PaymentHandlerExample
{
    public function processPayment(array $paymentData)
    {
        try {
            // Validate input
            if (!$this->validatePaymentData($paymentData)) {
                if (Config::bool('ENABLE_LOGGING')) {
                    Logger::logError(
                        "Invalid payment data",
                        $paymentData,
                        'warning'
                    );
                }
                throw new ValidationException("Invalid payment data");
            }

            // Create payment
            $transaction = $this->createTransaction($paymentData);

            // Log successful transaction
            if (Config::bool('ENABLE_LOGGING')) {
                Logger::logTransaction([
                    'transaction_id' => $transaction['id'],
                    'amount' => $paymentData['amount'],
                    'currency' => $paymentData['currency'],
                    'status' => 'created',
                ]);
            }

            return $transaction;

        } catch (\Exception $e) {
            if (Config::bool('ENABLE_LOGGING')) {
                Logger::logError(
                    $e->getMessage(),
                    [
                        'file' => __FILE__,
                        'line' => __LINE__,
                        'trace' => $e->getTraceAsString(),
                        'payment_data' => $paymentData,
                    ],
                    'critical'
                );
            }

            throw $e;
        }
    }

    private function validatePaymentData(array $data)
    {
        return isset($data['amount']) && isset($data['currency']);
    }

    private function createTransaction(array $data)
    {
        // Payment creation logic
        return ['id' => uniqid('TXN_')];
    }
}

// ============================================================================
// MIGRATION CHECKLIST
// ============================================================================

/*
Use this checklist when integrating logging:

[ ] Copy Logger.php and DatabaseManager.php to src/
[ ] Update .env with database configuration
[ ] Update bootstrap.php to initialize Logger
[ ] Create ErrorHandler.php for exception handling
[ ] Update PaymentStorage.php to log transactions
[ ] Update payment creation endpoints to log API calls
[ ] Update callback.php to log payment status
[ ] Update status endpoints to log queries
[ ] Set proper file permissions on storage/
[ ] Test logging by checking storage/logs/ directory
[ ] Verify database tables created (if using DB logging)
[ ] Monitor logs in production

Free Hosting Considerations:
[ ] Using SQLite (no server setup needed)
[ ] File logs stored in storage/logs/
[ ] Regular cleanup of old logs configured
[ ] Logs directory protected from web access
*/
