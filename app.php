<?php
// Common bootstrap and helpers
session_start();

require_once __DIR__ . '/db.php';

function h($str) {
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

function is_logged_in(): bool {
    return isset($_SESSION['user_id']);
}

function require_login(): void {
    if (!is_logged_in()) {
        header('Location: /login.php');
        exit;
    }
}

function current_user(): ?array {
    if (!is_logged_in()) return null;
    global $pdo;
    $stmt = $pdo->prepare('SELECT id, username, email, created_at FROM users WHERE id = ?');
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
 * Create a campaign record.
 * Required: title, summary.
 * Optional: area, target_meals (int), start_date (YYYY-MM-DD), end_date (YYYY-MM-DD), status.
 * Returns inserted campaign ID.
 */
function create_campaign(array $data): int {
    global $pdo;

    $title = trim($data['title'] ?? '');
    $summary = trim($data['summary'] ?? '');
    if ($title === '' || $summary === '') {
        throw new InvalidArgumentException('title and summary are required');
    }

    $area = isset($data['area']) ? trim((string)$data['area']) : null;
    $targetMeals = isset($data['target_meals']) && $data['target_meals'] !== ''
        ? (int)$data['target_meals']
        : null;
    $startDate = isset($data['start_date']) ? trim((string)$data['start_date']) : null;
    $endDate = isset($data['end_date']) ? trim((string)$data['end_date']) : null;
    $status = isset($data['status']) && $data['status'] !== '' ? trim((string)$data['status']) : 'draft';

    $stmt = $pdo->prepare('INSERT INTO campaigns (title, summary, area, target_meals, start_date, end_date, status, created_at)
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([
        $title,
        $summary,
        $area,
        $targetMeals,
        $startDate,
        $endDate,
        $status,
        gmdate('c'),
    ]);

    return (int)$pdo->lastInsertId();
}