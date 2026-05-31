<?php

namespace App\Services;

use PDO;

class Database
{
    public static function connect()
    {
        $host = '127.0.0.1';
        $dbname = 'agricity';
        $username = 'root';
        $password = '';

        return new PDO(
            "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
            $username,
            $password
        );
    }
}