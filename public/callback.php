<?php

require_once __DIR__ . '/../src/bootstrap.php';

use App\AllSecureService;
use App\Config;
use App\PaymentStorage;
use App\Logger;
use App\EmailService;

$callbackStartTime = microtime(true);
$requestId = bin2hex(random_bytes(8));

if (Config::bool('ENABLE_LOGGING')) {
    Logger::logTransaction([
        'type' => 'callback_received',
        'request_id' => $requestId,
        'method' => $_SERVER['REQUEST_METHOD'] ?? null,
        'uri' => $_SERVER['REQUEST_URI'] ?? null,
        'timestamp' => date('Y-m-d H:i:s'),
    ]);
}

header('Content-Type: text/plain; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    if (Config::bool('ENABLE_LOGGING')) {
        Logger::logError(
            'Callback rejected: not a POST request',
            [
                'request_id' => $requestId,
                'method' => $_SERVER['REQUEST_METHOD'] ?? null
            ],
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
    Logger::logTransaction([
        'type' => 'callback_body_received',
        'request_id' => $requestId,
        'body_length' => $bodyLength,
        'timestamp' => date('Y-m-d H:i:s'),
    ]);
}

if ($bodyLength === 0) {
    if (Config::bool('ENABLE_LOGGING')) {
        Logger::logError(
            'Callback rejected: empty request body',
            ['request_id' => $requestId],
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
        ?? $_SERVER['HTTP_AUTHORIZATION']
        ?? $_SERVER['HTTP_X_AUTHORIZATION']
        ?? null;

    $requestUri = $_SERVER['REQUEST_URI'] ?? '/callback.php';

    if (Config::bool('ENABLE_LOGGING')) {
        Logger::logTransaction([
            'type' => 'callback_headers_check',
            'request_id' => $requestId,
            'has_date_header' => !empty($dateHeader),
            'has_signature' => !empty($signature),
            'request_uri' => $requestUri,
            'timestamp' => date('Y-m-d H:i:s'),
        ]);
    }

    if (Config::bool('ALLSECURE_VALIDATE_CALLBACKS', true)) {
        if (!$dateHeader || !$signature) {

            PaymentStorage::append('callback_rejections.jsonl', [
                'reason' => 'missing_headers',
                'has_date' => !empty($dateHeader),
                'has_signature' => !empty($signature),
                'requestUri' => $requestUri,
                'bodyLength' => $bodyLength,
                'timestamp' => date('Y-m-d H:i:s'),
            ]);

            http_response_code(400);
            echo 'MISSING_HEADERS';
            exit;
        }

        if (!$service->validateCallback($body, $requestUri, $dateHeader, $signature)) {

            PaymentStorage::append('callback_rejections.jsonl', [
                'reason' => 'invalid_signature',
                'requestUri' => $requestUri,
                'bodyLength' => $bodyLength,
                'timestamp' => date('Y-m-d H:i:s'),
            ]);

            http_response_code(401);
            echo 'INVALID_SIGNATURE';
            exit;
        }
    }

    $callback = $service->readCallback($body);
    $callbackData = AllSecureService::callbackResultToArray($callback);

    PaymentStorage::append('callbacks.jsonl', $callbackData);

    $callbackResult = $callback->getResult();
    $merchantTransactionId = $callback->getMerchantTransactionId();

    $scheduleId = null;
    $scheduleStatus = null;

    if (method_exists($callback, 'getScheduleId')) {
        try {
            $scheduleId = $callback->getScheduleId();
        } catch (Throwable $e) {}
    }

    if (method_exists($callback, 'getScheduleStatus')) {
        try {
            $scheduleStatus = $callback->getScheduleStatus();
        } catch (Throwable $e) {}
    }

    if (Config::bool('ENABLE_LOGGING')) {
        Logger::logPaymentStatus(
            $merchantTransactionId,
            $callbackResult,
            [
                'type' => 'callback',
                'uuid' => $callback->getUuid(),
                'purchase_id' => $callback->getPurchaseId(),
                'transaction_type' => $callback->getTransactionType(),
                'payment_method' => $callback->getPaymentMethod(),
                'card_last_four' => $callbackData['cardLastFourDigits'] ?? null,
                'auth_code' => $callbackData['authCode'] ?? null,
                'schedule_id' => $scheduleId,
                'schedule_status' => $scheduleStatus,
                'timestamp' => date('Y-m-d H:i:s'),
                'request_id' => $requestId,
            ]
        );
    }

    $customerEmail = getCustomerEmailFromTransaction($merchantTransactionId);

    $paymentData = [
        'id' => $merchantTransactionId,
        'email' => $customerEmail,
        'amount' => $callback->getAmount(),
        'currency' => $callback->getCurrency(),
        'transaction_type' => $callback->getTransactionType(),
    ];

    try {

        if ($callbackResult === 'confirmed') {

            if ($customerEmail) {
                EmailService::sendPaymentSuccess($paymentData, $callbackData);
            }

            if (!empty($scheduleId) && $customerEmail) {
                EmailService::sendScheduleConfirmation($paymentData, $callbackData);
            }

        } elseif (in_array($callbackResult, ['failed', 'error'], true)) {

            if ($customerEmail) {
                EmailService::sendPaymentFailure($paymentData, $callbackData);
            } else {
                if (Config::bool('ENABLE_LOGGING')) {
                    Logger::logError(
                        'Cannot send payment failure email - customer email not found',
                        [
                            'transaction_id' => $merchantTransactionId,
                            'result' => $callbackResult,
                            'request_id' => $requestId,
                        ],
                        'warning'
                    );
                }
            }
        }

        EmailService::sendCallbackNotification($callbackData);

    } catch (Throwable $emailException) {
        if (Config::bool('ENABLE_LOGGING')) {
            Logger::logError(
                'Email sending failed: ' . $emailException->getMessage(),
                [
                    'transaction_id' => $merchantTransactionId,
                    'request_id' => $requestId,
                ],
                'warning'
            );
        }
    }

    http_response_code(200);
    echo 'OK';

} catch (Throwable $exception) {

    PaymentStorage::append('callback_errors.jsonl', [
        'exception' => get_class($exception),
        'message' => $exception->getMessage(),
        'file' => $exception->getFile(),
        'line' => $exception->getLine(),
        'timestamp' => date('Y-m-d H:i:s'),
        'request_id' => $requestId,
    ]);

    if (Config::bool('ENABLE_LOGGING')) {
        Logger::logError(
            'Callback processing failed: ' . $exception->getMessage(),
            [
                'exception' => get_class($exception),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'request_id' => $requestId,
            ],
            'critical'
        );
    }

    http_response_code(500);
    echo 'ERROR';
}

/**
 * Helper function to get customer email from transaction records
 */
function getCustomerEmailFromTransaction($merchantTransactionId)
{
    $transactionFile = dirname(__DIR__) . '/storage/transactions.jsonl';

    if (!file_exists($transactionFile)) {
        return null;
    }

    $file = fopen($transactionFile, 'r');
    if (!$file) {
        return null;
    }

    while (($line = fgets($file)) !== false) {
        $record = json_decode(trim($line), true);

        if (($record['merchantTransactionId'] ?? null) === $merchantTransactionId) {
            fclose($file);
            return $record['customer_email'] ?? null;
        }
    }

    fclose($file);
    return null;
}