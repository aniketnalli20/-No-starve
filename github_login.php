<?php
require_once __DIR__ . '/app.php';

// Demo GitHub signup/login logic (no external OAuth; creates or finds a demo user)
$next = trim((string)($_GET['next'] ?? ''));
$email = 'github@nostrv.com';
$username = 'GitHub User';

try {
    // Find existing user
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row && isset($row['id'])) {
        $_SESSION['user_id'] = (int)$row['id'];
    } else {
        // Create account with random password hash
        $randPass = bin2hex(random_bytes(8));
        $id = register_user($username, $email, $randPass);
        $_SESSION['user_id'] = (int)$id;
    }
    $_SESSION['login_role'] = 'user';
    // Determine admin flag
    $st = $pdo->prepare('SELECT is_admin FROM users WHERE id = ?');
    $st->execute([$_SESSION['user_id']]);
    $r = $st->fetch(PDO::FETCH_ASSOC);
    $_SESSION['is_admin'] = ($r && (int)($r['is_admin'] ?? 0) === 1) ? 1 : 0;

    $dest = $next !== '' ? $next : 'index.php#hero';
    header('Location: ' . $BASE_PATH . $dest);
    exit;
} catch (Throwable $e) {
    http_response_code(500);
    echo h('GitHub login failed: ' . $e->getMessage());
    exit;
}