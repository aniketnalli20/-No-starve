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
    echo 'Database connection failed: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
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

// Listings posted by donors for NGOs/recipients to claim
$pdo->exec('CREATE TABLE IF NOT EXISTS listings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    donor_type TEXT NOT NULL,            -- Restaurant, Caterer, Individual
    donor_name TEXT NOT NULL,
    contact TEXT,
    item TEXT NOT NULL,
    quantity TEXT NOT NULL,
    category TEXT NOT NULL,
    address TEXT,
    city TEXT,
    pincode TEXT,
    expires_at TEXT,                     -- ISO8601 timestamp
    image_url TEXT,                      -- URL/path to uploaded image
    status TEXT NOT NULL DEFAULT "open", -- open | claimed | expired | closed
    created_at TEXT NOT NULL,
    claimed_at TEXT
)');

// Claims by NGOs/volunteers for a listing
$pdo->exec('CREATE TABLE IF NOT EXISTS claims (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    listing_id INTEGER NOT NULL,
    ngo_name TEXT,
    claimer_name TEXT NOT NULL,
    contact TEXT,
    notes TEXT,
    created_at TEXT NOT NULL,
    FOREIGN KEY(listing_id) REFERENCES listings(id) ON DELETE CASCADE
)');