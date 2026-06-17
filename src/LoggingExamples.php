<?php
/**
 * LOGGING AND DATABASE SETUP GUIDE
 * 
 * This file demonstrates how to use the Logger and DatabaseManager classes.
 * The logging system supports both file-based and database-based logging.
 */

namespace App;

// ============================================================================
// SETUP: Initialize in your bootstrap or entry point
// ============================================================================

// Initialize Logger with database support (optional)
// If you want to use file-based logging only, omit the DatabaseManager

try {
    // Option 1: File-based logging only (recommended for free hosting)
    Logger::initialize();
    
    // Option 2: File + SQLite database logging
    // $db = new DatabaseManager('sqlite');
    // Logger::initialize($db);
    
    // Option 3: File + MySQL database logging
    // $db = new DatabaseManager('mysql');
    // Logger::initialize($db);
    
    // Option 4: File + PostgreSQL database logging
    // $db = new DatabaseManager('pgsql');
    // Logger::initialize($db);
} catch (Exception $e) {
    error_log("Failed to initialize logging: " . $e->getMessage());
}

// ============================================================================
// USAGE EXAMPLES
// ============================================================================

class LoggingExamples
{
    /**
     * Log a successful payment transaction
     */
    public static function logPaymentTransaction()
    {
        $transactionData = [
            'transaction_id' => 'TXN_12345',
            'payment_id' => 'PAY_98765',
            'amount' => 99.99,
            'currency' => 'EUR',
            'customer_email' => 'customer@example.com',
            'status' => 'completed',
            'method' => 'card',
        ];

        Logger::logTransaction($transactionData);
    }

    /**
     * Log a payment error
     */
    public static function logPaymentError()
    {
        try {
            // ... some payment processing code that throws an exception
            throw new \Exception("Payment gateway timeout");
        } catch (\Exception $e) {
            Logger::logError(
                "Payment processing failed",
                [
                    'payment_id' => 'PAY_98765',
                    'error_code' => $e->getCode(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                ],
                'critical'
            );
        }
    }

    /**
     * Log payment status changes
     */
    public static function logPaymentStatusChange()
    {
        $paymentId = 'PAY_98765';
        $status = 'confirmed';
        $details = [
            'previous_status' => 'pending',
            'gateway_response' => 'approved',
            'authorization_code' => 'AUTH_12345',
        ];

        Logger::logPaymentStatus($paymentId, $status, $details);
    }

    /**
     * Log API requests (e.g., AllSecure Gateway calls)
     */
    public static function logApiRequest()
    {
        Logger::logApiRequest(
            'https://asxgw.paymentsandbox.cloud/payment/create',
            'POST',
            [
                'amount' => 99.99,
                'currency' => 'EUR',
                'description' => 'Website payment',
            ],
            [
                'status' => 200,
                'response_code' => 'SUCCESS',
            ]
        );
    }

    /**
     * Log validation errors
     */
    public static function logValidationError()
    {
        Logger::logError(
            "Validation failed",
            [
                'field' => 'email',
                'value' => 'invalid-email',
                'rule' => 'email_format',
            ],
            'warning'
        );
    }

    /**
     * Log callback processing
     */
    public static function logCallbackProcessing()
    {
        Logger::logTransaction([
            'type' => 'callback',
            'transaction_id' => 'TXN_12345',
            'status' => 'processed',
            'callback_received_at' => gmdate('c'),
            'signature_valid' => true,
        ]);
    }
}

// ============================================================================
// INTEGRATION POINTS
// ============================================================================

/**
 * Integration point 1: Update PaymentStorage to log transactions
 * 
 * In PaymentStorage::append(), add:
 * Logger::logTransaction($record);
 */

/**
 * Integration point 2: Update error handlers
 * 
 * In your error handler, add:
 * Logger::logError("Error message", [
 *     'file' => __FILE__,
 *     'line' => __LINE__,
 *     'trace' => debug_backtrace(),
 * ], 'critical');
 */

/**
 * Integration point 3: Update API calls
 * 
 * When calling AllSecure API, log requests and responses:
 * Logger::logApiRequest($endpoint, 'POST', $requestData, $response);
 */

/**
 * Integration point 4: Update callback handlers
 * 
 * In callback.php, log the received callback:
 * Logger::logPaymentStatus($paymentId, $status, $details);
 */

// ============================================================================
// DATABASE QUERIES (if using database logging)
// ============================================================================

/**
 * Example: Query logs from database
 */
class LogQueries
{
    /**
     * Get recent transaction logs
     */
    public static function getRecentTransactions($days = 7)
    {
        $filters = [
            'from_date' => gmdate('c', strtotime("-{$days} days")),
        ];
        return Logger::getLogs('transactions', $filters);
    }

    /**
     * Get error logs by IP address
     */
    public static function getErrorsByIp($ipAddress)
    {
        $filters = ['ip_address' => $ipAddress];
        return Logger::getLogs('errors', $filters);
    }

    /**
     * Get payment status changes
     */
    public static function getPaymentLogs($paymentId)
    {
        $filters = [];
        $logs = Logger::getLogs('payments');
        
        // Filter by payment_id in application code
        return array_filter($logs, function($log) use ($paymentId) {
            return $log['payment_id'] === $paymentId;
        });
    }

    /**
     * Clean up old logs (keep only 30 days)
     */
    public static function cleanupOldLogs()
    {
        Logger::clearOldLogs(30);
    }
}

// ============================================================================
// FILE STRUCTURE
// ============================================================================

/**
 * Logs are stored in:
 * storage/logs/
 * ├── transactions.log  - Payment transaction records
 * ├── errors.log        - Error and exception logs
 * ├── payments.log      - Payment status changes
 * └── api_requests.log  - API request logs
 * 
 * If using SQLite database:
 * storage/database.sqlite - SQLite database file with tables:
 * ├── logs_transactions
 * ├── logs_errors
 * ├── logs_payments
 * └── logs_api_requests
 */

// ============================================================================
// ENVIRONMENT CONFIGURATION
// ============================================================================

/**
 * .env file settings:
 * 
 * # Enable file logging
 * ENABLE_LOGGING=true
 * 
 * # Enable database logging (if database is available)
 * ENABLE_DATABASE_LOGGING=true
 * 
 * # Database type: sqlite, mysql, or pgsql
 * DB_TYPE=sqlite
 * 
 * # For MySQL/PostgreSQL
 * # DB_HOST=localhost
 * # DB_PORT=3306 (MySQL) or 5432 (PostgreSQL)
 * # DB_DATABASE=payments
 * # DB_USERNAME=root
 * # DB_PASSWORD=your_password
 */
