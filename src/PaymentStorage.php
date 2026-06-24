<?php

namespace App;

final class PaymentStorage
{
    public static function append($file, array $record)
    {
        $path = Config::storagePath($file);
        $dir = dirname($path);

        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $record['recordedAt'] = gmdate('c');
        file_put_contents($path, json_encode($record, JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND | LOCK_EX);

        if (Config::bool('ENABLE_LOGGING')) {
            if (strpos($file, 'callback_errors') !== false || strpos($file, 'transactions.jsonl') !== false) {
                if ((isset($record['success']) && $record['success'] === false) || isset($record['exception'])) {
                    Logger::logError(
                        'Payment storage error: ' . ($record['message'] ?? 'Unknown error'),
                        $record,
                        'warning'
                    );
                } else {
                    Logger::logTransaction($record);
                }
            }
        }
    }

    /**
     * Check whether a customer email of a given type was already sent for a merchant transaction
     */
    public static function emailWasSent(string $merchantTransactionId, string $emailType): bool
    {
        $path = Config::storagePath('email_events.jsonl');
        if (!file_exists($path)) {
            return false;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!$lines) {
            return false;
        }

        foreach ($lines as $line) {
            $record = json_decode($line, true);
            if (!is_array($record)) {
                continue;
            }

            if ((($record['merchantTransactionId'] ?? null) === $merchantTransactionId)
                && (($record['emailType'] ?? null) === $emailType)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Mark that a customer email has been sent for a transaction
     */
    public static function markEmailSent(string $merchantTransactionId, string $emailType, array $context = [])
    {
        $path = Config::storagePath('email_events.jsonl');
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $record = [
            'merchantTransactionId' => $merchantTransactionId,
            'emailType' => $emailType,
            'context' => $context,
            'recordedAt' => gmdate('c'),
        ];

        file_put_contents($path, json_encode($record, JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    /**
     * Retrieve customer email for a merchant transaction from stored transactions
     *
     * Returns null if not found or file not readable.
     */
    public static function getCustomerEmail(string $merchantTransactionId): ?string
    {
        $path = Config::storagePath('transactions.jsonl');
        if (!is_readable($path)) {
            return null;
        }

        $fp = fopen($path, 'r');
        if (!$fp) {
            return null;
        }

        while (($line = fgets($fp)) !== false) {
            $rec = json_decode(trim($line), true);
            if (!is_array($rec)) {
                continue;
            }

            if ((($rec['merchantTransactionId'] ?? null) === $merchantTransactionId)) {
                fclose($fp);
                return $rec['customer_email'] ?? $rec['email'] ?? null;
            }
        }

        fclose($fp);
        return null;
    }

      /**
     * Retrieve stored transactions by merchantId
     *
     * Returns null if not found or file not readable.
     */
    public static function getTransaction(string $merchantTransactionId): ?array
    {
        $path = Config::storagePath('transactions.jsonl');
        if (!is_readable($path)) {
            return null;
        }

        $fp = fopen($path, 'r');
        if (!$fp) {
            return null;
        }

        while (($line = fgets($fp)) !== false) {
            $rec = json_decode(trim($line), true);
            if (!is_array($rec)) {
                continue;
            }

            if ((($rec['merchantTransactionId'] ?? null) === $merchantTransactionId)) {
                fclose($fp);
                return $rec ?? null;
            }
        }

        fclose($fp);
        return null;
    }

    /**
     * Check whether a cancel token was already used
     */
    public static function tokenWasUsed(string $token): bool
    {
        $path = Config::storagePath('used_tokens.jsonl');
        if (!file_exists($path)) {
            return false;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!$lines) {
            return false;
        }

        foreach ($lines as $line) {
            $record = json_decode($line, true);
            if (!is_array($record)) {
                continue;
            }
            if (($record['token'] ?? null) === $token) {
                return true;
            }
        }

        return false;
    }

    /**
     * Mark a token as used to prevent reuse
     */
    public static function markTokenUsed(string $token, array $context = [])
    {
        $path = Config::storagePath('used_tokens.jsonl');
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $record = [
            'token' => $token,
            'context' => $context,
            'recordedAt' => gmdate('c'),
        ];

        file_put_contents($path, json_encode($record, JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}
