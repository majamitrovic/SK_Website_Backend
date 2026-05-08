<?php

require_once __DIR__ . '/../src/bootstrap.php';

use App\AllSecureService;
use App\Config;
use App\PaymentStorage;

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

        http_response_code(401);
        echo 'INVALID_SIGNATURE';
        exit;
    }

    $callback = $service->readCallback($body);
    PaymentStorage::append('callbacks.jsonl', AllSecureService::callbackResultToArray($callback));

    http_response_code(200);
    echo 'OK';
} catch (Throwable $exception) {
    PaymentStorage::append('callback_errors.jsonl', array(
        'exception' => get_class($exception),
        'message' => $exception->getMessage(),
        'bodyLength' => strlen($body),
    ));

    http_response_code(500);
    echo 'ERROR';
}
