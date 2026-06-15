<?php

require_once __DIR__ . '/../src/bootstrap.php';

use App\AllSecureService;
use App\Config;
use App\PaymentStorage;
use App\Logger;
use App\EmailService;

// Log callback attempt immediately
$callbackStartTime = microtime(true);
$requestId = bin2hex(random_bytes(8));
error_log("[CALLBACK-$requestId] START: " . $_SERVER['REQUEST_METHOD'] . " " . $_SERVER['REQUEST_URI']);

header('Content-Type: text/plain; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log("[CALLBACK-$requestId] ERROR: Not a POST request, method=" . $_SERVER['REQUEST_METHOD']);
    http_response_code(405);
    echo 'METHOD_NOT_ALLOWED';
    exit;
}

$body = file_get_contents('php://input');
$bodyLength = strlen($body);

error_log("[CALLBACK-$requestId] Body length: $bodyLength bytes");

if ($bodyLength === 0) {
    error_log("[CALLBACK-$requestId] ERROR: Empty request body");
    http_response_code(400);
    echo 'EMPTY_BODY';
    exit;
}

try {
    error_log("[CALLBACK-$requestId] Creating AllSecureService...");
    $service = new AllSecureService();
    error_log("[CALLBACK-$requestId] AllSecureService created successfully");

    $dateHeader = $_SERVER['HTTP_DATE'] ?? $_SERVER['HTTP_X_DATE'] ?? null;
    $signature = $_SERVER['HTTP_X_SIGNATURE']
        ?? $_SERVER['X_SIGNATURE']
        ?? $_SERVER['HTTP_AUTHORIZATION']
        ?? $_SERVER['HTTP_X_AUTHORIZATION']
        ?? null;
    $requestUri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/callback.php';

    error_log("[CALLBACK-$requestId] Date header: " . ($dateHeader ? 'present' : 'missing'));
    error_log("[CALLBACK-$requestId] Signature: " . ($signature ? 'present' : 'missing'));
    error_log("[CALLBACK-$requestId] Request URI: $requestUri");

    if (Config::bool('ALLSECURE_VALIDATE_CALLBACKS', true)) {
        error_log("[CALLBACK-$requestId] Validating callback signature...");
        
        if (!$dateHeader || !$signature) {
            error_log("[CALLBACK-$requestId] ERROR: Missing date header or signature");
            PaymentStorage::append('callback_rejections.jsonl', array(
                'reason' => 'missing_headers',
                'has_date' => !empty($dateHeader),
                'has_signature' => !empty($signature),
                'requestUri' => $requestUri,
                'bodyLength' => $bodyLength,
                'timestamp' => date('Y-m-d H:i:s'),
            ));

            if (Config::bool('ENABLE_LOGGING')) {
                Logger::logError(
                    'Callback rejected: missing headers',
                    array(
                        'has_date' => !empty($dateHeader),
                        'has_signature' => !empty($signature),
                        'request_id' => $requestId,
                    ),
                    'warning'
                );
            }

            http_response_code(400);
            echo 'MISSING_HEADERS';
            exit;
        }

        if (!$service->validateCallback($body, $requestUri, $dateHeader, $signature)) {
            error_log("[CALLBACK-$requestId] ERROR: Signature validation failed");
            PaymentStorage::append('callback_rejections.jsonl', array(
                'reason' => 'invalid_signature',
                'requestUri' => $requestUri,
                'bodyLength' => $bodyLength,
                'timestamp' => date('Y-m-d H:i:s'),
            ));

            if (Config::bool('ENABLE_LOGGING')) {
                Logger::logError(
                    'Callback signature validation failed',
                    array(
                        'reason' => 'invalid_signature',
                        'request_uri' => $requestUri,
                        'body_length' => $bodyLength,
                        'request_id' => $requestId,
                    ),
                    'warning'
                );
            }

            http_response_code(401);
            echo 'INVALID_SIGNATURE';
            exit;
        }
        error_log("[CALLBACK-$requestId] Signature validation passed");
    } else {
        error_log("[CALLBACK-$requestId] Callback validation disabled");
    }

    error_log("[CALLBACK-$requestId] Reading callback data...");
    $callback = $service->readCallback($body);
    error_log("[CALLBACK-$requestId] Callback data read successfully");
    
    $callbackData = AllSecureService::callbackResultToArray($callback);
    error_log("[CALLBACK-$requestId] Callback converted to array");
    
    PaymentStorage::append('callbacks.jsonl', $callbackData);
    error_log("[CALLBACK-$requestId] Callback stored to callbacks.jsonl");

    // Get callback details
    $callbackResult = $callback->getResult();
    $merchantTransactionId = $callback->getMerchantTransactionId();
    
    error_log("[CALLBACK-$requestId] Callback result: $callbackResult, Transaction ID: $merchantTransactionId");
    
    // Log the callback result
    if (Config::bool('ENABLE_LOGGING')) {
        Logger::logTransaction(array(
            'type' => 'callback_result',
            'transaction_id' => $merchantTransactionId,
            'result' => $callbackResult,
            'amount' => $callback->getAmount(),
            'currency' => $callback->getCurrency(),
            'uuid' => $callback->getUuid(),
            'purchase_id' => $callback->getPurchaseId(),
            'payment_method' => $callback->getPaymentMethod(),
            'card_last_four' => $callbackData['cardLastFourDigits'],
            'auth_code' => $callbackData['authCode'],
            'timestamp' => date('Y-m-d H:i:s'),
            'callback_data' => $callbackData,
            'request_id' => $requestId,
        ));
        error_log("[CALLBACK-$requestId] Callback result logged");
    }
    
    $scheduleId = null;
    $scheduleStatus = null;
    
    try {
        error_log("[CALLBACK-$requestId] Extracting schedule information...");
        if (method_exists($callback, 'getScheduleId')) {
            $scheduleId = $callback->getScheduleId();
            error_log("[CALLBACK-$requestId] Schedule ID: " . ($scheduleId ?: 'none'));
        }
    } catch (Throwable $e) {
        error_log("[CALLBACK-$requestId] Error getting schedule ID: " . $e->getMessage());
    }
    
    try {
        if (method_exists($callback, 'getScheduleStatus')) {
            $scheduleStatus = $callback->getScheduleStatus();
            error_log("[CALLBACK-$requestId] Schedule status: " . ($scheduleStatus ?: 'none'));
        }
    } catch (Throwable $e) {
        error_log("[CALLBACK-$requestId] Error getting schedule status: " . $e->getMessage());
    }

    // Log successful callback
    // Log successful callback
    if (Config::bool('ENABLE_LOGGING')) {
        Logger::logPaymentStatus(
            $merchantTransactionId,
            $callbackResult,
            array(
                'type' => 'callback',
                'uuid' => $callback->getUuid(),
                'purchase_id' => $callback->getPurchaseId(),
                'transaction_type' => $callback->getTransactionType(),
                'payment_method' => $callback->getPaymentMethod(),
                'card_last_four' => $callbackData['cardLastFourDigits'],
                'auth_code' => $callbackData['authCode'],
                'schedule_id' => $scheduleId,
                'schedule_status' => $scheduleStatus,
                'timestamp' => date('Y-m-d H:i:s'),
                'request_id' => $requestId,
            )
        );
        error_log("[CALLBACK-$requestId] Payment status logged");
    }

    // Send emails based on callback result
    error_log("[CALLBACK-$requestId] Processing email notifications...");
    try {
        // Get customer email from transaction records
        error_log("[CALLBACK-$requestId] Looking up customer email for transaction: $merchantTransactionId");
        $customerEmail = getCustomerEmailFromTransaction($merchantTransactionId);
        error_log("[CALLBACK-$requestId] Customer email: " . ($customerEmail ?: 'NOT FOUND'));
        
        // Prepare payment data from callback
        $paymentData = array(
            'id' => $merchantTransactionId,
            'email' => $customerEmail,
            'amount' => $callback->getAmount(),
            'currency' => $callback->getCurrency(),
            'transaction_type' => $callback->getTransactionType(),
        );

        if ($callbackResult === 'confirmed') {
            error_log("[CALLBACK-$requestId] Callback result is CONFIRMED - processing success emails");
            
            // Payment was successful - send confirmation email to customer
            if (Config::bool('ENABLE_LOGGING')) {
                Logger::logTransaction(array(
                    'type' => 'email_trigger',
                    'trigger' => 'payment_confirmed',
                    'transaction_id' => $merchantTransactionId,
                    'customer_email' => $customerEmail,
                    'message' => 'Attempting to send payment success email',
                    'request_id' => $requestId,
                ));
            }
            
            if ($customerEmail) {
                error_log("[CALLBACK-$requestId] Sending payment success email to: $customerEmail");
                EmailService::sendPaymentSuccess($paymentData, $callbackData);
                error_log("[CALLBACK-$requestId] Payment success email sent");
            } else {
                error_log("[CALLBACK-$requestId] WARNING: No customer email found, skipping success email");
                if (Config::bool('ENABLE_LOGGING')) {
                    Logger::logError(
                        'Cannot send payment success email - customer email not found',
                        array(
                            'transaction_id' => $merchantTransactionId,
                            'request_id' => $requestId,
                        ),
                        'warning'
                    );
                }
            }
            
            // If recurring, send schedule confirmation
            if (!empty($scheduleId)) {
                error_log("[CALLBACK-$requestId] Schedule ID present ($scheduleId), checking if should send schedule confirmation");
                if ($customerEmail) {
                    if (Config::bool('ENABLE_LOGGING')) {
                        Logger::logTransaction(array(
                            'type' => 'email_trigger',
                            'trigger' => 'schedule_created',
                            'transaction_id' => $merchantTransactionId,
                            'schedule_id' => $scheduleId,
                            'customer_email' => $customerEmail,
                            'message' => 'Attempting to send schedule confirmation email',
                            'request_id' => $requestId,
                        ));
                    }
                    error_log("[CALLBACK-$requestId] Sending schedule confirmation email to: $customerEmail");
                    EmailService::sendScheduleConfirmation($paymentData, $callbackData);
                    error_log("[CALLBACK-$requestId] Schedule confirmation email sent");
                } else {
                    error_log("[CALLBACK-$requestId] No customer email for schedule confirmation");
                }
            } else {
                error_log("[CALLBACK-$requestId] No schedule ID, skipping schedule confirmation email");
            }
        } elseif ($callbackResult === 'failed' || $callbackResult === 'error') {
            error_log("[CALLBACK-$requestId] Callback result is $callbackResult - processing failure emails");
            
            // Payment failed - send failure email to customer
            if (Config::bool('ENABLE_LOGGING')) {
                Logger::logTransaction(array(
                    'type' => 'email_trigger',
                    'trigger' => 'payment_failed',
                    'transaction_id' => $merchantTransactionId,
                    'customer_email' => $customerEmail,
                    'result' => $callbackResult,
                    'message' => 'Attempting to send payment failure email',
                    'request_id' => $requestId,
                ));
            }
            
            if ($customerEmail) {
                error_log("[CALLBACK-$requestId] Sending payment failure email to: $customerEmail");
                EmailService::sendPaymentFailure($paymentData, $callbackData);
                error_log("[CALLBACK-$requestId] Payment failure email sent");
            } else {
                error_log("[CALLBACK-$requestId] WARNING: No customer email found, skipping failure email");
                if (Config::bool('ENABLE_LOGGING')) {
                    Logger::logError(
                        'Cannot send payment failure email - customer email not found',
                        array(
                            'transaction_id' => $merchantTransactionId,
                            'result' => $callbackResult,
                            'request_id' => $requestId,
                        ),
                        'warning'
                    );
                }
            }
        } else {
            error_log("[CALLBACK-$requestId] Unknown callback result: $callbackResult - no emails sent");
        }

        // Always send admin notification
        error_log("[CALLBACK-$requestId] Sending admin notification");
        if (Config::bool('ENABLE_LOGGING')) {
            Logger::logTransaction(array(
                'type' => 'email_trigger',
                'trigger' => 'admin_notification',
                'transaction_id' => $merchantTransactionId,
                'result' => $callbackResult,
                'message' => 'Attempting to send admin callback notification',
                'request_id' => $requestId,
            ));
        }
        EmailService::sendCallbackNotification($callbackData);
        error_log("[CALLBACK-$requestId] Admin notification sent");
        
    } catch (Throwable $emailException) {
        error_log("[CALLBACK-$requestId] ERROR sending emails: " . get_class($emailException) . " - " . $emailException->getMessage());
        
        if (Config::bool('ENABLE_LOGGING')) {
            Logger::logError(
                'Error sending emails during callback: ' . $emailException->getMessage(),
                array(
                    'transaction_id' => $merchantTransactionId,
                    'exception' => get_class($emailException),
                    'file' => $emailException->getFile(),
                    'line' => $emailException->getLine(),
                    'trace' => $emailException->getTraceAsString(),
                    'request_id' => $requestId,
                ),
                'warning'
            );
        }
    }

    $callbackDuration = microtime(true) - $callbackStartTime;
    error_log("[CALLBACK-$requestId] SUCCESS: Callback processed in " . round($callbackDuration * 1000, 2) . "ms");
    http_response_code(200);
    echo 'OK';
}

/**
 * Helper function to get customer email from transaction records
 */
function getCustomerEmailFromTransaction($merchantTransactionId) {
    global $requestId;
    
    try {
        $transactionFile = dirname(__DIR__) . '/storage/transactions.jsonl';
        
        if (!file_exists($transactionFile)) {
            error_log("[CALLBACK-$requestId] Transaction file not found: $transactionFile");
            return null;
        }
        
        error_log("[CALLBACK-$requestId] Reading transaction file: $transactionFile");
        $file = fopen($transactionFile, 'r');
        if (!$file) {
            error_log("[CALLBACK-$requestId] ERROR: Cannot open transaction file for reading");
            return null;
        }
        
        $lineNum = 0;
        while (($line = fgets($file)) !== false) {
            $lineNum++;
            $trimmed = trim($line);
            
            if (empty($trimmed)) {
                continue;
            }
            
            $record = json_decode($trimmed, true);
            
            if (!is_array($record)) {
                error_log("[CALLBACK-$requestId] Line $lineNum: Invalid JSON in transaction file");
                continue;
            }
            
            if (($record['merchantTransactionId'] ?? null) === $merchantTransactionId) {
                $email = $record['customer_email'] ?? null;
                error_log("[CALLBACK-$requestId] Found transaction at line $lineNum, email: " . ($email ?: 'null'));
                fclose($file);
                return $email;
            }
        }
        
        error_log("[CALLBACK-$requestId] Transaction ID not found in file after $lineNum lines");
        fclose($file);
        return null;
        
    } catch (Throwable $e) {
        error_log("[CALLBACK-$requestId] ERROR reading customer email: " . get_class($e) . " - " . $e->getMessage());
        
        if (Config::bool('ENABLE_LOGGING')) {
            Logger::logError(
                'Error reading customer email from transactions: ' . $e->getMessage(),
                array(
                    'transaction_id' => $merchantTransactionId,
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'request_id' => $requestId,
                ),
                'warning'
            );
        }
        return null;
    }
}

} catch (Throwable $exception) {
    error_log("[CALLBACK-$requestId] FATAL ERROR: " . get_class($exception) . " - " . $exception->getMessage());
    error_log("[CALLBACK-$requestId] File: " . $exception->getFile() . ":" . $exception->getLine());
    error_log("[CALLBACK-$requestId] Trace: " . $exception->getTraceAsString());
    error_log("[CALLBACK-$requestId] Body length: $bodyLength bytes");
    
    PaymentStorage::append('callback_errors.jsonl', array(
        'exception' => get_class($exception),
        'message' => $exception->getMessage(),
        'file' => $exception->getFile(),
        'line' => $exception->getLine(),
        'bodyLength' => $bodyLength,
        'timestamp' => date('Y-m-d H:i:s'),
        'request_id' => $requestId,
    ));

    // Log callback processing error
    if (Config::bool('ENABLE_LOGGING')) {
        Logger::logError(
            'Callback processing failed: ' . $exception->getMessage(),
            array(
                'exception' => get_class($exception),
                'body_length' => $bodyLength,
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString(),
                'request_id' => $requestId,
            ),
            'critical'
        );
    }

    $callbackDuration = microtime(true) - $callbackStartTime;
    error_log("[CALLBACK-$requestId] FAILED after " . round($callbackDuration * 1000, 2) . "ms");

    http_response_code(500);
    echo 'ERROR';
}
