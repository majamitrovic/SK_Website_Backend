# AllSecure PHP Payment Integration

This project is a small PHP checkout using AllSecure payment.js for browser-side card tokenization and the official `allsecure-pay/php-exchange` SDK for server-side debit requests.

## Setup

1. Install dependencies:

```sh
composer install
```

2. Create your local config:

```sh
copy .env.example .env
```

3. Fill in your AllSecure API user, connector API key, shared secret, and public integration key in `.env`.

4. Run the backend API locally:

```sh
php -S localhost:8001 -t backend
```

5. Run the separate frontend locally:

```sh
php -S localhost:5173 -t frontend
```

6. Open `http://localhost:5173`.

For real payments, set `APP_URL` to your public HTTPS domain so AllSecure can reach `callback.php` and redirect customers back to your site.

For sandbox testing, use `ALLSECURE_GATEWAY_URL=https://asxgw.paymentsandbox.cloud/` and the sandbox public integration key supplied by AllSecure. On Windows PHP installs, cURL may also need a CA bundle. Download one to `storage/cacert.pem` and set `ALLSECURE_CURL_CAINFO=storage/cacert.pem`, or configure `curl.cainfo` globally in `php.ini`.

## Flow

- `frontend/index.html` renders the checkout as static HTML.
- `frontend/assets/checkout.js` loads frontend config, initializes `PaymentJs`, tokenizes card data, and posts the `transaction_token` to the backend API.
- `backend/api/config.php` exposes public checkout config only.
- `backend/api/pay.php` creates an `Exchange\Client\Transaction\Debit` request with the token.
- `public/callback.php` validates AllSecure postback signatures, records the callback summary, and responds with `OK`.
- `backend/api/status.php` can query AllSecure by `merchant_transaction_id`.
- `backend/api/schedule.php` can show, pause, continue, or cancel a recurring schedule by `schedule_id`.

## Recurring Payments

Enable "Create recurring payment schedule" on the checkout to send the first payment as a debit-with-register transaction. The backend sets `withRegister=true`, `transactionIndicator=INITIAL`, and attaches an AllSecure `ScheduleWithTransaction` with the recurring amount, interval, first charge date, and callback URL.

The first scheduled repeat must be more than 24 hours after the initial payment. Store the returned `scheduleId`; you can manage it at `/schedule.php`.

Gateway docs:

- https://asxgw.com/documentation/gateway
- https://asxgw.com/documentation/gateway#payment-js-javascript-integration
- https://github.com/allsecure-pay/exchange-php-client/blob/master/README.md
