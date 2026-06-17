<?php

$root = dirname(__DIR__);

require_once $root . '/vendor/autoload.php';

\App\Config::load($root);

// Initialize logging system
try {
    if (\App\Config::bool('ENABLE_LOGGING', true)) {
        if (\App\Config::bool('ENABLE_DATABASE_LOGGING', false)) {
            $dbType = \App\Config::get('DB_TYPE', 'sqlite');
            $db = new \App\DatabaseManager($dbType);
            \App\Logger::initialize($db);
        } else {
            \App\Logger::initialize();
        }
    }
} catch (\Exception $e) {
    error_log("Failed to initialize logging: " . $e->getMessage());
}
