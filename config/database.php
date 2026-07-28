<?php
/**
 * Database Connection Class
 * Uses PDO with MySQL
 */
class Database {
    private $host   = 'localhost';
    private $dbname = 'dbevent';
    private $user   = 'root';
    private $pass   = '';

    public function connect(): PDO {
        try {
            $pdo = new PDO(
                "mysql:host={$this->host};dbname={$this->dbname};charset=utf8mb4",
                $this->user,
                $this->pass
            );
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            

            return $pdo;
        } catch (PDOException $e) {
            die("Database Connection Failed: " . $e->getMessage());
        }
    }
}
