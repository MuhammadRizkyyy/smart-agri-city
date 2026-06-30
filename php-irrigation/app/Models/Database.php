<?php
namespace App\Models;

use PDO;
use PDOException;

class Database {
    private ?PDO $conn = null;

    public function getConnection(): PDO {
        if ($this->conn === null) {
            
            $host = getenv('DB_HOST') ?: 'mysql';
            $db_name = getenv('DB_NAME') ?: getenv('DB_DATABASE') ?: 'agriCity';
            $username = getenv('DB_USER') ?: getenv('DB_USERNAME') ?: 'root';
            $password = getenv('DB_PASS') ?: getenv('DB_PASSWORD') ?: 'rootpass';
            $port = getenv('DB_PORT') ?: 3306;

            try {
                $dsn = "mysql:host={$host};port={$port};dbname={$db_name};charset=utf8mb4";
                
                $options = [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_PERSISTENT         => true,
                    PDO::ATTR_TIMEOUT            => 5, // 5 second connection timeout
                ];

                $this->conn = new PDO($dsn, $username, $password, $options);
                
                // Set session-level timeout
                $this->conn->exec("SET SESSION net_read_timeout = 5, net_write_timeout = 5");
            } catch(PDOException $exception) {
                error_log("Database Connection Error: " . $exception->getMessage());
                throw new PDOException("Database Connection Error: " . $exception->getMessage(), (int)$exception->getCode(), $exception);
            }
        }
        return $this->conn;
    }
}