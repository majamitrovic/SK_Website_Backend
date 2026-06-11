<?php

require_once __DIR__ . '/../src/bootstrap.php';

use App\AllSecureService;
use App\Config;
use App\PaymentStorage;
use App\Logger;

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

    // Log successful callback
    if (Config::bool('ENABLE_LOGGING')) {
        Logger::logPaymentStatus(
            $callback->getMerchantTransactionId(),
            $callback->getResult(),
            array(
                'type' => 'callback',
                'uuid' => $callback->getUuid(),
                'purchase_id' => $callback->getPurchaseId(),
                'transaction_type' => $callback->getTransactionType(),
                'schedule_id' => $callback->getScheduleId(),
                'schedule_status' => $callback->getScheduleStatus(),
            )
        );
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
