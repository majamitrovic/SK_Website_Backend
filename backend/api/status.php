<?php

require_once __DIR__ . '/../bootstrap.php';

use App\AllSecureService;
use App\Config;
use App\Logger;

api_cors();

$merchantTransactionId = trim((string) ($_GET['merchant_transaction_id'] ?? $_GET['tx'] ?? ''));

if ($merchantTransactionId === '') {
    api_json(422, array('ok' => false, 'message' => 'merchant_transaction_id is required'));
}

try {
    $service = new AllSecureService();
    $status = $service->statusByMerchantTransactionId($merchantTransactionId);

    // Log status query
    if (Config::bool('ENABLE_LOGGING')) {
        Logger::logTransaction(array(
            'type' => 'status_query',
            'transaction_id' => $merchantTransactionId,
            'payment_status' => $status['result'] ?? null,
            'uuid' => $status['uuid'] ?? null,
            'success' => $status['success'] ?? false,
        ));
    }

    api_json(200, array(
        'ok' => true,
        'merchantTransactionId' => $merchantTransactionId,
        'status' => $status,
    ));
} catch (Throwable $exception) {
    // Log status query error
    if (Config::bool('ENABLE_LOGGING')) {
        Logger::logError(
            'Status query failed: ' . $exception->getMessage(),
            array(
                'transaction_id' => $merchantTransactionId,
                'exception' => get_class($exception),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ),
            'error'
        );
    }

    api_json(500, array(
        'ok' => false,
        'merchantTransactionId' => $merchantTransactionId,
        'message' => $exception->getMessage(),
    ));
}
