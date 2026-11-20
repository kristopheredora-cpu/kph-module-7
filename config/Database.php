<?php
class Database {
    private $pdo = null;

    public function getConnection() {
        if ($this->pdo === null) {
            $config = require __DIR__ . '/config.php';
            
            $dsn = "mysql:host=" . $config['host'] . ";dbname=" . $config['dbname'] . ";charset=utf8mb4";
            
            $this->pdo = new PDO($dsn, $config['user'], $config['pass']);
            // The build guide requires classes to throw exceptions on errors
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        }
        return $this->pdo;
    }
}