<?php

namespace App;

use PDO;

class Database
{
    private $pdo;

    public function __construct()
    {
        $dbPath = __DIR__ . '/../database.sqlite';
        $this->pdo = new PDO('sqlite:' . $dbPath);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function init()
    {
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY,
            amtgard_sub TEXT UNIQUE,
            email TEXT,
            access_token TEXT,
            refresh_token TEXT,
            access_expires_at INTEGER,
            refresh_expires_at INTEGER,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
    }

    public function getUser($id)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE id = :id");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getLastUser()
    {
        $stmt = $this->pdo->query("SELECT * FROM users ORDER BY id DESC LIMIT 1");
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function hasAnyUser()
    {
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM users");
        return $stmt->fetchColumn() > 0;
    }

    public function upsertUser($sub, $email, $accessToken, $refreshToken, $accessExpiresAt, $refreshExpiresAt)
    {
        // Check if exists
        $stmt = $this->pdo->prepare("SELECT id FROM users WHERE amtgard_sub = :sub");
        $stmt->execute([':sub' => $sub]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // Update
            $stmt = $this->pdo->prepare("UPDATE users SET email = :email, access_token = :at, refresh_token = :rt, access_expires_at = :aea, refresh_expires_at = :rea WHERE id = :id");
            $stmt->execute([
                ':email' => $email,
                ':at' => $accessToken,
                ':rt' => $refreshToken,
                ':aea' => $accessExpiresAt,
                ':rea' => $refreshExpiresAt,
                ':id' => $user['id']
            ]);
            return $user['id'];
        } else {
            // Insert
            $stmt = $this->pdo->prepare("INSERT INTO users (amtgard_sub, email, access_token, refresh_token, access_expires_at, refresh_expires_at) VALUES (:sub, :email, :at, :rt, :aea, :rea)");
            $stmt->execute([
                ':sub' => $sub,
                ':email' => $email,
                ':at' => $accessToken,
                ':rt' => $refreshToken,
                ':aea' => $accessExpiresAt,
                ':rea' => $refreshExpiresAt
            ]);
            return $this->pdo->lastInsertId();
        }
    }

    public function clearUsers()
    {
        $this->pdo->exec("DROP TABLE IF EXISTS users");
    }

    public function reinit()
    {
        $this->clearUsers();
        $this->init();
    }
}
