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

        /**
                self::logAndAppend($file, $record);
            }

            private static function logAndAppend($file, array $record)
            {
                $path = Config::storagePath($file);
                $record['recordedAt'] = gmdate('c');
                file_put_contents($path, json_encode($record, JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND | LOCK_EX);

                // Log to Logger if enabled
                if (Config::bool('ENABLE_LOGGING')) {
                    if (strpos($file, 'callback_errors') !== false || strpos($file, 'transactions.jsonl') !== false) {
                        if (isset($record['success']) && $record['success'] === false || isset($record['exception'])) {
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
                    continue;

                }
                if (($record['merchantTransactionId'] ?? null) === $merchantTransactionId
                    && ($record['emailType'] ?? null) === $emailType) {
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
        $record['recordedAt'] = gmdate('c');
        file_put_contents($path, json_encode($record, JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND | LOCK_EX);

        // Log to Logger if enabled
        if (Config::bool('ENABLE_LOGGING')) {
            if (strpos($file, 'callback_errors') !== false || strpos($file, 'transactions.jsonl') !== false) {
                if ($record['success'] === false || isset($record['exception'])) {
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
}
