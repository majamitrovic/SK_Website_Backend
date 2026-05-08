<?php

require_once __DIR__ . '/../bootstrap.php';

use App\AllSecureService;

api_cors();

$merchantTransactionId = trim((string) ($_GET['merchant_transaction_id'] ?? $_GET['tx'] ?? ''));

if ($merchantTransactionId === '') {
    api_json(422, array('ok' => false, 'message' => 'merchant_transaction_id is required'));
}

try {
    $service = new AllSecureService();
    api_json(200, array(
        'ok' => true,
        'merchantTransactionId' => $merchantTransactionId,
        'status' => $service->statusByMerchantTransactionId($merchantTransactionId),
    ));
} catch (Throwable $exception) {
    api_json(500, array(
        'ok' => false,
        'merchantTransactionId' => $merchantTransactionId,
        'message' => $exception->getMessage(),
    ));
}
