<?php

require_once __DIR__ . '/../src/bootstrap.php';

use App\AllSecureService;
use App\Config;
use App\PaymentStorage;
use App\Logger;
use App\EmailService;

header('Content-Type: text/plain; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo 'METHOD_NOT_ALLOWED';
    exit;
}

$body = file_get_contents('php://input');

try {
    $service = new AllSecureService();

    $dateHeader = $_SERVER['HTTP_DATE'] ?? $_SERVER['HTTP_X_DATE'] ?? null;
    $signature = $_SERVER['HTTP_X_SIGNATURE']
        ?? $_SERVER['X_SIGNATURE']
        ?? $_SERVER['HTTP_AUTHORIZATION']
        ?? $_SERVER['HTTP_X_AUTHORIZATION']
        ?? null;
    $requestUri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/callback.php';

    if (Config::bool('ALLSECURE_VALIDATE_CALLBACKS', true) && (!$dateHeader || !$signature || !$service->validateCallback($body, $requestUri, $dateHeader, $signature))) {
        PaymentStorage::append('callback_rejections.jsonl', array(
            'reason' => 'invalid_signature',
            'requestUri' => $requestUri,
            'bodyLength' => strlen($body),
        ));

        // Log callback rejection
        if (Config::bool('ENABLE_LOGGING')) {
            Logger::logError(
                'Callback signature validation failed',
                array(
                    'reason' => 'invalid_signature',
                    'request_uri' => $requestUri,
                    'body_length' => strlen($body),
                ),
                'warning'
            );
        }

        http_response_code(401);
        echo 'INVALID_SIGNATURE';
        exit;
    }

    $callback = $service->readCallback($body);
    $callbackData = AllSecureService::callbackResultToArray($callback);
    PaymentStorage::append('callbacks.jsonl', $callbackData);

    // Get callback details
    $callbackResult = $callback->getResult();
    $scheduleId = null;
    $scheduleStatus = null;
    
    try {
        if (method_exists($callback, 'getScheduleId')) {
            $scheduleId = $callback->getScheduleId();
        }
    } catch (Throwable $e) {
        // Schedule ID not available
    }
    
    try {
        if (method_exists($callback, 'getScheduleStatus')) {
            $scheduleStatus = $callback->getScheduleStatus();
        }
    } catch (Throwable $e) {
        // Schedule status not available
    }

    // Log successful callback
    if (Config::bool('ENABLE_LOGGING')) {
        Logger::logPaymentStatus(
            $callback->getMerchantTransactionId(),
            $callbackResult,
            array(
                'type' => 'callback',
                'uuid' => $callback->getUuid(),
                'purchase_id' => $callback->getPurchaseId(),
                'transaction_type' => $callback->getTransactionType(),
                'schedule_id' => $scheduleId,
                'schedule_status' => $scheduleStatus,
            )
        );
    }

    // Send emails based on callback result
    try {
        // Prepare payment data from callback
        $paymentData = array(
            'id' => $callback->getMerchantTransactionId(),
            'email' => $callback->getMerchantTransactionId(), // You may need to get this from your database
            'amount' => $callback->getAmount(),
            'currency' => $callback->getCurrency(),
            'transaction_type' => $callback->getTransactionType(),
        );

        if ($callbackResult === 'confirmed') {
            // Payment was successful - send confirmation email
            if (Config::bool('ENABLE_LOGGING')) {
                Logger::logTransaction(array(
                    'type' => 'email_trigger',
                    'trigger' => 'payment_confirmed',
                    'transaction_id' => $callback->getMerchantTransactionId(),
                    'message' => 'Attempting to send payment success email',
                ));
            }
            EmailService::sendPaymentSuccess($paymentData, $callbackData);
            
            // If recurring, send schedule confirmation
            if (!empty($scheduleId)) {
                if (Config::bool('ENABLE_LOGGING')) {
                    Logger::logTransaction(array(
                        'type' => 'email_trigger',
                        'trigger' => 'schedule_created',
                        'transaction_id' => $callback->getMerchantTransactionId(),
                        'schedule_id' => $scheduleId,
                        'message' => 'Attempting to send schedule confirmation email',
                    ));
                }
                EmailService::sendScheduleConfirmation($paymentData, $callbackData);
            }
        } elseif ($callbackResult === 'failed' || $callbackResult === 'error') {
            // Payment failed - send failure email
            if (Config::bool('ENABLE_LOGGING')) {
                Logger::logTransaction(array(
                    'type' => 'email_trigger',
                    'trigger' => 'payment_failed',
                    'transaction_id' => $callback->getMerchantTransactionId(),
                    'result' => $callbackResult,
                    'message' => 'Attempting to send payment failure email',
                ));
            }
            EmailService::sendPaymentFailure($paymentData, $callbackData);
        }

        // Always send admin notification
        if (Config::bool('ENABLE_LOGGING')) {
            Logger::logTransaction(array(
                'type' => 'email_trigger',
                'trigger' => 'admin_notification',
                'transaction_id' => $callback->getMerchantTransactionId(),
                'message' => 'Attempting to send admin callback notification',
            ));
        }
        EmailService::sendCallbackNotification($callbackData);
        
    } catch (Throwable $emailException) {
        if (Config::bool('ENABLE_LOGGING')) {
            Logger::logError(
                'Error sending emails during callback: ' . $emailException->getMessage(),
                array(
                    'transaction_id' => $callback->getMerchantTransactionId(),
                    'exception' => get_class($emailException),
                    'file' => $emailException->getFile(),
                    'line' => $emailException->getLine(),
                    'trace' => $emailException->getTraceAsString(),
                ),
                'warning'
            );
        }
    }

    http_response_code(200);
    echo 'OK';
} catch (Throwable $exception) {
    PaymentStorage::append('callback_errors.jsonl', array(
        'exception' => get_class($exception),
        'message' => $exception->getMessage(),
        'bodyLength' => strlen($body),
    ));

    // Log callback processing error
    if (Config::bool('ENABLE_LOGGING')) {
        Logger::logError(
            'Callback processing failed: ' . $exception->getMessage(),
            array(
                'exception' => get_class($exception),
                'body_length' => strlen($body),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString(),
            ),
            'critical'
        );
    }

    http_response_code(500);
    echo 'ERROR';
}
