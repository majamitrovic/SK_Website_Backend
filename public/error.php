<?php

$merchantTransactionId = htmlspecialchars((string) ($_GET['merchant_transaction_id'] ?? ''), ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Payment error</title>
    <link rel="stylesheet" href="/assets/checkout.css">
</head>
<body>
    <main class="single-panel result error">
        <h1>Payment failed</h1>
        <?php if ($merchantTransactionId): ?>
            <p>Transaction: <code><?= $merchantTransactionId ?></code></p>
            <a class="button-link" href="/status.php?merchant_transaction_id=<?= rawurlencode($merchantTransactionId) ?>">Check status</a>
        <?php endif; ?>
        <a class="button-link secondary" href="/">Try another card</a>
    </main>
</body>
</html>
