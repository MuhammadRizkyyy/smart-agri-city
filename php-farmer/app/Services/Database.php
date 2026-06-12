<?php

namespace App\Services;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $instance = null;

    public static function connect(): PDO
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        $host     = getenv('DB_HOST')     ?: '127.0.0.1';
        $port     = getenv('DB_PORT')     ?: '3306';
        $dbname   = getenv('DB_DATABASE') ?: 'agriCity';
        $username = getenv('DB_USERNAME') ?: 'root';
        $password = getenv('DB_PASSWORD') ?: '';

        $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";

        self::$instance = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);

        return self::$instance;
    }
}
