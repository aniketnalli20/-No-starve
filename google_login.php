<?php
require_once __DIR__ . '/app.php';

// Simple Google-style login using provided email (no external OAuth)
// Supports two modes:
// - Redirect login: GET with optional `email` and `next`
// - AJAX email check: POST with `action=check_email` returns JSON

// Common domain typo fixes
function fix_domain_typo(string $email): array {
    $suggestion = null;
    $valid = false;
    $normalized = strtolower(trim($email));
    if ($normalized === '') return ['valid'=>false,'email'=>'','suggestion'=>null];
    // Basic validation
    if (!filter_var($normalized, FILTER_VALIDATE_EMAIL)) {
        // Try domain corrections on common typos
        $parts = explode('@', $normalized);
        if (count($parts) === 2) {
            $local = $parts[0]; $dom = $parts[1];
            $map = [
                'gmial.com' => 'gmail.com', 'gnail.com' => 'gmail.com', 'gmai.com' => 'gmail.com',
                'yaho.com' => 'yahoo.com', 'yahho.com' => 'yahoo.com',
                'hotmial.com' => 'hotmail.com', 'hotmai.com' => 'hotmail.com',
                'outlok.com' => 'outlook.com', 'outllok.com' => 'outlook.com'
            ];
            if (isset($map[$dom])) {
                $normalized = $local . '@' . $map[$dom];
                if (filter_var($normalized, FILTER_VALIDATE_EMAIL)) { $suggestion = $normalized; }
            }
        }
    } else { $valid = true; }
    return ['valid'=>$valid,'email'=>$normalized,'suggestion'=>$suggestion];
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_POST['action'] ?? '') === 'check_email') {
    header('Content-Type: application/json');
    $emailIn = trim((string)($_POST['email'] ?? ''));
    $fix = fix_domain_typo($emailIn);
    $email = $fix['email']; $suggestion = $fix['suggestion']; $valid = (bool)$fix['valid'];
    $exists = false; $mxOk = false;
    if ($email !== '' && strpos($email, '@') !== false) {
        $parts = explode('@', $email); $domain = $parts[1] ?? '';
        if ($domain !== '') {
            try {
                $mxRecords = [];
                if (function_exists('getmxrr')) { $mxOk = getmxrr($domain, $mxRecords) && !empty($mxRecords); }
                if (!$mxOk) {
                    $dns = @dns_get_record($domain, DNS_MX);
                    $mxOk = is_array($dns) && count($dns) > 0;
                }
            } catch (Throwable $e) { $mxOk = false; }
        }
        try {
            $st = $pdo->prepare('SELECT 1 FROM users WHERE email = ? LIMIT 1');
            $st->execute([$email]);
            $exists = (bool)$st->fetchColumn();
        } catch (Throwable $e) { $exists = false; }
    }
    echo json_encode(['ok'=>true,'valid'=>$valid,'email'=>$email,'suggestion'=>$suggestion,'exists'=>$exists,'mx'=>$mxOk]);
    exit;
}

$next = trim((string)($_GET['next'] ?? ''));
$emailIn = trim((string)($_GET['email'] ?? ''));
$usernameIn = trim((string)($_GET['username'] ?? ''));
if ($emailIn === '') { $emailIn = 'google@nostrv.com'; }
$username = $usernameIn !== '' ? $usernameIn : 'Google User';

try {
    $fix = fix_domain_typo($emailIn);
    $email = $fix['email'];
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { throw new InvalidArgumentException('Invalid email'); }
    // Find existing user
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row && isset($row['id'])) {
        $_SESSION['user_id'] = (int)$row['id'];
    } else {
        // Create a passwordless-style account with random password hash
        $randPass = bin2hex(random_bytes(8));
        $id = register_user($username, $email, $randPass);
        $_SESSION['user_id'] = (int)$id;
    }
    $_SESSION['login_role'] = 'user';
    $st = $pdo->prepare('SELECT is_admin FROM users WHERE id = ?');
    $st->execute([$_SESSION['user_id']]);
    $r = $st->fetch(PDO::FETCH_ASSOC);
    $_SESSION['is_admin'] = ($r && (int)($r['is_admin'] ?? 0) === 1) ? 1 : 0;

    $dest = $next !== '' ? $next : 'index.php#hero';
    header('Location: ' . $BASE_PATH . $dest);
    exit;
} catch (Throwable $e) {
    http_response_code(400);
    echo h('Google login failed: ' . $e->getMessage());
    exit;
}