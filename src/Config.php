<?php

namespace App;

use RuntimeException;

final class Config
{
    private static $root;

    public static function load($root)
    {
        self::$root = rtrim($root, DIRECTORY_SEPARATOR);
        self::loadEnvFile(self::$root . DIRECTORY_SEPARATOR . '.env');
    }

    public static function get($key, $default = null)
    {
        if (array_key_exists($key, $_ENV)) {
            return $_ENV[$key];
        }

        if (array_key_exists($key, $_SERVER)) {
            return $_SERVER[$key];
        }

        $value = getenv($key);
        return $value === false ? $default : $value;
    }

    public static function bool($key, $default = false)
    {
        $value = self::get($key);

        if ($value === null || $value === '') {
            return $default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $default;
    }

    public static function required(array $keys)
    {
        $missing = array();

        foreach ($keys as $key) {
            $value = self::get($key);
            if ($value === null || trim((string) $value) === '' || strpos((string) $value, 'replace_with_') === 0) {
                $missing[] = $key;
            }
        }

        if ($missing) {
            throw new RuntimeException('Missing required environment values: ' . implode(', ', $missing));
        }
    }

    public static function projectPath($path = '')
    {
        return self::$root . ($path === '' ? '' : DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR));
    }

    public static function storagePath($path = '')
    {
        return self::projectPath('storage' . ($path === '' ? '' : DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR)));
    }

    /**
     * Return configured backend base URL.
     * Priority: BACKEND_BASE_URL, APP_URL, auto-detect.
     */
    public static function baseBackend()
    {
        $configured = trim((string) self::get('BACKEND_BASE_URL', ''));
        if ($configured !== '') {
            return rtrim($configured, '/');
        }

        $configured = trim((string) self::get('APP_URL', ''));
        if ($configured !== '' && strpos($configured, 'your-domain.example') === false) {
            return rtrim($configured, '/');
        }

        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
        $scheme = $https ? 'https' : 'http';
        $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '127.0.0.1:8000';

        return $scheme . '://' . $host;
    }

    /**
     * Return configured frontend base URL.
     * Priority: FRONTEND_BASE_URL, APP_URL, auto-detect.
     */
    public static function baseFrontend()
    {
        $configured = trim((string) self::get('FRONTEND_BASE_URL', ''));
        if ($configured !== '' && strpos($configured, 'your-domain.example') === false) {
            return rtrim($configured, '/');
        }

        $configured = trim((string) self::get('APP_URL', ''));
        if ($configured !== '' && strpos($configured, 'your-domain.example') === false) {
            return rtrim($configured, '/');
        }

        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
        $scheme = $https ? 'https' : 'http';
        $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '127.0.0.1:8000';

        return $scheme . '://' . $host;
    }

    private static function loadEnvFile($path)
    {
        if (!is_file($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!$lines) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || strpos($line, '#') === 0 || strpos($line, '=') === false) {
                continue;
            }

            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = self::cleanEnvValue(trim($value));

            if ($key === '' || array_key_exists($key, $_ENV)) {
                continue;
            }

            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
            putenv($key . '=' . $value);
        }
    }

    private static function cleanEnvValue($value)
    {
        if ($value === '') {
            return '';
        }

        $first = substr($value, 0, 1);
        $last = substr($value, -1);

        if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
            return substr($value, 1, -1);
        }

        return $value;
    }
}
