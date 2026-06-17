<?php

require_once __DIR__ . '/../src/bootstrap.php';

use App\Config;

$merchantTransactionId = htmlspecialchars((string) ($_GET['merchant_transaction_id'] ?? ''), ENT_QUOTES, 'UTF-8');

// Prefer explicit APP_URL from environment, fall back to Config::baseUrl()
$siteUrl = trim((string) Config::get('APP_URL', ''));
if ($siteUrl === '') {
    $siteUrl = Config::baseFrontend();
}

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Uspešno plaćanje</title>
    <link rel="stylesheet" href="/assets/checkout.css">
</head>
<body>
    <main class="single-panel result success">
        <h1>Uspešno plaćanje</h1>
        <?php if ($merchantTransactionId): ?>
            <p>Transaction: <code><?= $merchantTransactionId ?></code></p>
            <a class="button-link" href="<?= htmlspecialchars($siteUrl, ENT_QUOTES, 'UTF-8') ?>">Povratak na sajt</a>
        <?php endif; ?>
    </main>
</body>
</html>
