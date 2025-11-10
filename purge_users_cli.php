<?php
// CLI script to purge all users for a clean re-import
require_once __DIR__ . '/app.php';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from the command line.\n");
    exit(1);
}

try {
    // Count existing rows
    $count = 0;
    try {
        $st = $pdo->query('SELECT COUNT(*) FROM users');
        $count = (int)$st->fetchColumn();
    } catch (Throwable $e) {}

    // Purge users safely
    $pdo->exec('DELETE FROM users');
    // Reset auto-increment if supported
    try { $pdo->exec('ALTER TABLE users AUTO_INCREMENT = 1'); } catch (Throwable $e) {}

    echo "Purged users: removed {$count} rows.\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'Purge failed: ' . $e->getMessage() . "\n");
    exit(1);
}