<?php

require_once __DIR__ . '/../bootstrap.php';

use App\Config;

api_json(200, array(
    'ok' => true,
    'publicIntegrationKey' => Config::get('ALLSECURE_PUBLIC_INTEGRATION_KEY', ''),
    'paymentJsUrl' => Config::get('ALLSECURE_PAYMENT_JS_URL', 'https://asxgw.com/js/integrated/payment.1.3.min.js'),
    'defaultAmount' => Config::get('ALLSECURE_DEFAULT_AMOUNT', '9.99'),
    'defaultCurrency' => strtoupper((string) Config::get('ALLSECURE_DEFAULT_CURRENCY', 'EUR')),
    'defaultDescription' => Config::get('ALLSECURE_DEFAULT_DESCRIPTION', 'Website payment'),
    'recurringStart' => (new DateTime('+25 hours'))->format('Y-m-d\TH:i'),
));
