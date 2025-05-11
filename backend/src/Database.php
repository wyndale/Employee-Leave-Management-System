<?php

class Database {
    private static $instance = null;
    private $connection;

    // Database configuration
    private $host = 'localhost';
    private $dbname = 'leave_management';
    private $username = 'root'; // Default MySQL username (adjust as needed)
    private $password = '';     // Default MySQL password (adjust as needed)

    /**
     * Private constructor to prevent direct instantiation (Singleton pattern)
     */
    private function __construct() {
        try {
            $dsn = "mysql:host={$this->host};dbname={$this->dbname};charset=utf8mb4";
            $this->connection = new PDO($dsn, $this->username, $this->password);
            // Set PDO attributes for security and error handling
            $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $this->connection->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        } catch (PDOException $e) {
            // Log the error in a production environment; for development, display it
            die("Database connection failed: " . $e->getMessage());
        }
    }

    /**
     * Get the singleton instance of the Database class
     * @return Database
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Get the PDO connection
     * @return PDO
     */
    public function getConnection() {
        return $this->connection;
    }

    /**
     * Prevent cloning of the instance (Singleton pattern)
     */
    private function __clone() {}

    /**
     * Prevent unserializing of the instance (Singleton pattern)
     */
    public function __wakeup() {
        throw new Exception("Cannot unserialize a singleton.");
    }
}

?>