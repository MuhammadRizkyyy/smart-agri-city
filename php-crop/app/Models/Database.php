<?php
namespace App\Models;

use PDO;
use PDOException;

class Database {
    private ?PDO $conn = null;
    private static ?Database $instance = null;

    public static function getInstance(): Database {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection(): PDO {
        if ($this->conn === null) {
            $this->connect();
        }
        return $this->conn;
    }

    private function connect(): void {
        $host     = getenv('DB_HOST')     ?: 'mysql';
        $db_name  = getenv('DB_DATABASE') ?: 'agriCity';
        $username = getenv('DB_USERNAME') ?: 'root';
        $password = getenv('DB_PASSWORD') ?: '';
        $port     = (int)(getenv('DB_PORT') ?: 3306);

        try {
            $dsn = "mysql:host={$host};port={$port};dbname={$db_name};charset=utf8mb4";
            
            $this->conn = new PDO($dsn, $username, $password, [
                PDO::ATTR_TIMEOUT => 5,
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_PERSISTENT => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET SESSION sql_mode='STRICT_TRANS_TABLES', SESSION wait_timeout=30"
            ]);

            $this->conn->query("SELECT 1");
            
            error_log("[Database] ✅ Connected to MySQL: $host:$port/$db_name");

        } catch (PDOException $exception) {
            error_log("[Database] ❌ Connection Error: " . $exception->getMessage());
            throw $exception;
        }
    }

    public function closeConnection(): void {
        $this->conn = null;
    }

    public function __destruct() {
        $this->closeConnection();
    }
}
