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

    // Build payment data for templates and idempotency checks
    $customerEmail = getCustomerEmailFromTransaction($merchantTransactionId);

    $paymentData = [
        'merchantTransactionId' => $merchantTransactionId,
        'email' => $customerEmail,
        'amount' => $callback->getAmount(),
        'currency' => $callback->getCurrency(),
        'transaction_type' => $callback->getTransactionType(),
        'card' => $callbackData['card'] ?? [],
        'auth_code' => $callbackData['uuid'] ?? $callbackData['authCode'] ?? null,
        'result' => $callbackResult,
        'transaction_time' => $callbackData['scheduledAt'] ?? date('c'),
        'scheduleId' => $scheduleId,
    ];

    // If a schedule exists, create a short-lived HMAC token for cancellation links
    if (!empty($scheduleId)) {
        $expires = time() + (60 * 60 * 24 * 7); // 7 days
        $tokenPayload = $merchantTransactionId . '|' . $scheduleId . '|' . $expires;
        $secret = Config::get('ALLSECURE_CONNECTOR_SHARED_SECRET');
        $signature = hash_hmac('sha256', $tokenPayload, $secret);
        $token = rtrim(strtr(base64_encode($tokenPayload . '|' . $signature), '+/', '-_'), '=');
        $paymentData['cancelToken'] = $token;
        $paymentData['cancelLink'] = Config::baseUrl() . '/cancel_subscription.php?token=' . urlencode($token);
    }

    try {
        // Decide single customer email type per callback
        $emailType = null;
        if ($callbackResult === 'confirmed') {
            $emailType = $scheduleId ? 'schedule_confirmation' : 'payment_success';
        } elseif (in_array($callbackResult, ['failed', 'error'], true)) {
            $emailType = $scheduleId ? 'schedule_failure' : 'payment_failure';
        }

        if ($customerEmail && $emailType) {
            if (!PaymentStorage::emailWasSent($merchantTransactionId, $emailType)) {
                $sent = false;
                try {
                    if ($emailType === 'payment_success') {
                        $sent = EmailService::sendPaymentSuccess($paymentData, $callbackData);
                    } elseif ($emailType === 'payment_failure') {
                        $sent = EmailService::sendPaymentFailure($paymentData, $callbackData);
                    } elseif ($emailType === 'schedule_confirmation') {
                        $sent = EmailService::sendScheduleConfirmation($paymentData, $callbackData);
                    } elseif ($emailType === 'schedule_failure') {
                        // reuse payment failure template for scheduled failures
                        $sent = EmailService::sendPaymentFailure($paymentData, $callbackData);
                    }
                } catch (Throwable $e) {
                    $sent = false;
                    if (Config::bool('ENABLE_LOGGING')) {
                        Logger::logError(
                            'Email sending exception: ' . $e->getMessage(),
                            [
                                'transaction_id' => $merchantTransactionId,
                                'request_id' => $requestId,
                            ],
                            'error'
                        );
                    }
                }

                if ($sent) {
                    PaymentStorage::markEmailSent($merchantTransactionId, $emailType, ['request_id' => $requestId]);
                }
            }
        } elseif (!$customerEmail && $emailType) {
            if (Config::bool('ENABLE_LOGGING')) {
                Logger::logError(
                    'Cannot send customer email - customer email not found',
                    [
                        'transaction_id' => $merchantTransactionId,
                        'result' => $callbackResult,
                        'request_id' => $requestId,
                    ],
                    'warning'
                );
            }
        }

        // Admin/internal notification remains unchanged
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