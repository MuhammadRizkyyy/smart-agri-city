<?php
namespace App\Models;

use PDO;
use PDOException;

class Database {
    private ?PDO $conn = null;

    public function getConnection(): PDO {
        if ($this->conn === null) {
            $host     = getenv('DB_HOST')     ?: '127.0.0.1';
            $db_name  = getenv('DB_DATABASE') ?: 'agriCity';
            $username = getenv('DB_USERNAME') ?: 'root';
            $password = getenv('DB_PASSWORD') ?: '';

            try {
                $dsn = "mysql:host={$host};dbname={$db_name};charset=utf8mb4";
                $this->conn = new PDO($dsn, $username, $password);
                $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            } catch (PDOException $exception) {
                error_log("Database Connection Error: " . $exception->getMessage());
                throw $exception;
            }
        }
        return $this->conn;
    }
}
