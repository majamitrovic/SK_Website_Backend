<?php

require_once __DIR__ . '/../bootstrap.php';

use App\AllSecureService;
use App\PaymentStorage;

api_cors();

$merchantTransactionId = trim((string) ($_GET['merchant_transaction_id'] ?? $_GET['tx'] ?? ''));

if ($merchantTransactionId === '') {
    api_json(422, array('ok' => false, 'message' => 'merchant_transaction_id is required'));
}

try {
    $service = new AllSecureService();

    $transaction = PaymentStorage::getTransaction($merchantTransactionId);
    if (!$transaction) {
        api_json(404, array('ok' => false, 'message' => 'Transaction not found'));
    }

    $recordedAt = $transaction['recordedAt'] ? (new \DateTime($transaction['recordedAt']))
    ->setTimezone(new \DateTimeZone('Europe/Belgrade'))
    ->format('Y-m-d H:i:s') : null;

    $scheduleId = $transaction['scheduleId'] ?? null;

    $isScheduledTransaction = !empty($scheduleId);

    api_json(200, array(
        'ok' => true,
        'merchantTransactionId' => $merchantTransactionId,
        'status' => $service->statusByMerchantTransactionId($merchantTransactionId),
        'isScheduledTransaction' => $isScheduledTransaction,
        'transactionDate' => $recordedAt
    ));
} catch (Throwable $exception) {
    api_json(500, array(
        'ok' => false,
        'merchantTransactionId' => $merchantTransactionId,
        'message' => $exception->getMessage(),
    ));
}
