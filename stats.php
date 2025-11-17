<?php
require_once __DIR__ . '/app.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

try {
    $mode = isset($_GET['mode']) ? strtolower(trim((string)$_GET['mode'])) : 'summary';
    $statusParam = isset($_GET['status']) ? strtolower(trim((string)$_GET['status'])) : 'all';
    $statusParam = in_array($statusParam, ['open','closed','all'], true) ? $statusParam : 'all';
    $days = isset($_GET['days']) ? max(1, min(90, (int)$_GET['days'])) : 14;
    // Optional manual override from uploads/counters_override.json (summary only)
    $override = null;
    $overridePath = __DIR__ . '/uploads/counters_override.json';
    if ($mode === 'summary' && is_file($overridePath)) {
        try {
            $data = json_decode((string)file_get_contents($overridePath), true);
            if (is_array($data) && !empty($data['enabled'])) { $override = $data; }
        } catch (Throwable $e) {}
    }
    // Meals Made: sum of crowd_size (fallback to target_meals) based on status filter
    $mealsSaved = 0;
    try {
        $where = '';
        if ($statusParam === 'open') { $where = " WHERE status = 'open'"; }
        else if ($statusParam === 'closed') { $where = " WHERE status = 'closed'"; }
        $mealsSaved = (int)($pdo->query("SELECT COALESCE(SUM(COALESCE(crowd_size, target_meals)), 0) FROM campaigns" . $where)->fetchColumn() ?: 0);
    } catch (Throwable $e) {}

    // Contributors: distinct users who created campaigns
    $donorsCount = 0;
    try {
        $donorsCount = (int)($pdo->query("SELECT COUNT(DISTINCT user_id) FROM campaigns WHERE user_id IS NOT NULL")->fetchColumn() ?: 0);
    } catch (Throwable $e) {}

    // Partners: distinct contributor_name values from campaigns
    $partnersCount = 0;
    try {
        $partnersCount = (int)($pdo->query("SELECT COUNT(DISTINCT contributor_name) FROM campaigns WHERE contributor_name IS NOT NULL AND contributor_name <> ''")->fetchColumn() ?: 0);
    } catch (Throwable $e) {}

    // Active Users: distinct users who endorsed in the last 30 days
    $activeUsersCount = 0;
    try {
        $cutoff = gmdate('Y-m-d H:i:s', time() - (30 * 24 * 3600));
        $st = $pdo->prepare('SELECT COUNT(DISTINCT user_id) FROM endorsements WHERE user_id IS NOT NULL AND created_at >= ?');
        $st->execute([$cutoff]);
        $activeUsersCount = (int)($st->fetchColumn() ?: 0);
    } catch (Throwable $e) {}

    if ($mode === 'summary') {
        // If override is enabled but mealsSaved is 0 or missing, fallback to live computed value to keep it linked to campaign crowd_size
        $mealsOut = $mealsSaved;
        if (isset($override['mealsSaved'])) {
            $ovMeals = (int)$override['mealsSaved'];
            if ($ovMeals > 0) $mealsOut = $ovMeals; // use manual only when > 0
        }
        echo json_encode([
            'mealsSaved' => $mealsOut,
            'donorsCount' => isset($override['donorsCount']) ? (int)$override['donorsCount'] : $donorsCount,
            'partnersCount' => isset($override['partnersCount']) ? (int)$override['partnersCount'] : $partnersCount,
            'activeUsersCount' => isset($override['activeUsersCount']) ? (int)$override['activeUsersCount'] : $activeUsersCount,
            'status' => $statusParam,
        ]);
        return;
    }

    // mode=series: return meals per day for the last N days
    $series = [];
    try {
        $cutoff = gmdate('Y-m-d', time() - ($days * 24 * 3600));
        $where = " WHERE DATE(created_at) >= '" . $cutoff . "'";
        if ($statusParam === 'open') { $where .= " AND status = 'open'"; }
        else if ($statusParam === 'closed') { $where .= " AND status = 'closed'"; }
        $sql = "SELECT DATE(created_at) AS d, COALESCE(SUM(COALESCE(crowd_size, target_meals)),0) AS meals FROM campaigns" . $where . " GROUP BY DATE(created_at) ORDER BY DATE(created_at) ASC";
        $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $map = [];
        foreach ($rows as $r) { $map[(string)$r['d']] = (int)($r['meals'] ?? 0); }
        for ($i = $days - 1; $i >= 0; $i--) {
            $day = gmdate('Y-m-d', time() - ($i * 24 * 3600));
            $series[] = ['date' => $day, 'meals' => (int)($map[$day] ?? 0)];
        }
    } catch (Throwable $e) {}
    echo json_encode(['status' => $statusParam, 'days' => $days, 'series' => $series]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}