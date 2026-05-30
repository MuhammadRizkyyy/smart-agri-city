<?php
namespace App\Services;

class EnvLoader {
    public static function load(string $dir): void {
        $envPaths = [
            $dir . '/.env',
            dirname($dir) . '/.env',
            dirname(dirname($dir)) . '/.env'
        ];

        foreach ($envPaths as $path) {
            if (file_exists($path)) {
                $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                foreach ($lines as $line) {
                    if (str_starts_with(trim($line), '#')) {
                        continue;
                    }
                    if (strpos($line, '=') !== false) {
                        list($key, $value) = explode('=', $line, 2);
                        $key = trim($key);
                        $value = trim($value);

                        if (str_starts_with($value, '"') && str_ends_with($value, '"')) {
                            $value = substr($value, 1, -1);
                        } elseif (str_starts_with($value, "'") && str_ends_with($value, "'")) {
                            $value = substr($value, 1, -1);
                        }

                        if (!getenv($key)) {
                            putenv("$key=$value");
                        }
                        if (!isset($_ENV[$key])) {
                            $_ENV[$key] = $value;
                        }
                        if (!isset($_SERVER[$key])) {
                            $_SERVER[$key] = $value;
                        }
                    }
                }
                break;
            }
        }
    }
}
