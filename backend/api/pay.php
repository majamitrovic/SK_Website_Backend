<?php

require_once __DIR__ . '/../bootstrap.php';

use App\AllSecureService;
use App\PaymentStorage;
use App\ValidationException;
use App\Config;
use App\Logger;

api_cors();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    api_json(405, array('ok' => false, 'message' => 'Method not allowed'));
}

try {
    $service = new AllSecureService();
    $input = api_input();
    $payment = $service->createDebit($input);

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

    // Log successful transaction
    if (Config::bool('ENABLE_LOGGING')) {
        Logger::logTransaction(array(
            'type' => 'debit_request',
            'transaction_id' => $payment['merchantTransactionId'],
            'amount' => $payment['amount'],
            'currency' => $payment['currency'],
            'customer_email' => $input['email'] ?? null,
            'success' => $payment['result']['success'],
            'uuid' => $payment['result']['uuid'],
            'purchase_id' => $payment['result']['purchaseId'],
            'schedule_id' => $payment['result']['scheduleId'],
            'payment_method' => $payment['result']['paymentMethod'],
            'error_count' => count($payment['result']['errors']),
            'errors' => !empty($payment['result']['errors']) ? implode(', ', array_map(function($e) { return $e['code'] . ': ' . $e['message']; }, $payment['result']['errors'])) : null,
        ));
    }

    api_json(200, array('ok' => true) + $payment);
} catch (ValidationException $exception) {
    // Log validation error
    if (Config::bool('ENABLE_LOGGING')) {
        Logger::logError(
            'Payment validation failed',
            array(
                'errors' => $exception->errors(),
                'file' => __FILE__,
                'line' => __LINE__,
            ),
            'warning'
        );
    }

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

    // Log payment processing error
    if (Config::bool('ENABLE_LOGGING')) {
        Logger::logError(
            'Payment processing failed: ' . $exception->getMessage(),
            array(
                'exception' => get_class($exception),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString(),
                'input_email' => $input['email'] ?? null,
            ),
            'critical'
        );
    }

    api_json(500, array(
        'ok' => false,
        'message' => $exception->getMessage(),
    ));
}
