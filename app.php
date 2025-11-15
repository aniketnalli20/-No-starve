<?php
// Common bootstrap and helpers
session_start();

require_once __DIR__ . '/db.php';

// Compute base path dynamically so links work from a subfolder (e.g., /No%20starve/) or as a vhost root
$BASE_PATH = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/') . '/';

function h($str) {
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

function is_logged_in(): bool {
    return isset($_SESSION['user_id']);
}

function require_login(): void {
    if (!is_logged_in()) {
        global $BASE_PATH;
        $script = $_SERVER['SCRIPT_NAME'] ?? '';
        $query = $_SERVER['QUERY_STRING'] ?? '';
        $next = '';
        if ($script) {
            // Reduce to basename to avoid leaking directories beyond app scope
            $base = basename($script);
            $next = $base;
            if ($query) {
                $next .= '?' . $query;
            }
        }
        $location = $BASE_PATH . 'login.php' . ($next ? ('?next=' . urlencode($next)) : '');
        header('Location: ' . $location);
        exit;
    }
}

function current_user(): ?array {
    if (!is_logged_in()) return null;
    global $pdo;
    $stmt = $pdo->prepare('SELECT id, username, email, phone, address, created_at FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    return $user ?: null;
}

function time_ago($datetime): string {
    $ts = strtotime($datetime);
    $diff = time() - $ts;
    if ($diff < 60) return $diff . 's ago';
    if ($diff < 3600) return floor($diff/60) . 'm ago';
    if ($diff < 86400) return floor($diff/3600) . 'h ago';
    return floor($diff/86400) . 'd ago';
}

/**
 * Append a simple record of a user entry to a text file.
 * Source can be 'register', 'import', etc.
 */
function log_user_entry(string $username, string $email, ?string $phone, ?string $address, string $source = 'register'): void {
    try {
        $path = __DIR__ . '/uploads/user_entries.txt';
        $date = gmdate('c');
        $line = $date
            . ' | ' . $source
            . ' | username=' . str_replace(["\r","\n"], '', (string)$username)
            . ' | email=' . str_replace(["\r","\n"], '', (string)$email)
            . ' | phone=' . str_replace(["\r","\n"], '', (string)($phone ?? ''))
            . ' | address=' . str_replace(["\r","\n"], '', (string)($address ?? ''));
        file_put_contents($path, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
    } catch (Throwable $e) {
        // Swallow logging errors; never block user operations
    }
}

/**
 * Register a new user and return the inserted user ID.
 * Validates username, email, and password; optional phone and address.
 */
function register_user(string $username, string $email, string $password, ?string $phone = null, ?string $address = null): int {
    global $pdo;

    $username = trim($username);
    $email = strtolower(trim($email));
    $password = (string)$password;
    $phone = $phone !== null ? trim($phone) : null;
    $address = $address !== null ? trim($address) : null;

    if ($username === '') {
        throw new InvalidArgumentException('Username is required');
    }
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('Valid email is required');
    }
    if (strlen($password) < 6) {
        throw new InvalidArgumentException('Password must be at least 6 characters long');
    }
    if ($phone !== null && $phone !== '' && !preg_match('/^[0-9+\-\s]{7,30}$/', $phone)) {
        throw new InvalidArgumentException('Phone must be 7–30 characters using digits, +, -, spaces');
    }
    if ($address !== null && strlen($address) > 5000) {
        throw new InvalidArgumentException('Address is too long');
    }

    // Ensure email is not already registered
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($existing) {
        throw new InvalidArgumentException('Email is already registered');
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $now = gmdate('Y-m-d H:i:s');
    $stmt = $pdo->prepare('INSERT INTO users (username, email, phone, address, password_hash, created_at) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([$username, $email, ($phone !== '' ? $phone : null), ($address !== '' ? $address : null), $hash, $now]);
    // Log the entry to a separate text file
    log_user_entry($username, $email, ($phone !== '' ? $phone : null), ($address !== '' ? $address : null), 'register');

    return (int)$pdo->lastInsertId();
}

/**
 * Create a campaign record.
 * Required: title, summary.
 * Optional: area, target_meals (int), start_date (YYYY-MM-DD), end_date (YYYY-MM-DD), status.
 * Returns inserted campaign ID.
 */
function create_campaign(array $data, ?array $imageFile = null): int {
    global $pdo;

    $contributorName = trim((string)($data['contributor_name'] ?? ''));
    $community = isset($data['community']) ? trim((string)$data['community']) : '';
    $location = trim((string)($data['location'] ?? ''));
    $crowdSize = isset($data['crowd_size']) && $data['crowd_size'] !== '' ? (int)$data['crowd_size'] : null;
    $closingTime = isset($data['closing_time']) ? trim((string)$data['closing_time']) : null;

    if ($contributorName === '' || $location === '' || $crowdSize === null || $closingTime === null) {
        throw new InvalidArgumentException('contributor_name, location, crowd_size, and closing_time are required');
    }

    $title = trim((string)($data['title'] ?? 'Campaign by ' . $contributorName));
    // Keep default summary generic to avoid duplicating meta details on the community page
    $summary = trim((string)($data['summary'] ?? 'Surplus food available; volunteers needed.'));

    $area = isset($data['area']) ? trim((string)$data['area']) : $location;
    $targetMeals = isset($data['target_meals']) && $data['target_meals'] !== '' ? (int)$data['target_meals'] : null;
    $startDate = isset($data['start_date']) ? trim((string)$data['start_date']) : null;
    $endDate = isset($data['end_date']) ? trim((string)$data['end_date']) : null;
    $status = isset($data['status']) && $data['status'] !== '' ? trim((string)$data['status']) : 'draft';

    $imageUrl = isset($data['image_url']) ? trim((string)$data['image_url']) : null;
    if (!$imageUrl && $imageFile && isset($imageFile['tmp_name']) && is_uploaded_file($imageFile['tmp_name'])) {
        $uploadsDir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads';
        if (!is_dir($uploadsDir)) {
            mkdir($uploadsDir, 0777, true);
        }
        $ext = strtolower(pathinfo($imageFile['name'] ?? '', PATHINFO_EXTENSION));
        $fname = 'campaign_' . uniqid('', true) . ($ext ? ('.' . $ext) : '');
        $dest = $uploadsDir . DIRECTORY_SEPARATOR . $fname;
        if (!move_uploaded_file($imageFile['tmp_name'], $dest)) {
            throw new RuntimeException('failed to upload image');
        }
        $imageUrl = 'uploads/' . $fname;
    }

    $latitude = isset($data['latitude']) && $data['latitude'] !== '' ? (float)$data['latitude'] : null;
    $longitude = isset($data['longitude']) && $data['longitude'] !== '' ? (float)$data['longitude'] : null;

    $stmt = $pdo->prepare('INSERT INTO campaigns (title, summary, area, target_meals, start_date, end_date, status, created_at, contributor_name, community, location, crowd_size, image_url, closing_time, latitude, longitude)
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([
        $title,
        $summary,
        $area,
        $targetMeals,
        $startDate,
        $endDate,
        $status,
        gmdate('Y-m-d H:i:s'),
        $contributorName,
        $community,
        $location,
        $crowdSize,
        $imageUrl,
        $closingTime,
        $latitude,
        $longitude,
    ]);

    return (int)$pdo->lastInsertId();
}