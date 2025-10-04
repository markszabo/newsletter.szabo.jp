<?php

class Database {
    private $pdo;

    public function __construct($host, $name, $user, $pass) {
        $this->pdo = new PDO("mysql:host=$host;dbname=$name;charset=utf8mb4", $user, $pass);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->initializeSchema();
    }

    public function getPDO() {
        return $this->pdo;
    }

    private function initializeSchema() {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS subscribers (
                id INT AUTO_INCREMENT PRIMARY KEY,
                email VARCHAR(255) UNIQUE NOT NULL,
                confirmed TINYINT(1) DEFAULT 0,
                token VARCHAR(64),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
    }

    public function addSubscriber($email, $token) {
        $stmt = $this->pdo->prepare("INSERT INTO subscribers (email, token) VALUES (?, ?) ON DUPLICATE KEY UPDATE token=?");
        $stmt->execute([$email, $token, $token]);
    }

    public function confirmSubscriber($token) {
        $stmt = $this->pdo->prepare("UPDATE subscribers SET confirmed=1 WHERE token=?");
        return $stmt->execute([$token]);
    }

    public function deleteSubscriber($token) {
        $stmt = $this->pdo->prepare("DELETE FROM subscribers WHERE token=?");
        return $stmt->execute([$token]);
    }

    public function getConfirmedSubscribers() {
        $stmt = $this->pdo->query("SELECT email, token FROM subscribers WHERE confirmed=1");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getSubscriberByToken($token) {
        $stmt = $this->pdo->prepare("SELECT * FROM subscribers WHERE token=?");
        $stmt->execute([$token]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getSubscriberByEmail($email) {
        $stmt = $this->pdo->prepare("SELECT * FROM subscribers WHERE email=?");
        $stmt->execute([$email]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
