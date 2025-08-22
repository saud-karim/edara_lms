<?php
// Database Configuration
class Database {
    private $host = 'localhost';
    private $db_name = 'license_management';
    private $username = 'root'; // XAMPP default
    private $password = ''; // XAMPP default (empty password)
    private $conn;

    // Get database connection
    public function getConnection() {
        $this->conn = null;
        
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name,
                $this->username,
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch(PDOException $exception) {
            echo "Connection error: " . $exception->getMessage();
        }
        
        return $this->conn;
    }
}

// Global database connection function
function getDBConnection() {
    $database = new Database();
    return $database->getConnection();
}
?>