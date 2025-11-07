<?php
namespace App\Core;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $instance = null;
    private array $config;

    private function __construct()
    {
        // Load database configuration from database.php
        // ROOT_PATH is defined in public/index.php
        $this->config = require ROOT_PATH . '/app/config/database.php';
        $dsn = "mysql:host={$this->config['host']};dbname={$this->config['name']};charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            self::$instance = new PDO($dsn, $this->config['user'], $this->config['password'], $options);
        } catch (PDOException $e) {
            // Log the detailed error for debugging purposes
            error_log("Database connection failed: " . $e->getMessage());
            // Throw the exception to be caught at a higher level (e.g., App::run())
            throw new PDOException("Database connection failed: " . $e->getMessage(), (int)$e->getCode(), $e);
        }
    }

    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            // Initialize the singleton instance if it doesn't exist
            // This will call the private __construct() and attempt to connect to the database
            new self();
        }
        return self::$instance;
    }
}
