<?php

require_once __DIR__ . '/../src/bootstrap.php';

use App\Config;

function h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$publicKey = (string) Config::get('ALLSECURE_PUBLIC_INTEGRATION_KEY', '');
$missingPublicKey = $publicKey === '' || strpos($publicKey, 'replace_with_') === 0;
$paymentJsUrl = (string) Config::get('ALLSECURE_PAYMENT_JS_URL', 'https://asxgw.com/js/integrated/payment.1.3.min.js');
$defaultAmount = (string) Config::get('ALLSECURE_DEFAULT_AMOUNT', '9.99');
$defaultCurrency = strtoupper((string) Config::get('ALLSECURE_DEFAULT_CURRENCY', 'EUR'));
$defaultDescription = (string) Config::get('ALLSECURE_DEFAULT_DESCRIPTION', 'Website payment');
$defaultRecurringStart = (new DateTime('+25 hours'))->format('Y-m-d\TH:i');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Checkout</title>
    <link rel="stylesheet" href="/assets/checkout.css">
    <script data-main="payment-js" src="<?= h($paymentJsUrl) ?>"></script>
</head>
<body>
    <main class="checkout-shell">
        <section class="summary-panel" aria-labelledby="summary-title">
            <p class="eyebrow">Secure checkout</p>
            <h1 id="summary-title">Complete your payment V2</h1>
            <dl>
                <div>
                    <dt>Gateway</dt>
                    <dd>AllSecure</dd>
                </div>
                <div>
                    <dt>Flow</dt>
                    <dd>Payment.js tokenization</dd>
                </div>
            </dl>
        </section>

        <section class="payment-panel" aria-labelledby="payment-title">
            <h2 id="payment-title">Payment details</h2>

            <?php if ($missingPublicKey): ?>
                <div class="notice error">
                    Set <code>ALLSECURE_PUBLIC_INTEGRATION_KEY</code> in <code>.env</code> before taking payments.
                </div>
            <?php endif; ?>

            <form id="payment-form" action="/pay.php" method="post" novalidate>
                <input type="hidden" id="transaction_token" name="transaction_token">

                <div class="field-grid two">
                    <label>
                        <span>Amount</span>
                        <input name="amount" inputmode="decimal" value="<?= h($defaultAmount) ?>" pattern="[0-9]+(\.[0-9]{1,2})?" required>
                    </label>
                    <label>
                        <span>Currency</span>
                        <input name="currency" value="<?= h($defaultCurrency) ?>" maxlength="3" required>
                    </label>
                </div>

                <label>
                    <span>Description</span>
                    <input name="description" value="<?= h($defaultDescription) ?>" required>
                </label>

                <section class="recurring-box" aria-labelledby="recurring-title">
                    <label class="checkbox-line">
                        <input id="recurring_enabled" name="recurring_enabled" type="checkbox" value="1">
                        <span id="recurring-title">Create recurring payment schedule</span>
                    </label>

                    <div id="recurring-fields" class="recurring-fields" hidden>
                        <div class="field-grid three">
                            <label>
                                <span>Recurring amount</span>
                                <input id="recurring_amount" name="recurring_amount" inputmode="decimal" value="<?= h($defaultAmount) ?>" pattern="[0-9]+(\.[0-9]{1,2})?" disabled>
                            </label>
                            <label>
                                <span>Every</span>
                                <input id="recurring_period_length" name="recurring_period_length" type="number" min="1" step="1" value="1" disabled>
                            </label>
                            <label>
                                <span>Unit</span>
                                <select id="recurring_period_unit" name="recurring_period_unit" disabled>
                                    <option value="MONTH" selected>Month</option>
                                    <option value="WEEK">Week</option>
                                    <option value="DAY">Day</option>
                                    <option value="YEAR">Year</option>
                                </select>
                            </label>
                        </div>
                        <label>
                            <span>First recurring charge</span>
                            <input id="recurring_start_datetime" name="recurring_start_datetime" type="datetime-local" value="<?= h($defaultRecurringStart) ?>" disabled>
                        </label>
                    </div>
                </section>

                <div class="field-grid two">
                    <label>
                        <span>First name</span>
                        <input name="first_name" autocomplete="given-name">
                    </label>
                    <label>
                        <span>Last name</span>
                        <input name="last_name" autocomplete="family-name">
                    </label>
                </div>

                <label>
                    <span>Email</span>
                    <input name="email" type="email" autocomplete="email" required>
                </label>

                <label>
                    <span>Billing address</span>
                    <input name="billing_address" autocomplete="billing street-address">
                </label>

                <div class="field-grid three">
                    <label>
                        <span>City</span>
                        <input name="billing_city" autocomplete="billing address-level2">
                    </label>
                    <label>
                        <span>Postcode</span>
                        <input name="billing_postcode" autocomplete="billing postal-code">
                    </label>
                    <label>
                        <span>Country</span>
                        <input name="billing_country" value="AT" maxlength="2" autocomplete="billing country" required>
                    </label>
                </div>

                <label>
                    <span>State or region</span>
                    <input name="billing_state" autocomplete="billing address-level1">
                </label>

                <div class="field-grid two">
                    <label>
                        <span>Card holder</span>
                        <input id="card_holder" name="card_holder" autocomplete="cc-name" required>
                    </label>
                    <label>
                        <span>Expiry</span>
                        <span class="expiry-row">
                            <input id="exp_month" name="exp_month" placeholder="MM" inputmode="numeric" maxlength="2" autocomplete="cc-exp-month" required>
                            <input id="exp_year" name="exp_year" placeholder="YYYY" inputmode="numeric" maxlength="4" autocomplete="cc-exp-year" required>
                        </span>
                    </label>
                </div>

                <div class="field-grid two">
                    <label>
                        <span>Card number</span>
                        <span id="number_div" class="gateway-field"></span>
                    </label>
                    <label>
                        <span>CVV</span>
                        <span id="cvv_div" class="gateway-field short"></span>
                    </label>
                </div>

                <button id="pay-button" type="submit" disabled>Pay now</button>
                <output id="payment-status" class="status" role="status" aria-live="polite"></output>
            </form>
        </section>
    </main>

    <script>
        window.ALLSECURE_CONFIG = {
            publicIntegrationKey: <?= json_encode($publicKey) ?>,
            missingPublicKey: <?= $missingPublicKey ? 'true' : 'false' ?>
        };
    </script>
    <script src="/assets/checkout.js"></script>
</body>
</html>
