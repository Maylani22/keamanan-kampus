<?php
class Database {
    private $host;
    private $dbname;
    private $username;
    private $password;
    private $conn;

    public function __construct() {
        $this->host = DB_HOST;
        $this->dbname = DB_NAME;
        $this->username = DB_USER;
        $this->password = DB_PASS;
        $this->port = DB_PORT;
    }

    public function connect() {
        try {
            $this->conn = new PDO(
            "mysql:host={$this->host};port=" . DB_PORT . ";dbname={$this->dbname};charset=utf8",
            $this->username,
            $this->password,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]
        );

            return $this->conn;
        } catch(PDOException $e) {
            die("Connection failed: " . $e->getMessage());
        }
    }
}
?>