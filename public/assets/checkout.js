(function () {
    var config = window.ALLSECURE_CONFIG || {};
    var form = document.getElementById('payment-form');
    var button = document.getElementById('pay-button');
    var status = document.getElementById('payment-status');
    var tokenInput = document.getElementById('transaction_token');
    var recurringToggle = document.getElementById('recurring_enabled');
    var recurringFields = document.getElementById('recurring-fields');
    var payment = null;
    var paymentReady = false;
    var cardState = {
        validNumber: null,
        validCvv: null
    };

    if (!form || !button) {
        return;
    }

    function setStatus(message, type) {
        status.textContent = message || '';
        status.className = 'status' + (type ? ' ' + type : '');
    }

    function setBusy(isBusy) {
        button.disabled = isBusy || !paymentReady;
        button.textContent = isBusy ? 'Processing...' : 'Pay now';
    }

    function setCardState(data) {
        if (!data) {
            return;
        }

        if (typeof data.validNumber !== 'undefined' && data.validNumber !== null) {
            cardState.validNumber = !!data.validNumber;
        }

        if (typeof data.validCvv !== 'undefined' && data.validCvv !== null) {
            cardState.validCvv = !!data.validCvv;
        }
    }

    function normalizeErrors(errors) {
        if (!errors) {
            return 'Payment failed. Please try again.';
        }

        if (Array.isArray(errors)) {
            return errors.map(function (error) {
                return error.message || error.adapterMessage || error.key || JSON.stringify(error);
            }).join(' ');
        }

        if (typeof errors === 'object') {
            return Object.keys(errors).map(function (key) {
                return errors[key];
            }).join(' ');
        }

        return String(errors);
    }

    function updateRecurringFields() {
        if (!recurringToggle || !recurringFields) {
            return;
        }

        var enabled = recurringToggle.checked;
        recurringFields.hidden = !enabled;
        Array.prototype.forEach.call(recurringFields.querySelectorAll('input, select'), function (field) {
            field.disabled = !enabled;
            field.required = enabled;
        });
    }

    function initializePaymentJs() {
        if (config.missingPublicKey) {
            setStatus('Missing AllSecure public integration key.', 'error');
            return;
        }

        if (typeof PaymentJs === 'undefined') {
            setStatus('Payment.js did not load. Check ALLSECURE_PAYMENT_JS_URL and network access.', 'error');
            return;
        }

        function markPaymentReady(gatewayPayment) {
            payment = gatewayPayment || payment;

            try {
                payment.setRequireCardHolder(true);
                payment.setNumberPlaceholder('Card number');
                payment.setCvvPlaceholder('CVV');
                payment.numberOn('input', setCardState);
                payment.cvvOn('input', setCardState);
                payment.setNumberStyle({
                    border: '1px solid #8b95a1',
                    'border-radius': '6px',
                    padding: '10px 12px',
                    width: '100%',
                    height: '42px',
                    'font-size': '16px',
                    color: '#111827',
                    'box-sizing': 'border-box',
                    '::placeholder': {
                        color: '#6b7280'
                    }
                });
                payment.setCvvStyle({
                    border: '1px solid #8b95a1',
                    'border-radius': '6px',
                    padding: '10px 12px',
                    width: '100%',
                    height: '42px',
                    'font-size': '16px',
                    color: '#111827',
                    'box-sizing': 'border-box',
                    '::placeholder': {
                        color: '#6b7280'
                    }
                });
            } catch (error) {
                // The sandbox can expose the secure iframes before styling calls are accepted.
            }

            paymentReady = true;
            button.disabled = false;
            setStatus('');
        }

        payment = new PaymentJs();
        payment.init(config.publicIntegrationKey, 'number_div', 'cvv_div', markPaymentReady);

        window.setTimeout(function () {
            if (!paymentReady) {
                setStatus('Payment.js is still initializing. Check the payment.js URL and public integration key.', 'pending');
            }
        }, 4000);
    }

    function additionalCardData() {
        return {
            card_holder: document.getElementById('card_holder').value,
            first_name: form.elements.first_name.value,
            last_name: form.elements.last_name.value,
            month: document.getElementById('exp_month').value,
            year: document.getElementById('exp_year').value,
            email: form.elements.email.value,
            address1: form.elements.billing_address.value,
            zip: form.elements.billing_postcode.value,
            city: form.elements.billing_city.value,
            state: form.elements.billing_state.value,
            country: form.elements.billing_country.value
        };
    }

    function handleGatewayResponse(payload) {
        var result = payload.result || {};

        if (result.returnType === 'REDIRECT' && result.redirectUrl) {
            window.location.href = result.redirectUrl;
            return;
        }

        if (result.returnType === 'HTML' && result.htmlContent) {
            document.open();
            document.write(result.htmlContent);
            document.close();
            return;
        }

        if (result.returnType === 'FINISHED' && result.success) {
            window.location.href = '/success.php?merchant_transaction_id=' + encodeURIComponent(payload.merchantTransactionId);
            return;
        }

        if (result.returnType === 'PENDING') {
            setStatus('Payment is pending. Transaction ' + payload.merchantTransactionId + scheduleSuffix(result) + '.', 'pending');
            return;
        }

        setStatus(normalizeErrors(result.errors) || 'Payment failed. Please try another card.', 'error');
    }

    function scheduleSuffix(result) {
        if (!result.scheduleId) {
            return '';
        }

        return ' Schedule ' + result.scheduleId + (result.scheduleStatus ? ' is ' + result.scheduleStatus : '');
    }

    form.addEventListener('submit', function (event) {
        event.preventDefault();

        if (!paymentReady) {
            setStatus('Payment form is still loading.', 'error');
            return;
        }

        if (!form.reportValidity()) {
            return;
        }

        if (cardState.validNumber === false || cardState.validCvv === false) {
            setStatus('Please check the card number and CVV, then try again.', 'error');
            return;
        }

        setBusy(true);
        setStatus('Tokenizing card...', 'pending');

        var tokenizeFinished = false;
        var tokenizeTimeout = window.setTimeout(function () {
            if (tokenizeFinished) {
                return;
            }

            tokenizeFinished = true;
            setBusy(false);
            setStatus('Card tokenization timed out. Please re-enter the card number and CVV, then try again.', 'error');
        }, 20000);

        function finishTokenize() {
            tokenizeFinished = true;
            window.clearTimeout(tokenizeTimeout);
        }

        try {
            payment.tokenize(
                additionalCardData(),
                function (token) {
                    if (tokenizeFinished) {
                        return;
                    }

                    finishTokenize();

                    if (!token) {
                        setBusy(false);
                        setStatus('Payment.js did not return a transaction token. Please retry.', 'error');
                        return;
                    }

                    tokenInput.value = token;
                    setStatus('Sending payment...', 'pending');

                    fetch(form.action, {
                        method: 'POST',
                        body: new FormData(form),
                        headers: {
                            Accept: 'application/json'
                        }
                    })
                        .then(function (response) {
                            return response.json().then(function (payload) {
                                if (!response.ok || !payload.ok) {
                                    throw payload;
                                }
                                return payload;
                            });
                        })
                        .then(handleGatewayResponse)
                        .catch(function (error) {
                            setStatus(error.message || normalizeErrors(error.errors), 'error');
                        })
                        .finally(function () {
                            setBusy(false);
                        });
                },
                function (errors) {
                    if (tokenizeFinished) {
                        return;
                    }

                    finishTokenize();
                    setBusy(false);
                    setStatus(normalizeErrors(errors), 'error');
                }
            );
        } catch (error) {
            finishTokenize();
            setBusy(false);
            setStatus(error && error.message ? error.message : normalizeErrors(error), 'error');
        }
    });

    if (recurringToggle) {
        recurringToggle.addEventListener('change', updateRecurringFields);
        updateRecurringFields();
    }

    initializePaymentJs();
}());
