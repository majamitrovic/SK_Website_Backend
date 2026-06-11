<?php

namespace App;

final class Logger
{
    private static $database;

    public static function initialize(DatabaseManager $database = null)
    {
        self::$database = $database;
        
        // Create tables if using database
        if (self::$database) {
            self::$database->initializeLogTables();
        }
    }

    /**
     * Log a transaction
     */
    public static function logTransaction(array $transactionData)
    {
        $logEntry = [
            'timestamp' => gmdate('c'),
            'type' => 'transaction',
            'data' => json_encode($transactionData),
            'ip_address' => self::getClientIp(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
        ];

        // Log to file
        self::logToFile('transactions', $logEntry);

        // Log to database if available
        if (self::$database) {
            self::$database->insertLog('transactions', $logEntry);
        }
    }

    /**
     * Log an error
     */
    public static function logError($errorMessage, array $context = [], $severity = 'error')
    {
        $logEntry = [
            'timestamp' => gmdate('c'),
            'type' => 'error',
            'severity' => $severity,
            'message' => $errorMessage,
            'context' => json_encode($context),
            'ip_address' => self::getClientIp(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'file' => $context['file'] ?? null,
            'line' => $context['line'] ?? null,
            'trace' => $context['trace'] ?? null,
        ];

        // Log to file
        self::logToFile('errors', $logEntry);

        // Log to database if available
        if (self::$database) {
            self::$database->insertLog('errors', $logEntry);
        }
    }

    /**
     * Log payment status
     */
    public static function logPaymentStatus($paymentId, $status, array $details = [])
    {
        $logEntry = [
            'timestamp' => gmdate('c'),
            'type' => 'payment_status',
            'payment_id' => $paymentId,
            'status' => $status,
            'details' => json_encode($details),
            'ip_address' => self::getClientIp(),
        ];

        // Log to file
        self::logToFile('payments', $logEntry);

        // Log to database if available
        if (self::$database) {
            self::$database->insertLog('payments', $logEntry);
        }
    }

    /**
     * Log API request
     */
    public static function logApiRequest($endpoint, $method, array $data = [], $response = null)
    {
        $logEntry = [
            'timestamp' => gmdate('c'),
            'type' => 'api_request',
            'endpoint' => $endpoint,
            'method' => $method,
            'request_data' => json_encode($data),
            'response_status' => $response['status'] ?? null,
            'ip_address' => self::getClientIp(),
        ];

        // Log to file
        self::logToFile('api_requests', $logEntry);

        // Log to database if available
        if (self::$database) {
            self::$database->insertLog('api_requests', $logEntry);
        }
    }

    /**
     * Log to JSON file
     */
    private static function logToFile($logType, array $logEntry)
    {
        $logsDir = Config::storagePath('logs');
        
        if (!is_dir($logsDir)) {
            mkdir($logsDir, 0775, true);
        }

        // Create separate log files for each type
        $logFile = $logsDir . DIRECTORY_SEPARATOR . $logType . '.log';
        
        $logLine = json_encode($logEntry, JSON_UNESCAPED_SLASHES) . PHP_EOL;
        file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);
    }

    /**
     * Get client IP address
     */
    private static function getClientIp()
    {
        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            return $_SERVER['HTTP_CF_CONNECTING_IP'];
        }

        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return trim($ips[0]);
        }

        if (!empty($_SERVER['HTTP_X_FORWARDED'])) {
            return $_SERVER['HTTP_X_FORWARDED'];
        }

        if (!empty($_SERVER['HTTP_FORWARDED_FOR'])) {
            return $_SERVER['HTTP_FORWARDED_FOR'];
        }

        if (!empty($_SERVER['HTTP_FORWARDED'])) {
            return $_SERVER['HTTP_FORWARDED'];
        }

        return $_SERVER['REMOTE_ADDR'] ?? null;
    }

    /**
     * Get logs by type and filters
     */
    public static function getLogs($logType, array $filters = [])
    {
        if (!self::$database) {
            return [];
        }

        return self::$database->getLogs($logType, $filters);
    }

    /**
     * Clear old logs (older than days specified)
     */
    public static function clearOldLogs($daysOld = 30)
    {
        if (!self::$database) {
            return;
        }

        self::$database->clearOldLogs($daysOld);
    }
}
