<?php
// Promote a user to admin via CLI
// Usage examples:
//   php scripts/promote_admin_cli.php --email akshar@nostrv.com
//   php scripts/promote_admin_cli.php --username Akshar
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Run from the command line only.\n");
    exit(1);
}

require_once __DIR__ . '/../db.php';

$email = null;
$username = null;
for ($i = 1; $i < $argc; $i++) {
    $arg = (string)$argv[$i];
    if (strpos($arg, '--email') === 0) {
        $email = isset($argv[$i+1]) ? (string)$argv[$i+1] : null;
        $i++;
    } elseif (strpos($arg, '--username') === 0) {
        $username = isset($argv[$i+1]) ? (string)$argv[$i+1] : null;
        $i++;
    }
}

if (!$email && !$username) {
    fwrite(STDERR, "Provide --email or --username\n");
    exit(2);
}

try {
    if ($email) {
        $stmt = $pdo->prepare('UPDATE users SET is_admin = 1 WHERE email = ?');
        $stmt->execute([$email]);
        $count = (int)$stmt->rowCount();
        if ($count === 0) {
            fwrite(STDOUT, "PROMOTE_FAILED email not found\n");
            exit(3);
        }
        fwrite(STDOUT, "PROMOTED_EMAIL=" . $email . "\n");
        exit(0);
    }

    $sel = $pdo->prepare('SELECT id, email FROM users WHERE username = ? ORDER BY id DESC LIMIT 1');
    $sel->execute([$username]);
    $row = $sel->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        fwrite(STDOUT, "PROMOTE_FAILED username not found\n");
        exit(4);
    }
    $upd = $pdo->prepare('UPDATE users SET is_admin = 1 WHERE id = ?');
    $upd->execute([(int)$row['id']]);
    fwrite(STDOUT, "PROMOTED_EMAIL=" . (string)$row['email'] . "\n");
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'ERROR ' . $e->getMessage() . "\n");
    exit(5);
}