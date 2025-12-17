<?php
// config/db.php
class Database {
    private $host = "localhost";
    private $db_name = "library-system";
    private $username = "root";
    private $password = "";
    private $conn;


    public function connect() {
        $this->conn = null;

        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4",
                $this->username,
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::ERRMODE_ASSOCIATION);
            $this->conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        } catch(PDOException $e){
            error_log("Database connection error: " . $e->getMessage());
            die("Database connection failed. Please try again later");
        }

        return $this->conn;
    }

    // singleton instance
    private static $instance = null;

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = (new self())->connect();
        }
        return self::$instance;
    }

    // Helper function
    function getDB() {
        return Database::getInstance();
    }
}






?>