<?php

require_once __DIR__ . '/../bootstrap.php';

use App\AllSecureService;
use App\Config;
use App\PaymentStorage;
use App\Logger;

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

$parts = explode('|', $decoded);
if (count($parts) < 4) {
    http_response_code(400);
    echo 'Invalid token format';
    exit;
}

list($merchantTransactionId, $registrationUuid, $expires, $signature) = $parts;

if ((int)$expires < time()) {
    http_response_code(400);
    echo 'Token expired';
    exit;
}

$secret = Config::get('ALLSECURE_CONNECTOR_SHARED_SECRET');
$payload = $merchantTransactionId . '|' . $registrationUuid . '|' . $expires;
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

    $result = $service->deregisterRegistration($registrationUuid);

    PaymentStorage::append('deregister_results.jsonl', [
        'merchantTransactionId' => $merchantTransactionId,
        'registrationUuid' => $registrationUuid,
        'result' => $result,
        'timestamp' => date('Y-m-d H:i:s'),
    ]);

    PaymentStorage::markTokenUsed($token, ['merchantTransactionId' => $merchantTransactionId, 'registrationUuid' => $registrationUuid]);

    echo '<!doctype html><html><head><meta charset="utf-8"><title>Card deregistered</title></head><body><h1>Card deregistered</h1><p>Your card has been deregistered successfully.</p></body></html>';
    exit;

} catch (Throwable $e) {
    if (Config::bool('ENABLE_LOGGING')) {
        Logger::logError('Deregistration failed: ' . $e->getMessage(), ['exception' => get_class($e)], 'error');
    }
    http_response_code(500);
    echo 'ERROR';
    exit;
}
