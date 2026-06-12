<?php

namespace App;

use Exchange\Client\Client;
use Exchange\Client\Data\Customer;
use Exchange\Client\Schedule\ContinueSchedule;
use Exchange\Client\Schedule\ScheduleWithTransaction;
use Exchange\Client\StatusApi\StatusRequestData;
use Exchange\Client\Transaction\Debit;
use Exchange\Client\Transaction\Result;

final class AllSecureService
{
    private $client;

    public function __construct()
    {
        Config::required(array(
            'ALLSECURE_USERNAME',
            'ALLSECURE_PASSWORD',
            'ALLSECURE_CONNECTOR_API_KEY',
            'ALLSECURE_CONNECTOR_SHARED_SECRET',
        ));

        $gatewayUrl = rtrim((string) Config::get('ALLSECURE_GATEWAY_URL', 'https://asxgw.com/'), '/') . '/';
        Client::setApiUrl($gatewayUrl);

        $this->client = new Client(
            Config::get('ALLSECURE_USERNAME'),
            Config::get('ALLSECURE_PASSWORD'),
            Config::get('ALLSECURE_CONNECTOR_API_KEY'),
            Config::get('ALLSECURE_CONNECTOR_SHARED_SECRET'),
            Config::get('ALLSECURE_LANGUAGE', 'en')
        );

        $curlOptions = array();
        $caInfo = trim((string) Config::get('ALLSECURE_CURL_CAINFO', ''));
        if ($caInfo !== '') {
            $curlOptions[CURLOPT_CAINFO] = $this->path($caInfo);
        }

        if (Config::bool('ALLSECURE_DISABLE_SSL_VERIFY', false)) {
            $curlOptions[CURLOPT_SSL_VERIFYHOST] = 0;
            $curlOptions[CURLOPT_SSL_VERIFYPEER] = false;
        }

        if ($curlOptions) {
            $this->client->setCustomCurlOptions($curlOptions);
        }
    }

    public function createDebit(array $input)
    {
        $payment = $this->validatePaymentInput($input);
        $merchantTransactionId = $payment['merchant_transaction_id'] ?: $this->makeTransactionId();

        $customer = new Customer();
        $customer
            ->setIdentification($payment['email'])
            ->setFirstName($payment['first_name'])
            ->setLastName($payment['last_name'])
            ->setEmail($payment['email'])
            ->setBillingAddress1($payment['billing_address'])
            ->setBillingCity($payment['billing_city'])
            ->setBillingPostcode($payment['billing_postcode'])
            ->setBillingState($payment['billing_state'])
            ->setBillingCountry($payment['billing_country'])
            ->setIpAddress($this->clientIp());

        $debit = new Debit();
        $debit
            ->setMerchantTransactionId($merchantTransactionId)
            ->setTransactionToken($payment['transaction_token'])
            ->setAmount($payment['amount'])
            ->setCurrency($payment['currency'])
            ->setDescription($payment['description'])
            ->setCustomer($customer)
            ->setSuccessUrl($this->url('/success.php', array('merchant_transaction_id' => $merchantTransactionId)))
            ->setCancelUrl($this->url('/cancel.php', array('merchant_transaction_id' => $merchantTransactionId)))
            ->setErrorUrl($this->url('/error.php', array('merchant_transaction_id' => $merchantTransactionId)))
            ->setCallbackUrl($this->url('/callback.php'))
            ->setTransactionIndicator(Debit::TRANSACTION_INDICATOR_SINGLE);

        if ($payment['recurring_enabled']) {
            $schedule = new ScheduleWithTransaction();
            $schedule
                ->setAmount($payment['recurring_amount'])
                ->setCurrency($payment['currency'])
                ->setPeriodLength($payment['recurring_period_length'])
                ->setPeriodUnit($payment['recurring_period_unit'])
                ->setStartDateTime($payment['recurring_start_datetime'])
                ->setMerchantMetaData(json_encode(array(
                    'merchantTransactionId' => $merchantTransactionId,
                    'description' => $payment['description'],
                ), JSON_UNESCAPED_SLASHES))
                ->setCallbackUrl($this->url('/callback.php'));

            $debit
                ->setWithRegister(true)
                ->setSchedule($schedule)
                ->setTransactionIndicator(Debit::TRANSACTION_INDICATOR_INITIAL);
        }

        $threeDSecure = strtoupper(trim((string) Config::get('ALLSECURE_3DSECURE', 'MANDATORY')));
        if ($threeDSecure !== '') {
            $debit->addExtraData('3dsecure', $threeDSecure);
        }

        $result = $this->client->debit($debit);

        return array(
            'merchantTransactionId' => $merchantTransactionId,
            'amount' => $payment['amount'],
            'currency' => $payment['currency'],
            'recurring' => array(
                'enabled' => $payment['recurring_enabled'],
                'amount' => $payment['recurring_amount'],
                'periodLength' => $payment['recurring_period_length'],
                'periodUnit' => $payment['recurring_period_unit'],
                'startDateTime' => $payment['recurring_start_datetime'] ? $payment['recurring_start_datetime']->format(\DateTime::ATOM) : null,
            ),
            'result' => self::transactionResultToArray($result),
        );
    }

    public function validateCallbackWithGlobals()
    {
        return $this->client->validateCallbackWithGlobals();
    }

    public function validateCallback($body, $requestUri, $dateHeader, $authorizationHeader)
    {
        return $this->client->validateCallback($body, $requestUri, $dateHeader, $authorizationHeader);
    }

    public function readCallback($body)
    {
        return $this->client->readCallback($body);
    }

    public function statusByMerchantTransactionId($merchantTransactionId)
    {
        $request = new StatusRequestData();
        $request->setMerchantTransactionId($merchantTransactionId);

        return self::statusResultToArray($this->client->sendStatusRequest($request));
    }

    public function showSchedule($scheduleId)
    {
        return self::scheduleResultToArray($this->client->showSchedule($scheduleId));
    }

    public function pauseSchedule($scheduleId)
    {
        return self::scheduleResultToArray($this->client->pauseSchedule($scheduleId));
    }

    public function cancelSchedule($scheduleId)
    {
        return self::scheduleResultToArray($this->client->cancelSchedule($scheduleId));
    }

    public function continueSchedule($scheduleId, $continueDateTime)
    {
        $schedule = new ContinueSchedule();
        $schedule
            ->setScheduleId($scheduleId)
            ->setContinueDateTime($continueDateTime ?: new \DateTime('+1 hour'));

        return self::scheduleResultToArray($this->client->continueSchedule($schedule));
    }

    public static function transactionResultToArray(Result $result)
    {
        return array(
            'success' => $result->isSuccess(),
            'returnType' => $result->getReturnType(),
            'uuid' => $result->getUuid(),
            'purchaseId' => $result->getPurchaseId(),
            'redirectType' => $result->getRedirectType(),
            'redirectUrl' => $result->getRedirectUrl(),
            'htmlContent' => $result->getHtmlContent(),
            'paymentMethod' => $result->getPaymentMethod(),
            'scheduleId' => $result->getScheduleId(),
            'scheduleStatus' => $result->getScheduleStatus(),
            'scheduledAt' => $result->getScheduledAt(),
            'errors' => self::errorsToArray($result->getErrors()),
        );
    }

    public static function callbackResultToArray($callback)
    {
        $scheduleId = null;
        $scheduleStatus = null;
        
        // Only call getScheduleId/getScheduleStatus if they exist and callback is for recurring payment
        try {
            if (method_exists($callback, 'getScheduleId')) {
                $scheduleId = $callback->getScheduleId();
            }
        } catch (Throwable $e) {
            // Schedule ID not available (non-recurring payment)
        }
        
        try {
            if (method_exists($callback, 'getScheduleStatus')) {
                $scheduleStatus = $callback->getScheduleStatus();
            }
        } catch (Throwable $e) {
            // Schedule status not available (non-recurring payment)
        }
        
        return array(
            'result' => $callback->getResult(),
            'uuid' => $callback->getUuid(),
            'merchantTransactionId' => $callback->getMerchantTransactionId(),
            'purchaseId' => $callback->getPurchaseId(),
            'transactionType' => $callback->getTransactionType(),
            'paymentMethod' => $callback->getPaymentMethod(),
            'amount' => $callback->getAmount(),
            'currency' => $callback->getCurrency(),
            'scheduleId' => $scheduleId,
            'scheduleStatus' => $scheduleStatus,
            'errorMessage' => $callback->getErrorMessage(),
            'errorCode' => $callback->getErrorCode(),
            'adapterMessage' => $callback->getAdapterMessage(),
            'adapterCode' => $callback->getAdapterCode(),
            'errors' => self::errorsToArray($callback->getErrors()),
        );
    }

    public static function statusResultToArray($status)
    {
        return array(
            'success' => $status->isSuccess(),
            'transactionStatus' => $status->getTransactionStatus(),
            'uuid' => $status->getUuid(),
            'merchantTransactionId' => $status->getMerchantTransactionId(),
            'purchaseId' => $status->getPurchaseId(),
            'transactionType' => $status->getTransactionType(),
            'paymentMethod' => $status->getPaymentMethod(),
            'amount' => $status->getAmount(),
            'currency' => $status->getCurrency(),
            'incomingSettlementState' => $status->getIncomingSettlementState(),
            'schedules' => self::scheduleResultDataListToArray($status->getSchedules()),
            'errorMessage' => $status->getErrorMessage(),
            'errorCode' => $status->getErrorCode(),
            'errors' => self::errorsToArray($status->getErrors()),
        );
    }

    public static function scheduleResultToArray($schedule)
    {
        return array(
            'success' => $schedule->isSuccess(),
            'scheduleId' => $schedule->getScheduleId(),
            'registrationUuid' => $schedule->getRegistrationUuid(),
            'oldStatus' => $schedule->getOldStatus(),
            'newStatus' => $schedule->getNewStatus(),
            'scheduledAt' => $schedule->getScheduledAt(),
            'errorMessage' => $schedule->getErrorMessage(),
            'errorCode' => $schedule->getErrorCode(),
        );
    }

    private function validatePaymentInput(array $input)
    {
        $errors = array();
        $amount = trim((string) ($input['amount'] ?? ''));
        $currency = strtoupper(trim((string) ($input['currency'] ?? '')));
        $transactionToken = trim((string) ($input['transaction_token'] ?? ''));
        $email = trim((string) ($input['email'] ?? ''));
        $billingCountry = strtoupper(trim((string) ($input['billing_country'] ?? '')));
        $merchantTransactionId = trim((string) ($input['merchant_transaction_id'] ?? ''));
        $recurringEnabled = !empty($input['recurring_enabled']);
        $recurringAmount = trim((string) ($input['recurring_amount'] ?? $amount));
        $recurringPeriodLength = trim((string) ($input['recurring_period_length'] ?? '1'));
        $recurringPeriodUnit = strtoupper(trim((string) ($input['recurring_period_unit'] ?? ScheduleWithTransaction::PERIOD_UNIT_MONTH)));
        $recurringStartDateTimeRaw = trim((string) ($input['recurring_start_datetime'] ?? ''));
        $recurringStartDateTime = null;

        if (!preg_match('/^\d+(?:\.\d{1,2})?$/', $amount) || (float) $amount <= 0) {
            $errors['amount'] = 'Enter a valid amount with up to two decimals.';
        }

        if (!preg_match('/^[A-Z]{3}$/', $currency)) {
            $errors['currency'] = 'Currency must be a 3-letter ISO code.';
        }

        if ($transactionToken === '') {
            $errors['transaction_token'] = 'Payment token is missing.';
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Enter a valid email address.';
        }

        if (!preg_match('/^[A-Z]{2}$/', $billingCountry)) {
            $errors['billing_country'] = 'Billing country must be a 2-letter ISO code.';
        }

        if ($merchantTransactionId !== '' && !preg_match('/^[A-Za-z0-9._:-]{1,80}$/', $merchantTransactionId)) {
            $errors['merchant_transaction_id'] = 'Transaction ID may only contain letters, numbers, dot, colon, dash, and underscore.';
        }

        if ($recurringEnabled) {
            if (!preg_match('/^\d+(?:\.\d{1,2})?$/', $recurringAmount) || (float) $recurringAmount <= 0) {
                $errors['recurring_amount'] = 'Enter a valid recurring amount with up to two decimals.';
            }

            if (!ctype_digit($recurringPeriodLength) || (int) $recurringPeriodLength < 1) {
                $errors['recurring_period_length'] = 'Recurring interval must be at least 1.';
            }

            if (!in_array($recurringPeriodUnit, ScheduleWithTransaction::getValidPeriodUnits(), true)) {
                $errors['recurring_period_unit'] = 'Recurring interval unit is invalid.';
            }

            try {
                $recurringStartDateTime = $recurringStartDateTimeRaw !== ''
                    ? new \DateTime($recurringStartDateTimeRaw)
                    : new \DateTime('+25 hours');

                $minimumStart = new \DateTime('+24 hours');
                if ($recurringStartDateTime <= $minimumStart) {
                    $errors['recurring_start_datetime'] = 'First recurring charge must be more than 24 hours after the initial payment.';
                }
            } catch (\Exception $exception) {
                $errors['recurring_start_datetime'] = 'Enter a valid first recurring charge date/time.';
            }
        }

        if ($errors) {
            throw new ValidationException($errors);
        }

        $cardHolder = trim((string) ($input['card_holder'] ?? ''));
        $firstName = trim((string) ($input['first_name'] ?? ''));
        $lastName = trim((string) ($input['last_name'] ?? ''));

        if (($firstName === '' || $lastName === '') && $cardHolder !== '') {
            $parts = preg_split('/\s+/', $cardHolder, 2);
            $firstName = $firstName ?: ($parts[0] ?? '');
            $lastName = $lastName ?: ($parts[1] ?? $parts[0] ?? '');
        }

        return array(
            'merchant_transaction_id' => $merchantTransactionId,
            'transaction_token' => $transactionToken,
            'amount' => number_format((float) $amount, 2, '.', ''),
            'currency' => $currency,
            'description' => trim((string) ($input['description'] ?? Config::get('ALLSECURE_DEFAULT_DESCRIPTION', 'Website payment'))),
            'recurring_enabled' => $recurringEnabled,
            'recurring_amount' => number_format((float) $recurringAmount, 2, '.', ''),
            'recurring_period_length' => (int) $recurringPeriodLength,
            'recurring_period_unit' => $recurringPeriodUnit,
            'recurring_start_datetime' => $recurringStartDateTime,
            'email' => $email,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'billing_address' => trim((string) ($input['billing_address'] ?? '')),
            'billing_city' => trim((string) ($input['billing_city'] ?? '')),
            'billing_postcode' => trim((string) ($input['billing_postcode'] ?? '')),
            'billing_state' => trim((string) ($input['billing_state'] ?? '')),
            'billing_country' => $billingCountry,
        );
    }

    private function makeTransactionId()
    {
        return 'web-' . gmdate('Ymd-His') . '-' . bin2hex(random_bytes(4));
    }

    private function url($path, array $query = array())
    {
        $url = Config::baseUrl() . '/' . ltrim($path, '/');

        if ($query) {
            $url .= '?' . http_build_query($query);
        }

        return $url;
    }

    private function path($path)
    {
        if (preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) || strpos($path, DIRECTORY_SEPARATOR) === 0) {
            return $path;
        }

        return Config::projectPath($path);
    }

    private function clientIp()
    {
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $parts = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return trim($parts[0]);
        }

        return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null;
    }

    private static function errorsToArray(array $errors)
    {
        $data = array();

        foreach ($errors as $error) {
            $data[] = array(
                'message' => $error->getMessage(),
                'code' => $error->getCode(),
                'adapterMessage' => $error->getAdapterMessage(),
                'adapterCode' => $error->getAdapterCode(),
            );
        }

        return $data;
    }

    private static function scheduleResultDataListToArray(array $schedules)
    {
        $data = array();

        foreach ($schedules as $schedule) {
            $scheduledAt = $schedule->getScheduledAt();
            $data[] = array(
                'scheduleId' => $schedule->getScheduleId(),
                'scheduleStatus' => $schedule->getScheduleStatus(),
                'scheduledAt' => $scheduledAt instanceof \DateTime ? $scheduledAt->format(\DateTime::ATOM) : $scheduledAt,
            );
        }

        return $data;
    }
}
