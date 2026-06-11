<?php

namespace App;

use PDO;
use PDOException;

final class DatabaseManager
{
    private $pdo;
    private $dbType;

    public function __construct($dbType = 'sqlite')
    {
        $this->dbType = strtolower($dbType);
        $this->connect();
    }

    /**
     * Connect to database based on type
     */
    private function connect()
    {
        try {
            switch ($this->dbType) {
                case 'sqlite':
                    $this->connectSqlite();
                    break;
                case 'mysql':
                    $this->connectMysql();
                    break;
                case 'pgsql':
                    $this->connectPostgres();
                    break;
                default:
                    throw new RuntimeException("Unsupported database type: {$this->dbType}");
            }

            // Enable foreign keys for SQLite
            if ($this->dbType === 'sqlite') {
                $this->pdo->exec('PRAGMA foreign_keys = ON');
            }
        } catch (PDOException $e) {
            throw new RuntimeException("Database connection failed: " . $e->getMessage());
        }
    }

    /**
     * Connect to SQLite (free, serverless)
     */
    private function connectSqlite()
    {
        $dbPath = Config::storagePath('database.sqlite');
        $dsn = "sqlite:{$dbPath}";
        
        $this->pdo = new PDO($dsn);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    /**
     * Connect to MySQL
     */
    private function connectMysql()
    {
        $host = Config::get('DB_HOST', 'localhost');
        $port = Config::get('DB_PORT', 3306);
        $database = Config::get('DB_DATABASE', 'payments');
        $username = Config::get('DB_USERNAME', 'root');
        $password = Config::get('DB_PASSWORD', '');

        $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";
        
        $this->pdo = new PDO($dsn, $username, $password);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    /**
     * Connect to PostgreSQL
     */
    private function connectPostgres()
    {
        $host = Config::get('DB_HOST', 'localhost');
        $port = Config::get('DB_PORT', 5432);
        $database = Config::get('DB_DATABASE', 'payments');
        $username = Config::get('DB_USERNAME', 'postgres');
        $password = Config::get('DB_PASSWORD', '');

        $dsn = "pgsql:host={$host};port={$port};dbname={$database}";
        
        $this->pdo = new PDO($dsn, $username, $password);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    /**
     * Initialize log tables
     */
    public function initializeLogTables()
    {
        switch ($this->dbType) {
            case 'sqlite':
                $this->initializeSqliteTables();
                break;
            case 'mysql':
                $this->initializeMysqlTables();
                break;
            case 'pgsql':
                $this->initializePostgresTables();
                break;
        }
    }

    /**
     * Initialize SQLite tables
     */
    private function initializeSqliteTables()
    {
        $sql = <<<SQL
        CREATE TABLE IF NOT EXISTS logs_transactions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            timestamp TEXT NOT NULL,
            type TEXT NOT NULL,
            data TEXT,
            ip_address TEXT,
            user_agent TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS logs_errors (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            timestamp TEXT NOT NULL,
            type TEXT NOT NULL,
            severity TEXT NOT NULL,
            message TEXT NOT NULL,
            context TEXT,
            ip_address TEXT,
            user_agent TEXT,
            file TEXT,
            line INTEGER,
            trace TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS logs_payments (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            timestamp TEXT NOT NULL,
            type TEXT NOT NULL,
            payment_id TEXT,
            status TEXT,
            details TEXT,
            ip_address TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS logs_api_requests (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            timestamp TEXT NOT NULL,
            type TEXT NOT NULL,
            endpoint TEXT NOT NULL,
            method TEXT NOT NULL,
            request_data TEXT,
            response_status TEXT,
            ip_address TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE INDEX IF NOT EXISTS idx_transactions_timestamp ON logs_transactions(timestamp);
        CREATE INDEX IF NOT EXISTS idx_errors_timestamp ON logs_errors(timestamp);
        CREATE INDEX IF NOT EXISTS idx_payments_timestamp ON logs_payments(timestamp);
        CREATE INDEX IF NOT EXISTS idx_api_requests_timestamp ON logs_api_requests(timestamp);
        SQL;

        foreach (explode(';', $sql) as $statement) {
            $statement = trim($statement);
            if (!empty($statement)) {
                $this->pdo->exec($statement);
            }
        }
    }

    /**
     * Initialize MySQL tables
     */
    private function initializeMysqlTables()
    {
        $sql = <<<SQL
        CREATE TABLE IF NOT EXISTS logs_transactions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            timestamp VARCHAR(255) NOT NULL,
            type VARCHAR(50) NOT NULL,
            data LONGTEXT,
            ip_address VARCHAR(45),
            user_agent TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_timestamp (timestamp),
            INDEX idx_type (type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE IF NOT EXISTS logs_errors (
            id INT AUTO_INCREMENT PRIMARY KEY,
            timestamp VARCHAR(255) NOT NULL,
            type VARCHAR(50) NOT NULL,
            severity VARCHAR(50) NOT NULL,
            message TEXT NOT NULL,
            context LONGTEXT,
            ip_address VARCHAR(45),
            user_agent TEXT,
            file VARCHAR(255),
            line INT,
            trace LONGTEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_timestamp (timestamp),
            INDEX idx_severity (severity)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE IF NOT EXISTS logs_payments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            timestamp VARCHAR(255) NOT NULL,
            type VARCHAR(50) NOT NULL,
            payment_id VARCHAR(255),
            status VARCHAR(50),
            details LONGTEXT,
            ip_address VARCHAR(45),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_timestamp (timestamp),
            INDEX idx_payment_id (payment_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE IF NOT EXISTS logs_api_requests (
            id INT AUTO_INCREMENT PRIMARY KEY,
            timestamp VARCHAR(255) NOT NULL,
            type VARCHAR(50) NOT NULL,
            endpoint VARCHAR(255) NOT NULL,
            method VARCHAR(10) NOT NULL,
            request_data LONGTEXT,
            response_status VARCHAR(50),
            ip_address VARCHAR(45),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_timestamp (timestamp),
            INDEX idx_endpoint (endpoint)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL;

        foreach (explode(';', $sql) as $statement) {
            $statement = trim($statement);
            if (!empty($statement)) {
                $this->pdo->exec($statement);
            }
        }
    }

    /**
     * Initialize PostgreSQL tables
     */
    private function initializePostgresTables()
    {
        $sql = <<<SQL
        CREATE TABLE IF NOT EXISTS logs_transactions (
            id SERIAL PRIMARY KEY,
            timestamp VARCHAR(255) NOT NULL,
            type VARCHAR(50) NOT NULL,
            data TEXT,
            ip_address VARCHAR(45),
            user_agent TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );

        CREATE INDEX IF NOT EXISTS idx_transactions_timestamp ON logs_transactions(timestamp);

        CREATE TABLE IF NOT EXISTS logs_errors (
            id SERIAL PRIMARY KEY,
            timestamp VARCHAR(255) NOT NULL,
            type VARCHAR(50) NOT NULL,
            severity VARCHAR(50) NOT NULL,
            message TEXT NOT NULL,
            context TEXT,
            ip_address VARCHAR(45),
            user_agent TEXT,
            file VARCHAR(255),
            line INT,
            trace TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );

        CREATE INDEX IF NOT EXISTS idx_errors_timestamp ON logs_errors(timestamp);

        CREATE TABLE IF NOT EXISTS logs_payments (
            id SERIAL PRIMARY KEY,
            timestamp VARCHAR(255) NOT NULL,
            type VARCHAR(50) NOT NULL,
            payment_id VARCHAR(255),
            status VARCHAR(50),
            details TEXT,
            ip_address VARCHAR(45),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );

        CREATE INDEX IF NOT EXISTS idx_payments_timestamp ON logs_payments(timestamp);

        CREATE TABLE IF NOT EXISTS logs_api_requests (
            id SERIAL PRIMARY KEY,
            timestamp VARCHAR(255) NOT NULL,
            type VARCHAR(50) NOT NULL,
            endpoint VARCHAR(255) NOT NULL,
            method VARCHAR(10) NOT NULL,
            request_data TEXT,
            response_status VARCHAR(50),
            ip_address VARCHAR(45),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );

        CREATE INDEX IF NOT EXISTS idx_api_requests_timestamp ON logs_api_requests(timestamp);
        SQL;

        foreach (explode(';', $sql) as $statement) {
            $statement = trim($statement);
            if (!empty($statement)) {
                $this->pdo->exec($statement);
            }
        }
    }

    /**
     * Insert log entry
     */
    public function insertLog($logType, array $data)
    {
        $tableName = "logs_{$logType}";
        
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        
        $sql = "INSERT INTO {$tableName} ({$columns}) VALUES ({$placeholders})";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_values($data));
    }

    /**
     * Get logs by type with filters
     */
    public function getLogs($logType, array $filters = [])
    {
        $tableName = "logs_{$logType}";
        $sql = "SELECT * FROM {$tableName} WHERE 1=1";
        $params = [];

        if (!empty($filters['from_date'])) {
            $sql .= " AND timestamp >= ?";
            $params[] = $filters['from_date'];
        }

        if (!empty($filters['to_date'])) {
            $sql .= " AND timestamp <= ?";
            $params[] = $filters['to_date'];
        }

        if (!empty($filters['ip_address'])) {
            $sql .= " AND ip_address = ?";
            $params[] = $filters['ip_address'];
        }

        $sql .= " ORDER BY timestamp DESC LIMIT 1000";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Clear logs older than specified days
     */
    public function clearOldLogs($daysOld = 30)
    {
        $cutoffDate = gmdate('c', strtotime("-{$daysOld} days"));

        $tables = [
            'logs_transactions',
            'logs_errors',
            'logs_payments',
            'logs_api_requests',
        ];

        foreach ($tables as $table) {
            $sql = "DELETE FROM {$table} WHERE timestamp < ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$cutoffDate]);
        }
    }

    /**
     * Execute a raw query
     */
    public function query($sql, $params = [])
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Get database connection
     */
    public function getConnection()
    {
        return $this->pdo;
    }
}
