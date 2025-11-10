<?php
// SQLite database connection and initialization
$dbPath = __DIR__ . '/database.sqlite';

try {
    $pdo = new PDO('sqlite:' . $dbPath, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA foreign_keys = ON');
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Database connection failed: ' . h($e->getMessage());
    exit;
}

// Initialize schema if not exists
$pdo->exec('CREATE TABLE IF NOT EXISTS reports (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    reporter_name TEXT NOT NULL,
    contact TEXT,
    item TEXT NOT NULL,
    quantity TEXT NOT NULL,
    category TEXT NOT NULL,
    location TEXT,
    status TEXT NOT NULL DEFAULT "pending",
    created_at TEXT NOT NULL
)');