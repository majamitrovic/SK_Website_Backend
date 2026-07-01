<?php

require_once __DIR__ . '/../src/bootstrap.php';

use App\Config;

$envValue = trim((string) Config::get('APP_ENV', ''));
if ($envValue === '') {
    $envValue = 'not-set';
}


?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Environment info</title>
    <link rel="stylesheet" href="/assets/checkout.css">
</head>
<body>
    <main class="single-panel">
        <h1>Environment info</h1>
        <p>This page shows the environment values currently loaded by the application.</p>

        <dl>
            <div>
                <dt>APP_ENV</dt>
                <dd><?= htmlspecialchars($envValue, ENT_QUOTES, 'UTF-8') ?></dd>
            </div>
         
        </dl>
    </main>
</body>
</html>
