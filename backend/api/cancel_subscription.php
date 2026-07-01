<?php

require_once __DIR__ . '/../bootstrap.php';

use App\AllSecureService;
use App\Config;
use App\PaymentStorage;
use App\Logger;
use App\EmailService;

header('Content-Type: text/html; charset=utf-8');

$token = $_GET['token'] ?? '';
if (!$token) {
    http_response_code(400);
    echo 'Invalid request';
    exit;
}

// restore base64 padding
$b64 = str_replace(['-','_'], ['+','/'], $token);
switch (strlen($b64) % 4) {
    case 2: $b64 .= '=='; break;
    case 3: $b64 .= '='; break;
}

$decoded = base64_decode($b64, true);
if ($decoded === false) {
    http_response_code(400);
    echo 'Invalid token';
    exit;
}

$parts = explode('|', $decoded, 5);
if (count($parts) < 5) {
    http_response_code(400);
    echo 'Invalid token format';
    exit;
}

list($tokenType, $merchantTransactionId, $scheduleId, $expires, $signature) = $parts;

if ($tokenType !== 'cancel') {
    http_response_code(400);
    echo 'Invalid token type';
    exit;
}

if ((int)$expires < time()) {
    http_response_code(400);
    echo 'Token expired';
    exit;
}

$secret = Config::get('ALLSECURE_CONNECTOR_SHARED_SECRET');
$payload = $tokenType . '|' . $merchantTransactionId . '|' . $scheduleId . '|' . $expires;
$expected = hash_hmac('sha256', $payload, $secret);
if (!hash_equals($expected, $signature)) {
    http_response_code(400);
    echo 'Invalid token signature';
    exit;
}

if (PaymentStorage::tokenWasUsed($token)) {
    http_response_code(400);
    echo 'Token already used';
    exit;
}

try {
    $service = new AllSecureService();

    // Verify schedule exists
    $schedule = $service->showSchedule($scheduleId);
    if (empty($schedule)) {
        http_response_code(404);
        echo 'Schedule not found';
        exit;
    }

    if (Config::bool('ENABLE_LOGGING')) {
Logger::logTransaction([
'type' => 'schedule_parsed',
'request_id' => $requestId,
'raw_schedule' => $schedule,
'timestamp' => date('Y-m-d H:i:s'),
]);
}
    // Attempt cancellation
    $cancelResult = $service->cancelSchedule($scheduleId);
    if (!$cancelResult['success']) {
        $alreadyCancelled = false;
        $oldStatus = strtoupper((string) ($cancelResult['oldStatus'] ?? ''));
        $newStatus = strtoupper((string) ($cancelResult['newStatus'] ?? ''));
        $errorMessage = (string) ($cancelResult['errorMessage'] ?? '');

        if (in_array($oldStatus, array('CANCELLED', 'CANCELED', 'INACTIVE', 'DELETED'), true)
            || in_array($newStatus, array('CANCELLED', 'CANCELED', 'INACTIVE', 'DELETED'), true)
            || preg_match('/already.*cancel|already.*deregister|already.*inactive/i', $errorMessage)
        ) {
            $alreadyCancelled = true;
        }

        if ($alreadyCancelled) {
            $cancelResult['success'] = true;
            $cancelResult['errorMessage'] = null;
            $cancelResult['errorCode'] = null;
        }
    }

    // Persist cancellation result
    PaymentStorage::append('cancel_results.jsonl', [
        'merchantTransactionId' => $merchantTransactionId,
        'scheduleId' => $scheduleId,
        'cancelResult' => $cancelResult,
        'timestamp' => date('Y-m-d H:i:s'),
    ]);

    // Mark token used to prevent reuse
    PaymentStorage::markTokenUsed($token, ['merchantTransactionId' => $merchantTransactionId, 'scheduleId' => $scheduleId]);

    // Send confirmation email to customer (best-effort)
    // Send confirmation email to customer (best-effort)
    $customerEmail = $schedule['customer']['identification'] ?? null;
    if (empty($customerEmail)) {
        $customerEmail = PaymentStorage::getCustomerEmail($merchantTransactionId);
    }

    $payment = [
        'merchantTransactionId' => $merchantTransactionId,
        'email' => $customerEmail,
    ];
    try {
        EmailService::sendCancellationConfirmation($payment, $cancelResult ?: []);
    } catch (Throwable $e) {
        if (Config::bool('ENABLE_LOGGING')) {
            Logger::logError('Cancellation email exception: ' . $e->getMessage(), ['merchantTransactionId' => $merchantTransactionId], 'warning');
        }
    }

    // Show simple success page
    echo '<!doctype html><html><head><meta charset="utf-8"><title>Subscription cancelled</title></head><body><h1>Subscription cancelled</h1><p>Your subscription has been cancelled successfully.</p></body></html>';
    exit;

} catch (Throwable $e) {
    if (Config::bool('ENABLE_LOGGING')) {
        Logger::logError('Subscription cancellation failed: ' . $e->getMessage(), ['exception' => get_class($e)], 'error');
    }
    http_response_code(500);
    echo 'ERROR';
    exit;
}
