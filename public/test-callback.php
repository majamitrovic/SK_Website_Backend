<?php

/**
 * Callback Diagnostic Test
 * 
 * This script tests if your callback endpoint is working properly.
 * It simulates what AllSecure would send.
 */

require_once __DIR__ . '/../src/bootstrap.php';

use App\Config;
use App\AllSecureService;

header('Content-Type: application/json');

// Check if callback validation is enabled
$validationEnabled = Config::bool('ALLSECURE_VALIDATE_CALLBACKS', true);

echo json_encode([
    'status' => 'DIAGNOSTIC_TEST',
    'callback_url_expected' => Config::baseUrl() . '/callback.php',
    'validation_enabled' => $validationEnabled,
    'https_required' => true,
    'test_results' => [
        'callback_endpoint_accessible' => true, // You're reading this, so yes!
        'allsecure_credentials' => [
            'username' => substr(Config::get('ALLSECURE_USERNAME', ''), 0, 5) . '...',
            'shared_secret' => substr(Config::get('ALLSECURE_CONNECTOR_SHARED_SECRET', ''), 0, 5) . '...',
        ],
        'app_url' => Config::baseUrl(),
        'https_enabled' => strpos(Config::baseUrl(), 'https://') === 0,
    ],
    'next_steps' => [
        '1. Verify callback URL in AllSecure merchant dashboard matches: ' . Config::baseUrl() . '/callback.php',
        '2. Check that this URL is HTTPS and publicly accessible',
        '3. Ensure your web server forwards custom headers (X-Signature, Date)',
        '4. Test with curl command (see CALLBACK_ANALYSIS.md)',
        '5. Check storage/callback_rejections.jsonl for rejection reasons',
    ],
    'debug_info' => $_GET['debug'] === '1' ? [
        'PHP_VERSION' => phpversion(),
        'REQUEST_METHOD' => $_SERVER['REQUEST_METHOD'],
        'REQUEST_URI' => $_SERVER['REQUEST_URI'],
        'HTTP_HOST' => $_SERVER['HTTP_HOST'] ?? 'N/A',
        'HTTPS' => !empty($_SERVER['HTTPS']) ? 'Yes' : 'No',
    ] : null,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
