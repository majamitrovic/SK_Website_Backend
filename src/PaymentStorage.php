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
    }
}
