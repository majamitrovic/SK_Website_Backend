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
