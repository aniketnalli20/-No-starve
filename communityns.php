<?php
require_once __DIR__ . '/app.php';

// Endorsement actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'endorse') {
    if (!is_logged_in()) {
        header('Location: ' . $BASE_PATH . 'login.php?next=communityns.php');
        exit;
    }
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $type = isset($_POST['type']) ? trim((string)$_POST['type']) : '';
    if ($id > 0 && in_array($type, ['campaign','contributor'], true)) {
        $col = $type === 'campaign' ? 'endorse_campaign' : 'endorse_contributor';
        // Increment counters on campaign
        $stmt = $pdo->prepare("UPDATE campaigns SET $col = COALESCE($col,0) + 1 WHERE id = ?");
        $stmt->execute([$id]);

        // Best-effort: record the endorsement event
        try {
            // Fetch contributor_name to store alongside the endorsement
            $cnStmt = $pdo->prepare('SELECT contributor_name FROM campaigns WHERE id = ?');
            $cnStmt->execute([$id]);
            $row = $cnStmt->fetch(PDO::FETCH_ASSOC) ?: [];
            $contributorName = isset($row['contributor_name']) ? (string)$row['contributor_name'] : null;

            $ip = isset($_SERVER['HTTP_X_FORWARDED_FOR']) && $_SERVER['HTTP_X_FORWARDED_FOR'] !== ''
                ? explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]
                : (isset($_SERVER['REMOTE_ADDR']) ? (string)$_SERVER['REMOTE_ADDR'] : null);
            $ua = isset($_SERVER['HTTP_USER_AGENT']) ? (string)$_SERVER['HTTP_USER_AGENT'] : null;

            $ins = $pdo->prepare('INSERT INTO endorsements (campaign_id, kind, contributor_name, ip, user_agent, created_at) VALUES (?, ?, ?, ?, ?, NOW())');
            $ins->execute([$id, $type, $contributorName, $ip, $ua]);
        } catch (Throwable $e) {
            // Swallow errors to avoid breaking UX if table is missing or write fails
        }
    }
    header('Location: /communityns.php#campaign-' . $id);
    exit;
}

// Filters
$filterCommunity = isset($_GET['community']) ? trim((string)$_GET['community']) : '';

// Distinct communities for explore
$communities = [];
try {
    $res = $pdo->query('SELECT DISTINCT community FROM campaigns WHERE community IS NOT NULL AND community <> "" ORDER BY community');
    $communities = $res->fetchAll(PDO::FETCH_COLUMN) ?: [];
} catch (Throwable $e) {}

// Fetch campaigns
$query = 'SELECT id, title, summary, contributor_name, community, crowd_size, location, image_url, closing_time, endorse_campaign, endorse_contributor, created_at, latitude, longitude FROM campaigns';
$params = [];
if ($filterCommunity !== '') {
    $query .= ' WHERE community = ?';
    $params[] = $filterCommunity;
}
$query .= ' ORDER BY id DESC LIMIT 200';
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$campaigns = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Community · No Starve</title>
    <link rel="stylesheet" href="<?= h($BASE_PATH) ?>style.css">
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="<?= h($BASE_PATH) ?>uploads/favicon.png" sizes="32x32">
    <link rel="apple-touch-icon" href="<?= h($BASE_PATH) ?>uploads/favicon.png">
</head>
<body>
    <header class="site-header" role="banner">
        <div class="container header-inner">
            <a href="<?= h($BASE_PATH) ?>index.php#hero" class="brand" aria-label="No Starve home">No Starve</a>
            <?php $currentPath = basename($_SERVER['SCRIPT_NAME'] ?? ''); ?>
            <nav id="primary-navigation" class="nav-links" role="navigation" aria-label="Primary">
                <a href="<?= h($BASE_PATH) ?>index.php#hero"<?= $currentPath === 'index.php' ? ' class="active"' : '' ?>>Home</a>
                <a href="<?= h(is_logged_in() ? ($BASE_PATH . 'create_campaign.php') : ($BASE_PATH . 'login.php?next=create_campaign.php')) ?>"<?= $currentPath === 'create_campaign.php' ? ' class="active"' : '' ?>>Create Campaign</a>
                <a href="<?= h($BASE_PATH) ?>communityns.php"<?= $currentPath === 'communityns.php' ? ' class="active"' : '' ?>>Community</a>
                <?php if (is_logged_in()): ?>
                    <a href="<?= h($BASE_PATH) ?>logout.php">Logout</a>
                <?php else: ?>
                    <a href="<?= h($BASE_PATH) ?>login.php"<?= $currentPath === 'login.php' ? ' class="active"' : '' ?>>Login</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>

    <main class="container" style="max-width: var(--content-max); padding: var(--content-pad);">
        <section class="card-plain">
            <h2 class="section-title">Explore Community</h2>
            <p class="section-subtitle muted">Showing <?= h((string)count($campaigns)) ?> campaigns<?= $filterCommunity !== '' ? (' in ' . h($filterCommunity)) : '' ?></p>
            <form method="get" class="filter-bar" aria-label="Filter campaigns">
                <label for="community" class="sr-only">Filter by Community</label>
                <div class="control-group">
                    <span class="control-label">Filter by Community</span>
                    <select id="community" name="community" class="select">
                        <option value="">All</option>
                        <?php foreach ($communities as $c): ?>
                            <option value="<?= h($c) ?>" <?= $filterCommunity === $c ? 'selected' : '' ?>><?= h($c) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="actions">
                    <button class="btn secondary" type="submit">Apply</button>
                    <?php if ($filterCommunity !== ''): ?>
                        <a class="btn" href="<?= h($BASE_PATH) ?>communityns.php">Reset</a>
                    <?php endif; ?>
                </div>
            </form>
        </section>

        <section class="cards-grid card-plain card-fullbleed" id="community-campaigns">
            <?php if (empty($campaigns)): ?>
                <div class="card-plain"><p>No campaigns yet.</p></div>
            <?php else: ?>
                <?php foreach ($campaigns as $c): ?>
                    <?php $initial = strtoupper(substr(trim((string)($c['contributor_name'] ?? 'U')), 0, 1)); ?>
                    <?php 
                        $handleBase = strtolower(preg_replace('/[^a-z0-9]+/i', '', (string)($c['contributor_name'] ?: 'user')));
                        $handle = $handleBase !== '' ? $handleBase : 'user';
                    ?>
                    <div class="tweet-card" id="campaign-<?= h((string)$c['id']) ?>">
                        <div class="tweet-header">
                            <div class="tweet-avatar" aria-hidden="true"><?= h($initial) ?></div>
                            <div class="tweet-meta">
                                <div>
                                    <span class="tweet-name"><?= h($c['contributor_name'] ?: 'Unknown') ?></span>
                                    <span class="tweet-handle">@<?= h($handle) ?></span>
                                </div>
                                <div class="muted">Uploaded <?= h(time_ago($c['created_at'])) ?></div>
                            </div>
                        </div>
                        <div class="tweet-body">
                            <strong><?= h($c['title'] ?: ('Campaign #' . $c['id'])) ?></strong>
                            <?php $summaryText = trim((string)$c['summary']); ?>
                            <?php if ($summaryText !== ''): ?>
                                <div><?= h($summaryText) ?></div>
                            <?php endif; ?>
                            <?php 
                              $metaLine = 'Crowd Size: ' . (string)$c['crowd_size'] . ' · Location: ' . (string)$c['location'] . ' · Community: ' . ((string)($c['community'] ?: 'General'));
                              $hasDuplicateMeta = stripos($summaryText, 'crowd') !== false || stripos($summaryText, 'location') !== false;
                            ?>
                            <?php if (!$hasDuplicateMeta): ?>
                                <div class="muted" style="margin-top:4px;"><?= h($metaLine) ?></div>
                            <?php endif; ?>
                            <?php 
                              $closingTs = strtotime((string)$c['closing_time']);
                              $nowTs = time();
                              $diff = $closingTs !== false ? ($closingTs - $nowTs) : null;
                              $closingCls = 'ok';
                              $closingText = (string)$c['closing_time'];
                              if ($diff !== null) {
                                if ($diff <= 0) { $closingCls = 'closed'; }
                                else if ($diff <= 2 * 3600) { $closingCls = 'soon'; }
                              }
                              if ($closingTs !== false) { $closingText = date('M j, Y g:i A', $closingTs); }
                            ?>
                            <div class="closing-indicator <?= h($closingCls) ?>" style="margin-top:4px;"><strong>Closing:</strong> <?= h($closingText) ?></div>
                        </div>
                        <?php if (!empty($c['image_url'])): ?>
                            <div class="tweet-media">
                                <img src="<?= h($c['image_url']) ?>" alt="Campaign image" />
                            </div>
                        <?php endif; ?>
                        <div class="tweet-actions">
                            <?php
                            $hasCoords = isset($c['latitude']) && $c['latitude'] !== null && isset($c['longitude']) && $c['longitude'] !== null;
                            $mapUrl = $hasCoords
                                ? ('https://www.google.com/maps?q=' . rawurlencode($c['latitude'] . ',' . $c['longitude']))
                                : ('https://www.google.com/maps/search/?api=1&query=' . rawurlencode((string)$c['location']));
                            ?>
                            <a href="<?= h($mapUrl) ?>" target="_blank" rel="noopener">Location</a>
                            <form method="post">
                                <input type="hidden" name="action" value="endorse"/>
                                <input type="hidden" name="type" value="campaign"/>
                                <input type="hidden" name="id" value="<?= h((string)$c['id']) ?>"/>
                                <button type="submit">Campaign (<?= h((string)($c['endorse_campaign'] ?? 0)) ?>)</button>
                            </form>
                            <form method="post">
                                <input type="hidden" name="action" value="endorse"/>
                                <input type="hidden" name="type" value="contributor"/>
                                <input type="hidden" name="id" value="<?= h((string)$c['id']) ?>"/>
                                <button type="submit">Contributor (<?= h((string)($c['endorse_contributor'] ?? 0)) ?>)</button>
                            </form>
                            <button type="button" onclick="shareCampaign(<?= h((string)$c['id']) ?>)">Share</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>
    </main>

    <script>
    function shareCampaign(id){
        const url = window.location.origin + (window.BASE_PATH || '<?= h($BASE_PATH) ?>') + 'communityns.php#campaign-' + id;
        const title = 'No Starve Campaign #' + id;
        if (navigator.share) {
            navigator.share({ title: title, text: 'Support this campaign', url: url }).catch(function(){});
        } else {
            navigator.clipboard && navigator.clipboard.writeText(url).then(function(){
                alert('Share link copied to clipboard');
            }).catch(function(){ window.prompt('Copy this link', url); });
        }
    }
    </script>
    <script>
      window.BASE_PATH = '<?= h($BASE_PATH) ?>';
    </script>

    <footer class="site-footer">
        <div class="container footer-inner">
            <small>&copy; 2025 No Starve</small>
        </div>
    </footer>
</body>
</html>