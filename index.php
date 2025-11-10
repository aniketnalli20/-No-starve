<?php
require_once __DIR__ . '/app.php';

$message = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reporter_name = trim($_POST['reporter_name'] ?? '');
    $contact = trim($_POST['contact'] ?? '');
    $item = trim($_POST['item'] ?? '');
    $quantity = trim($_POST['quantity'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $location = trim($_POST['location'] ?? '');

    if ($reporter_name === '') $errors[] = 'Your name is required.';
    if ($item === '') $errors[] = 'Item name is required.';
    if ($quantity === '') $errors[] = 'Quantity is required.';
    if ($category === '') $errors[] = 'Category is required.';

    if (!$errors) {
        global $pdo;
        $stmt = $pdo->prepare('INSERT INTO reports (reporter_name, contact, item, quantity, category, location, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $reporter_name,
            $contact,
            $item,
            $quantity,
            $category,
            $location,
            'pending',
            date('c')
        ]);
        $message = 'Thanks! Your report has been submitted.';
    }
}

global $pdo;
$recent = $pdo->query('SELECT id, reporter_name, item, quantity, category, location, status, created_at FROM reports ORDER BY id DESC LIMIT 10')->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Food Waste Management</title>
    <link rel="stylesheet" href="/style.css" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
</head>
<body>
    <header class="site-header">
        <div class="wrap">
            <h1>Food Waste Management</h1>
            <p class="tagline">Report, track, and reduce food waste.</p>
        </div>
    </header>

    <main class="container">
        <section class="card">
            <h2>Report Food Waste</h2>
            <?php if ($message): ?>
                <div class="alert success"><?= h($message) ?></div>
            <?php endif; ?>
            <?php if ($errors): ?>
                <div class="alert error">
                    <?php foreach ($errors as $err): ?>
                        <div><?= h($err) ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <form method="post" class="form-grid" autocomplete="off">
                <div class="form-field">
                    <label for="reporter_name">Your Name*</label>
                    <input type="text" id="reporter_name" name="reporter_name" required />
                </div>
                <div class="form-field">
                    <label for="contact">Contact (email or phone)</label>
                    <input type="text" id="contact" name="contact" />
                </div>
                <div class="form-field">
                    <label for="item">Item*</label>
                    <input type="text" id="item" name="item" required />
                </div>
                <div class="form-field">
                    <label for="quantity">Quantity*</label>
                    <input type="text" id="quantity" name="quantity" placeholder="e.g., 5 kg, 20 portions" required />
                </div>
                <div class="form-field">
                    <label for="category">Category*</label>
                    <select id="category" name="category" required>
                        <option value="">Select...</option>
                        <option value="Perishable">Perishable</option>
                        <option value="Non-perishable">Non-perishable</option>
                        <option value="Cooked">Cooked</option>
                        <option value="Raw">Raw</option>
                    </select>
                </div>
                <div class="form-field full">
                    <label for="location">Location</label>
                    <input type="text" id="location" name="location" placeholder="Pickup address or area" />
                </div>
                <div class="actions">
                    <button type="submit">Submit Report</button>
                </div>
            </form>
        </section>

        <section class="card">
            <h2>Recent Reports</h2>
            <?php if (!$recent): ?>
                <p class="muted">No reports yet. Be the first to submit.</p>
            <?php else: ?>
                <ul class="reports">
                    <?php foreach ($recent as $r): ?>
                        <li class="report">
                            <div class="report-main">
                                <strong><?= h($r['item']) ?></strong>
                                <span class="chip"><?= h($r['category']) ?></span>
                                <span class="muted"><?= h($r['quantity']) ?></span>
                            </div>
                            <div class="report-meta">
                                <span>By <?= h($r['reporter_name']) ?></span>
                                <?php if ($r['location']): ?>
                                    <span>• <?= h($r['location']) ?></span>
                                <?php endif; ?>
                                <span>• <?= h(time_ago($r['created_at'])) ?></span>
                                <span class="status <?= h(strtolower($r['status'])) ?>"><?= h($r['status']) ?></span>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </main>

    <footer class="site-footer">
        <div class="wrap">
            <small>&copy; <?= date('Y') ?> Food Waste Management</small>
        </div>
    </footer>
</body>
</html>