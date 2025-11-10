<?php
require_once __DIR__ . '/app.php';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Run this script from the command line.\n");
    exit(1);
}

try {
    $stmt = $pdo->query('SELECT id, username, email FROM users ORDER BY RAND() LIMIT 1');
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        echo "No users found.\n";
        exit(0);
    }
    echo 'Random user: id=' . (int)$user['id'] . ', username=' . (string)$user['username'] . ', email=' . (string)$user['email'] . "\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'Failed to fetch random user: ' . $e->getMessage() . "\n");
    exit(1);
}