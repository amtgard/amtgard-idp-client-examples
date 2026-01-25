<?php
// src/db.php

function getDb()
{
    $dbPath = __DIR__ . '/../database.sqlite';
    return new PDO('sqlite:' . $dbPath);
}

function initDb($db)
{
    $db->exec("CREATE TABLE IF NOT EXISTS users (
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

function getUser($db, $id)
{
    $stmt = $db->prepare("SELECT * FROM users WHERE id = :id");
    $stmt->execute([':id' => $id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function upsertUser($db, $sub, $email, $accessToken, $refreshToken, $accessExpiresAt, $refreshExpiresAt)
{
    // Check if exists
    $stmt = $db->prepare("SELECT id FROM users WHERE amtgard_sub = :sub");
    $stmt->execute([':sub' => $sub]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        // Update
        $stmt = $db->prepare("UPDATE users SET email = :email, access_token = :at, refresh_token = :rt, access_expires_at = :aea, refresh_expires_at = :rea WHERE id = :id");
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
        $stmt = $db->prepare("INSERT INTO users (amtgard_sub, email, access_token, refresh_token, access_expires_at, refresh_expires_at) VALUES (:sub, :email, :at, :rt, :aea, :rea)");
        $stmt->execute([
            ':sub' => $sub,
            ':email' => $email,
            ':at' => $accessToken,
            ':rt' => $refreshToken,
            ':aea' => $accessExpiresAt,
            ':rea' => $refreshExpiresAt
        ]);
        return $db->lastInsertId();
    }
}

function clearUsers($db)
{
    $db->exec("DROP TABLE IF EXISTS users");
}

function hasAnyUser($db)
{
    $stmt = $db->query("SELECT COUNT(*) FROM users");
    return $stmt->fetchColumn() > 0;
}

function getLastUser($db)
{
    // Get the user with the highest ID (most recently added/created)
    // In a single user client scenario, this effectively gets "the user".
    $stmt = $db->query("SELECT * FROM users ORDER BY id DESC LIMIT 1");
    return $stmt->fetch(PDO::FETCH_ASSOC);
}
