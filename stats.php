<?php
require_once __DIR__ . '/app.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

try {
    // Meals Saved: count of listings that have been claimed
    $mealsSaved = 0;
    try {
        $mealsSaved = (int)$pdo->query('SELECT COUNT(*) FROM listings WHERE claimed_at IS NOT NULL')->fetchColumn();
    } catch (Throwable $e) {}

    // Donors: distinct donors who have posted listings
    $donorsCount = 0;
    try {
        $donorsCount = (int)$pdo->query('SELECT COUNT(DISTINCT donor_name) FROM listings')->fetchColumn();
    } catch (Throwable $e) {}

    // Partners: use uploaded CSV users file (Target=1) as source of count
    $partnersCount = 0;
    try {
        $csvFile = __DIR__ . '/uploads/users_6911e5f14ab313.55561390.csv';
        if (is_readable($csvFile)) {
            $fh = fopen($csvFile, 'r');
            if ($fh !== false) {
                // Skip header if present
                $first = fgetcsv($fh);
                if ($first !== false) {
                    $isHeader = (isset($first[0]) && strtolower(trim($first[0])) === 'name');
                    if (!$isHeader) {
                        // Not a header; process the first row
                        $target = isset($first[1]) ? trim((string)$first[1]) : '';
                        if ($target === '1') { $partnersCount++; }
                    }
                }
                while (($row = fgetcsv($fh)) !== false) {
                    $target = isset($row[1]) ? trim((string)$row[1]) : '';
                    if ($target === '1') { $partnersCount++; }
                }
                fclose($fh);
            }
        }
        // Fallback to campaigns distinct contributor count if CSV not available
        if ($partnersCount === 0) {
            try {
                $partnersCount = (int)$pdo->query("SELECT COUNT(DISTINCT contributor_name) FROM campaigns WHERE contributor_name IS NOT NULL AND contributor_name <> ''")->fetchColumn();
            } catch (Throwable $e2) {}
        }
    } catch (Throwable $e) {}

    echo json_encode([
        'mealsSaved' => $mealsSaved,
        'donorsCount' => $donorsCount,
        'partnersCount' => $partnersCount,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}