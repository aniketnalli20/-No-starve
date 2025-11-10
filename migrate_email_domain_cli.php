<?php
require_once __DIR__ . '/app.php';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Run from the command line.\n");
    exit(1);
}

$from = $argv[1] ?? '@example.com';
$to = $argv[2] ?? '@nostrv.com';

try {
    $stmt = $pdo->prepare('UPDATE users SET email = REPLACE(email, ?, ?) WHERE email LIKE ?');
    $like = '%' . $from;
    $stmt->execute([$from, $to, $like]);
    $affected = $stmt->rowCount();
    echo "Updated {$affected} users from {$from} to {$to}.\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'Migration failed: ' . $e->getMessage() . "\n");
    exit(1);
}