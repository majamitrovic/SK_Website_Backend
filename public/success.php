<?php

$merchantTransactionId = htmlspecialchars((string) ($_GET['merchant_transaction_id'] ?? ''), ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Payment successful</title>
    <link rel="stylesheet" href="/assets/checkout.css">
</head>
<body>
    <main class="single-panel result success">
        <h1>Payment successful</h1>
        <?php if ($merchantTransactionId): ?>
            <p>Transaction: <code><?= $merchantTransactionId ?></code></p>
            <a class="button-link" href="/status.php?merchant_transaction_id=<?= rawurlencode($merchantTransactionId) ?>">View status</a>
        <?php endif; ?>
    </main>
</body>
</html>
