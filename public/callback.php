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

if (Config::bool('ENABLE_LOGGING')) {
    Logger::logTransaction(array(
        'type' => 'callback_received',
        'request_id' => $requestId,
        'method' => $_SERVER['REQUEST_METHOD'],
        'uri' => $_SERVER['REQUEST_URI'],
        'timestamp' => date('Y-m-d H:i:s'),
    ));
}

header('Content-Type: text/plain; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if (Config::bool('ENABLE_LOGGING')) {
        Logger::logError(
            'Callback rejected: not a POST request',
            array('request_id' => $requestId, 'method' => $_SERVER['REQUEST_METHOD']),
            'warning'
        );
    }
    http_response_code(405);
    echo 'METHOD_NOT_ALLOWED';
    exit;
}

$body = file_get_contents('php://input');
$bodyLength = strlen($body);

if (Config::bool('ENABLE_LOGGING')) {
    Logger::logTransaction(array(
        'type' => 'callback_body_received',
        'request_id' => $requestId,
        'body_length' => $bodyLength,
        'timestamp' => date('Y-m-d H:i:s'),
    ));
}

if ($bodyLength === 0) {
    if (Config::bool('ENABLE_LOGGING')) {
        Logger::logError(
            'Callback rejected: empty request body',
            array('request_id' => $requestId),
            'warning'
        );
    }
    http_response_code(400);
    echo 'EMPTY_BODY';
    exit;
}

try {
    $service = new AllSecureService();

    $dateHeader = $_SERVER['HTTP_DATE'] ?? $_SERVER['HTTP_X_DATE'] ?? null;
    $signature = $_SERVER['HTTP_X_SIGNATURE']
        ?? $_SERVER['X_SIGNATURE']
        ?? $_SERVER['HTTP_AUTHORIZATION']
        ?? $_SERVER['HTTP_X_AUTHORIZATION']
        ?? null;
    $requestUri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/callback.php';

    if (Config::bool('ENABLE_LOGGING')) {
        Logger::logTransaction(array(
            'type' => 'callback_headers_check',
            'request_id' => $requestId,
            'has_date_header' => !empty($dateHeader),
            'has_signature' => !empty($signature),
            'request_uri' => $requestUri,
            'timestamp' => date('Y-m-d H:i:s'),
        ));
    }

    if (Config::bool('ALLSECURE_VALIDATE_CALLBACKS', true)) {
        if (!$dateHeader || !$signature) {
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
    }

    $callback = $service->readCallback($body);
    $callbackData = AllSecureService::callbackResultToArray($callback);
    
    PaymentStorage::append('callbacks.jsonl', $callbackData);

    // Get callback details
    $callbackResult = $callback->getResult();
    $merchantTransactionId = $callback->getMerchantTransactionId();
    
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
        if (method_exists($callback, 'getScheduleId')) {
            $scheduleId = $callback->getScheduleId();
        }
    } catch (Throwable $e) {
        if (Config::bool('ENABLE_LOGGING')) {
            Logger::logError(
                'Error getting schedule ID from callback',
                array(
                    'request_id' => $requestId,
                    'exception' => get_class($e),
                    'message' => $e->getMessage(),
                ),
                'warning'
            );
        }
    }
    
    try {
        if (method_exists($callback, 'getScheduleStatus')) {
            $scheduleStatus = $callback->getScheduleStatus();
        }
    } catch (Throwable $e) {
        if (Config::bool('ENABLE_LOGGING')) {
            Logger::logError(
                'Error getting schedule status from callback',
                array(
                    'request_id' => $requestId,
                    'exception' => get_class($e),
                    'message' => $e->getMessage(),
                ),
                'warning'
            );
        }
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
    try {
        // Get customer email from transaction records
        $customerEmail = getCustomerEmailFromTransaction($merchantTransactionId);
        
        // Prepare payment data from callback
        $paymentData = array(
            'id' => $merchantTransactionId,
            'email' => $customerEmail,
            'amount' => $callback->getAmount(),
            'currency' => $callback->getCurrency(),
            'transaction_type' => $callback->getTransactionType(),
        );

        if ($callbackResult === 'confirmed') {
            
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
                EmailService::sendPaymentSuccess($paymentData, $callbackData);
            } else {
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
                    EmailService::sendScheduleConfirmation($paymentData, $callbackData);
                }
            }
        } elseif ($callbackResult === 'failed' || $callbackResult === 'error') {
            
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
                EmailService::sendPaymentFailure($paymentData, $callbackData);
            } else {
                if (Config::bool('ENABLE_LOGGING')) {
                    Logger::logError(
                        'Cannot send payment failure email - customer email not found',
                        array(
                            'transaction_id' => $merchantTransactionId,
                            'result' => $callbackResult,
                            'request_id' => $requestId,
                        )
            }
        }

        // Always send admin notification
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
        
    } catch (Throwable $emailException) {
        
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
    if (Config::bool('ENABLE_LOGGING')) {
        Logger::logTransaction(array(
            'type' => 'callback_processed',
            'request_id' => $requestId,
            'transaction_id' => $merchantTransactionId,
            'result' => $callbackResult,
            'duration_ms' => round($callbackDuration * 1000, 2),
            'timestamp' => date('Y-m-d H:i:s'),
        ));
    }
    http_response_code(200);
    echo 'OK';
}

/**
 * Helper function to get customer email from transaction records
 */
function getCustomerEmailFromTransaction($merchantTransactionId) {
    try {
        $transactionFile = dirname(__DIR__) . '/storage/transactions.jsonl';
        
        if (!file_exists($transactionFile)) {
            return null;
        }
        
        $file = fopen($transactionFile, 'r');
        if (!$file) {
            if (Config::bool('ENABLE_LOGGING')) {
                Logger::logError(
                    'Cannot open transaction file for reading customer email',
                    array('file' => $transactionFile),
                    'warning'
                );
            }
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
                continue;
            }
            
            if (($record['merchantTransactionId'] ?? null) === $merchantTransactionId) {
                $email = $record['customer_email'] ?? null;
                fclose($file);
                return $email;
            }
        }
        
        fclose($file);
        return null;
        
    } catch (Throwable $e) {
        if (Config::bool('ENABLE_LOGGING')) {
            Logger::logError(
                'Error reading customer email from transaction file',
                array(
                    'transaction_id' => $merchantTransactionId,
                    'exception' => get_class($e),
                    'message' => $e->getMessage(),
                ),
                'error'
            );
        }
        
        if (Config::bool('ENABLE_LOGGING')) {
            Logger::logError(
                'Error reading customer email from transactions: ' . $e->getMessage(),
                array(
                    'transaction_id' => $merchantTransactionId,
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ),
                'warning'
            );
        }
        return null;
    }
}

} catch (Throwable $exception) {
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

    http_response_code(500);
    echo 'ERROR';
}
