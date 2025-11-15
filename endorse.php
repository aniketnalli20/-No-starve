<?php
require_once __DIR__ . '/app.php';

header('Content-Type: application/json');

// Require login to prevent duplicate anonymous endorsements; enables per-user unique tracking
require_login();

$campaignId = isset($_POST['campaign_id']) ? (int)$_POST['campaign_id'] : 0;
$kind = isset($_POST['kind']) && $_POST['kind'] !== '' ? trim((string)$_POST['kind']) : 'campaign';
if ($kind !== 'campaign' && $kind !== 'contributor') { $kind = 'campaign'; }

if ($campaignId <= 0) {
    echo json_encode(['ok' => false, 'error' => 'invalid_campaign']);
    exit;
}

try {
    global $pdo;
    $pdo->beginTransaction();
    // Ensure campaign exists
    $st = $pdo->prepare('SELECT id, contributor_name FROM campaigns WHERE id = ? LIMIT 1');
    $st->execute([$campaignId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        $pdo->rollBack();
        echo json_encode(['ok' => false, 'error' => 'not_found']);
        exit;
    }

    $userId = $_SESSION['user_id'] ?? null;
    // Check duplicate per user
    $dupe = null;
    if ($userId) {
        try {
            $st2 = $pdo->prepare('SELECT id FROM endorsements WHERE campaign_id = ? AND kind = ? AND user_id = ? LIMIT 1');
            $st2->execute([$campaignId, $kind, (int)$userId]);
            $dupe = $st2->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) { /* fallback: ignore */ }
    }

    if (!$dupe) {
        // Insert endorsement event
        $ins = $pdo->prepare('INSERT INTO endorsements (campaign_id, kind, contributor_name, ip, user_agent, created_at, user_id) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $ins->execute([
            $campaignId,
            $kind,
            (string)($row['contributor_name'] ?? ''),
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
            gmdate('Y-m-d H:i:s'),
            $userId ? (int)$userId : null,
        ]);

        // Update aggregate count on campaigns
        $col = ($kind === 'contributor') ? 'endorse_contributor' : 'endorse_campaign';
        $pdo->prepare("UPDATE campaigns SET $col = COALESCE($col, 0) + 1 WHERE id = ?")->execute([$campaignId]);
    }

    // Fetch updated count
    $col = ($kind === 'contributor') ? 'endorse_contributor' : 'endorse_campaign';
    $st3 = $pdo->prepare("SELECT $col AS c FROM campaigns WHERE id = ?");
    $st3->execute([$campaignId]);
    $count = (int)($st3->fetchColumn() ?: 0);

    $pdo->commit();
    echo json_encode(['ok' => true, 'count' => $count]);
    exit;
} catch (Throwable $e) {
    try { $pdo->rollBack(); } catch (Throwable $ignored) {}
    echo json_encode(['ok' => false, 'error' => 'server_error']);
    exit;
}