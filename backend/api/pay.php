<?php

require_once __DIR__ . '/../bootstrap.php';

use App\AllSecureService;
use App\PaymentStorage;
use App\ValidationException;

api_cors();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    api_json(405, array('ok' => false, 'message' => 'Method not allowed'));
}

try {
    $service = new AllSecureService();
    $payment = $service->createDebit(api_input());

    PaymentStorage::append('transactions.jsonl', array(
        'merchantTransactionId' => $payment['merchantTransactionId'],
        'amount' => $payment['amount'],
        'currency' => $payment['currency'],
        'recurring' => $payment['recurring'],
        'success' => $payment['result']['success'],
        'returnType' => $payment['result']['returnType'],
        'uuid' => $payment['result']['uuid'],
        'purchaseId' => $payment['result']['purchaseId'],
        'scheduleId' => $payment['result']['scheduleId'],
        'scheduleStatus' => $payment['result']['scheduleStatus'],
        'paymentMethod' => $payment['result']['paymentMethod'],
        'errorCount' => count($payment['result']['errors']),
    ));

    api_json(200, array('ok' => true) + $payment);
} catch (ValidationException $exception) {
    api_json(422, array(
        'ok' => false,
        'message' => 'Please check the payment form.',
        'errors' => $exception->errors(),
    ));
} catch (Throwable $exception) {
    PaymentStorage::append('transactions.jsonl', array(
        'success' => false,
        'exception' => get_class($exception),
        'message' => $exception->getMessage(),
    ));

    api_json(500, array(
        'ok' => false,
        'message' => $exception->getMessage(),
    ));
}
