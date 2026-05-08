<?php

$merchantTransactionId = htmlspecialchars((string) ($_GET['merchant_transaction_id'] ?? ''), ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Payment cancelled</title>
    <link rel="stylesheet" href="/assets/checkout.css">
</head>
<body>
    <main class="single-panel result warning">
        <h1>Payment cancelled</h1>
        <?php if ($merchantTransactionId): ?>
            <p>Transaction: <code><?= $merchantTransactionId ?></code></p>
        <?php endif; ?>
        <a class="button-link" href="/">Return to checkout</a>
    </main>
</body>
</html>
