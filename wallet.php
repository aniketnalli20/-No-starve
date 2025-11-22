<?php
require_once __DIR__ . '/app.php';
require_wallet_access_or_redirect();

$user = current_user();
if (!$user) { header('Location: ' . $BASE_PATH . 'login.php'); exit; }

$msg = '';
$convFailed = false;
$redeemMsg = '';
$redeemFailed = false;
// Conversion: align wallet with endorsements-based expected earnings
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'convert') {
    try {
        // Expected coin earnings based on endorsements on user's campaigns, 1 coin per 100 endorsements
        $st = $pdo->prepare('SELECT COALESCE(SUM(endorse_campaign), 0) FROM campaigns WHERE user_id = ?');
        $st->execute([(int)$user['id']]);
        $endorseTotal = (int)($st->fetchColumn() ?: 0);
        $expectedCoins = (int)floor($endorseTotal / 100);
        $currentBalance = get_karma_balance((int)$user['id']);
        $delta = $expectedCoins - $currentBalance;
        if ($delta > 0) {
            award_karma_coins((int)$user['id'], $delta, 'conversion', 'user', (int)$user['id']);
            $msg = 'Converted ' . $delta . ' coins to wallet';
        } else {
            $msg = 'No conversion needed';
        }
    } catch (Throwable $e) {
        $msg = 'Conversion failed';
        $convFailed = true;
    }
}

// Redeem coins to paisa (requires 10 lakh coins)
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'redeem') {
    try {
        $res = redeem_karma_to_cash((int)$user['id']);
        if ($res['ok']) {
            $redeemMsg = 'Redeemed ' . (int)$res['coins'] . ' coins → ₹' . number_format(((int)$res['paisa']) / 100, 2);
            $redeemFailed = false;
        } else if ($res['error'] === 'threshold') {
            $redeemMsg = 'Redemption allowed only at 10,00,000 Karma Coins';
            $redeemFailed = true;
        } else {
            $redeemMsg = 'Redemption failed';
            $redeemFailed = true;
        }
    } catch (Throwable $e) {
        $redeemMsg = 'Redemption failed';
        $redeemFailed = true;
    }
}

$balance = get_karma_balance((int)$user['id']);
$redeemable = can_redeem((int)$balance);
$paisa = convert_coins_to_paisa((int)$balance);
$rupees = ((int)$paisa) / 100;
$endorseTotal = 0;
try {
    $st = $pdo->prepare('SELECT COALESCE(SUM(endorse_campaign), 0) FROM campaigns WHERE user_id = ?');
    $st->execute([(int)$user['id']]);
    $endorseTotal = (int)($st->fetchColumn() ?: 0);
} catch (Throwable $e) {}
$expectedCoins = (int)floor($endorseTotal / 100);
$progressPct = max(0, min(100, (int)floor(((float)$balance / 1000000.0) * 100)));

// Load events
$events = [];
try {
    $st = $pdo->prepare('SELECT amount, reason, ref_type, ref_id, created_at FROM karma_events WHERE user_id = ? ORDER BY created_at DESC LIMIT 200');
    $st->execute([(int)$user['id']]);
    $events = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {}

$highCredits = [];
try {
    $pos = array_filter($events, function($ev){ return (int)($ev['amount'] ?? 0) > 0; });
    usort($pos, function($a,$b){ return (int)$b['amount'] <=> (int)$a['amount']; });
    $highCredits = array_slice($pos, 0, 5);
} catch (Throwable $e) {}

$days = 30;
$labels = [];
$values = [];
$map = [];
for ($i = $days - 1; $i >= 0; $i--) {
    $d = gmdate('Y-m-d', strtotime('-' . $i . ' days'));
    $labels[] = $d;
    $map[$d] = 0;
}
foreach ($events as $ev) {
    $amt = (int)($ev['amount'] ?? 0);
    if ($amt <= 0) continue;
    $d = gmdate('Y-m-d', strtotime((string)($ev['created_at'] ?? '')));
    if (isset($map[$d])) { $map[$d] += $amt; }
}
foreach ($labels as $d) { $values[] = (int)$map[$d]; }
$minVal = 0; $maxVal = 0; foreach ($values as $v) { if ($v > $maxVal) $maxVal = $v; }
$points = [];
$W = 720; $H = 140; $P = 16;
for ($i = 0; $i < count($values); $i++) {
    $x = $P + ($i * (($W - 2*$P) / max(1, ($days - 1))));
    $span = max(1, $maxVal - $minVal);
    $y = $H - $P - ((($values[$i] - $minVal) / $span) * ($H - 2*$P));
    $points[] = [$x, $y];
}
$pathD = '';
for ($i = 0; $i < count($points); $i++) { $pathD .= ($i === 0 ? 'M' : 'L') . round($points[$i][0],1) . ' ' . round($points[$i][1],1) . ' '; }

?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Wallet · No Starve</title>
  <link rel="stylesheet" href="<?= h($BASE_PATH) ?>style.css">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,400,0,0" />
</head>
<body class="page-wallet">
  <header class="site-header" role="banner">
    <div class="container header-inner">
      <nav class="navbar navbar-expand-lg navbar-light bg-light" role="navigation" aria-label="Primary">
        <a class="navbar-brand" href="<?= h($BASE_PATH) ?>index.php#hero">No Starve</a>
        <div class="collapse navbar-collapse" id="primary-navbar">
          <ul class="navbar-nav">
            <li class="nav-item"><a class="nav-link" href="<?= h($BASE_PATH) ?>index.php#hero">Home</a></li>
            <li class="nav-item"><a class="nav-link" href="<?= h(is_logged_in() ? ($BASE_PATH . 'profile.php') : ($BASE_PATH . 'login.php?next=profile.php')) ?>">Profile</a></li>
            <li class="nav-item"><a class="nav-link active" href="<?= h($BASE_PATH) ?>wallet.php">Wallet</a></li>
            <li class="nav-item"><a class="nav-link" href="<?= h(is_logged_in() ? ($BASE_PATH . 'kyc.php') : ($BASE_PATH . 'login.php?next=kyc.php')) ?>">KYC</a></li>
            <?php if (is_admin()): ?>
              <li class="nav-item"><a class="nav-link" href="<?= h($BASE_PATH) ?>admin/index.php">Admin Tools</a></li>
            <?php endif; ?>
          </ul>
        </div>
      </nav>
    </div>
  </header>

  <main class="container content-grid" style="padding: var(--content-pad);">
    <section class="card-plain wallet-hero" aria-label="Wallet" style="grid-column: 1 / -1;">
      <div class="wallet-head">
        <div class="wallet-balance">
          <div class="wallet-label"><span class="material-symbols-outlined" aria-hidden="true">savings</span> Balance</div>
          <div class="wallet-value"><?= h(format_compact_number((int)$balance)) ?></div>
          <div class="wallet-sub">₹<?= h(number_format($rupees, 2)) ?></div>
        </div>
        <div class="wallet-actions">
          <form method="post" action="<?= h($BASE_PATH) ?>wallet.php">
            <input type="hidden" name="action" value="convert">
            <button type="submit" class="btn pill">Convert</button>
          </form>
          <form method="post" action="<?= h($BASE_PATH) ?>wallet.php">
            <input type="hidden" name="action" value="redeem">
            <button type="submit" class="btn pill"<?= $redeemable ? '' : ' disabled' ?>>Redeem</button>
          </form>
        </div>
      </div>
      <?php if ($msg !== ''): ?>
        <?php if ($convFailed): ?><div class="alert error error-wobble" role="alert"><?= h($msg) ?></div>
        <?php else: ?><div class="alert success" role="status"><?= h($msg) ?></div><?php endif; ?>
      <?php endif; ?>
      <?php if ($redeemMsg !== ''): ?>
        <?php if ($redeemFailed): ?><div class="alert error error-wobble" role="alert"><?= h($redeemMsg) ?></div>
        <?php else: ?><div class="alert success" role="status"><?= h($redeemMsg) ?></div><?php endif; ?>
      <?php endif; ?>
      <div class="wallet-progress">
        <div class="progress-head">
          <div>Redeem Threshold</div>
          <div><?= h(format_compact_number((int)$balance)) ?> / 1m</div>
        </div>
        <div class="progress-track"><div class="progress-bar" style="width: <?= (int)$progressPct ?>%"></div></div>
        <div class="progress-foot">Redemption unlocks at 1,000,000 Karma Coins</div>
      </div>
    </section>

    <section class="card-plain" aria-label="Overview">
      <div class="dash-cards">
        <div class="metric-card"><div class="metric-value"><?= h(format_compact_number((int)$endorseTotal)) ?></div><div class="metric-label">Endorsements</div></div>
        <div class="metric-card"><div class="metric-value"><?= h(format_compact_number((int)$expectedCoins)) ?></div><div class="metric-label">Expected Coins</div></div>
        <div class="metric-card"><div class="metric-value"><?= $redeemable ? 'Ready' : 'Locked' ?></div><div class="metric-label">Redeem Status</div></div>
      </div>
      <div class="muted" style="margin-top:8px;">Rate: 100 Karma Coins = ₹0.01</div>
    </section>

    <section class="card-plain chart-card" aria-label="Contributions Chart" style="grid-column: 1 / -1;">
      <div class="section-title">High Paying Contributions (last 30 days)</div>
      <div class="wallet-chart">
        <svg viewBox="0 0 <?= (int)$W ?> <?= (int)$H ?>" preserveAspectRatio="none" role="img" aria-label="Contributions over time" style="width:100%; height:180px;">
          <path d="<?= h(trim($pathD)) ?>" fill="none" stroke="#1a7aff" stroke-width="2" />
        </svg>
      </div>
      <div class="muted" style="margin-top:6px;">Total credits: <?= h(format_compact_number((int)array_sum($values))) ?></div>
      <div style="display:flex; align-items:center; justify-content:space-between; margin-top:6px; color:var(--muted);">
        <span><?= h($labels[0]) ?></span><span><?= h($labels[count($labels)-1]) ?></span>
      </div>
    </section>

    <section class="card-plain" aria-label="Top Contributions">
      <h2 class="section-title">Top Credits</h2>
      <?php if (!empty($highCredits)): ?>
        <div class="table" role="table" aria-label="Top credits">
          <?php foreach ($highCredits as $ev): ?>
            <div class="table-row wallet-row" role="row">
              <div class="cell time">&nbsp;<?= h(date('Y-m-d', strtotime((string)($ev['created_at'] ?? '')))) ?></div>
              <div class="cell reason"><?= h((string)($ev['reason'] ?? '')) ?></div>
              <div class="cell amount pos"><?= h(format_compact_number((int)$ev['amount'])) ?></div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="muted">No high credits yet.</div>
      <?php endif; ?>
    </section>

    <section class="card-plain" aria-label="History">
      <h2 class="section-title">History</h2>
      <?php if (!empty($events)): ?>
        <div class="table" role="table" aria-label="Wallet events">
          <?php foreach ($events as $ev): ?>
            <?php $amt = (int)$ev['amount']; $neg = ($amt < 0); ?>
            <div class="table-row wallet-row" role="row">
              <div class="cell time"><?= h(date('Y-m-d H:i', strtotime($ev['created_at']))) ?></div>
              <div class="cell reason"><?= h((string)($ev['reason'] ?? '')) ?></div>
              <div class="cell amount<?= $neg ? ' neg' : ' pos' ?>"><?php if ($neg): ?>−<?php endif; ?><?= h(format_compact_number((int)abs($amt))) ?></div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="muted">No events yet.</div>
      <?php endif; ?>
    </section>
  </main>
</body>
</html>