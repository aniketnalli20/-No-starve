<?php
require_once __DIR__ . '/../app.php';
require_admin();

// Handle actions
$message = '';
$errors = [];

try {
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string)($_POST['action'] ?? ''));
    if ($action === 'add_user') {
      $username = trim((string)($_POST['username'] ?? ''));
      $email = trim((string)($_POST['email'] ?? ''));
      $password = (string)($_POST['password'] ?? '');
      $isAdmin = isset($_POST['is_admin']) ? 1 : 0;
      if ($username === '' || $email === '' || $password === '') {
        $errors[] = 'Username, email, and password are required';
      } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $now = gmdate('Y-m-d H:i:s');
        $stmt = $pdo->prepare('INSERT INTO users (username, email, password_hash, created_at, is_admin) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$username, $email, $hash, $now, $isAdmin]);
        $message = 'User added: ' . htmlspecialchars($username);
      }
    } else if ($action === 'delete_user') {
      $uid = (int)($_POST['user_id'] ?? 0);
      if ($uid > 0) {
        $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$uid]);
        $message = 'Deleted user #' . $uid;
      }
    } else if ($action === 'award_coins') {
      $uid = (int)($_POST['user_id'] ?? 0);
      $amount = (int)($_POST['amount'] ?? 0);
      if ($uid > 0 && $amount > 0) {
        award_karma_coins($uid, $amount, 'admin_award', 'admin', (int)($_SESSION['user_id'] ?? 0));
        $message = 'Awarded ' . $amount . ' coins to user #' . $uid;
      }
    } else if ($action === 'create_campaign') {
      $title = trim((string)($_POST['title'] ?? ''));
      $summary = trim((string)($_POST['summary'] ?? ''));
      $area = trim((string)($_POST['area'] ?? ''));
      $status = trim((string)($_POST['status'] ?? 'open'));
      if ($title === '' || $summary === '') {
        $errors[] = 'Title and summary are required';
      } else {
        $now = gmdate('Y-m-d H:i:s');
        $stmt = $pdo->prepare('INSERT INTO campaigns (title, summary, area, status, created_at, user_id) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$title, $summary, ($area !== '' ? $area : null), $status !== '' ? $status : 'open', $now, (int)($_SESSION['user_id'] ?? null)]);
        $message = 'Campaign created: ' . htmlspecialchars($title);
      }
    } else if ($action === 'update_campaign_status') {
      $cid = (int)($_POST['campaign_id'] ?? 0);
      $status = trim((string)($_POST['status'] ?? 'open'));
      if ($cid > 0) {
        $pdo->prepare('UPDATE campaigns SET status = ? WHERE id = ?')->execute([$status, $cid]);
        $message = 'Updated campaign #' . $cid . ' to ' . htmlspecialchars($status);
      }
    } else if ($action === 'delete_campaign') {
      $cid = (int)($_POST['campaign_id'] ?? 0);
      if ($cid > 0) {
        $pdo->prepare('DELETE FROM campaigns WHERE id = ?')->execute([$cid]);
        $message = 'Deleted campaign #' . $cid;
      }
    } else if ($action === 'autogen_users') {
      $n = (int)($_POST['count'] ?? 0);
      if ($n > 0 && $n <= 500) {
        $now = gmdate('Y-m-d H:i:s');
        $ins = $pdo->prepare('INSERT INTO users (username, email, password_hash, created_at) VALUES (?, ?, ?, ?)');
        for ($i = 0; $i < $n; $i++) {
          $name = 'user' . mt_rand(10000, 99999);
          $email = $name . '@example.com';
          $pass = password_hash('password', PASSWORD_DEFAULT);
          $ins->execute([$name, $email, $pass, $now]);
        }
        $message = 'Generated ' . $n . ' users.';
      } else {
        $errors[] = 'Count must be between 1 and 500';
      }
    }
  }
} catch (Throwable $e) {
  $errors[] = 'Error: ' . $e->getMessage();
}

// Fetch lists
$users = [];
$campaigns = [];
$wallets = [];
try {
  $users = $pdo->query('SELECT id, username, email, created_at, is_admin FROM users ORDER BY id DESC LIMIT 200')->fetchAll(PDO::FETCH_ASSOC) ?: [];
  $campaigns = $pdo->query('SELECT id, title, status, area, created_at FROM campaigns ORDER BY id DESC LIMIT 200')->fetchAll(PDO::FETCH_ASSOC) ?: [];
  $wallets = $pdo->query('SELECT w.user_id, u.username, w.balance, w.updated_at FROM karma_wallets w JOIN users u ON u.id = w.user_id ORDER BY w.updated_at DESC LIMIT 200')->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin · No Starve</title>
  <link rel="stylesheet" href="<?= h($BASE_PATH) ?>style.css">
</head>
<body>
  <header class="site-header" role="banner">
    <div class="container header-inner">
      <a href="<?= h($BASE_PATH) ?>index.php#hero" class="brand" aria-label="No Starve home">No Starve</a>
      <nav id="primary-navigation" class="nav-links" role="navigation" aria-label="Primary">
        <a href="<?= h($BASE_PATH) ?>index.php#hero">Home</a>
        <a href="<?= h($BASE_PATH) ?>admin/index.php" class="active">Admin</a>
        <a href="<?= h($BASE_PATH) ?>logout.php">Logout</a>
      </nav>
    </div>
  </header>

  <main class="container">
    <h1>Admin Dashboard</h1>
    <div class="content-grid">
    <?php if (!empty($errors)): ?>
      <div class="card-plain is-highlight" role="alert">
        <ul class="list-clean">
          <?php foreach ($errors as $err): ?><li><?= h($err) ?></li><?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>
    <?php if ($message): ?>
      <div class="card-plain" role="status"><?= h($message) ?></div>
    <?php endif; ?>

    <section class="card-plain card-horizontal" aria-label="Users">
      <h2 class="section-title">Users</h2>
      <form method="post" class="form">
        <input type="hidden" name="action" value="add_user">
        <input name="username" type="text" class="input" placeholder="Username" required>
        <input name="email" type="email" class="input" placeholder="Email" required>
        <input name="password" type="password" class="input" placeholder="Password" required minlength="6">
        <label style="display:inline-flex; align-items:center; gap:6px; margin-top:6px;"><input type="checkbox" name="is_admin" value="1"> Make Admin</label>
        <div class="actions"><button type="submit" class="btn pill">Add User</button></div>
      </form>
      <form method="post" class="form">
        <input type="hidden" name="action" value="autogen_users">
        <input name="count" type="number" class="input" placeholder="Count (max 500)" min="1" max="500" required>
        <div class="actions"><button type="submit" class="btn pill">Auto-generate</button></div>
      </form>
      <div class="listings-grid">
        <div class="card-plain">
          <strong>Users (latest)</strong>
          <ul class="list-clean">
            <?php foreach ($users as $u): ?>
              <li>
                #<?= (int)$u['id'] ?> · <?= h($u['username']) ?> · <?= h($u['email']) ?> <?= ((int)($u['is_admin'] ?? 0) === 1 ? '(admin)' : '') ?>
                <form method="post" style="display:inline;">
                  <input type="hidden" name="action" value="delete_user">
                  <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                  <button type="submit" class="btn pill">Delete</button>
                </form>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
      </div>
    </section>

    <section class="card-plain card-horizontal" aria-label="Campaigns">
      <h2 class="section-title">Campaigns</h2>
      <form method="post" class="form">
        <input type="hidden" name="action" value="create_campaign">
        <input name="title" type="text" class="input" placeholder="Title" required>
        <textarea name="summary" class="input" placeholder="Summary" required></textarea>
        <input name="area" type="text" class="input" placeholder="Area (optional)">
        <select name="status" class="input">
          <option value="open">open</option>
          <option value="draft">draft</option>
          <option value="closed">closed</option>
        </select>
        <div class="actions"><button type="submit" class="btn pill">Create Campaign</button></div>
      </form>
      <div class="listings-grid">
        <div class="card-plain">
          <strong>Campaigns (latest)</strong>
          <ul class="list-clean">
            <?php foreach ($campaigns as $c): ?>
              <li>
                #<?= (int)$c['id'] ?> · <?= h($c['title']) ?> · status: <?= h($c['status']) ?> · <?= h($c['area'] ?? '') ?>
                <form method="post" style="display:inline;">
                  <input type="hidden" name="action" value="update_campaign_status">
                  <input type="hidden" name="campaign_id" value="<?= (int)$c['id'] ?>">
                  <select name="status" class="input" style="display:inline-block; width:auto;">
                    <option value="open">open</option>
                    <option value="draft">draft</option>
                    <option value="closed">closed</option>
                  </select>
                  <button type="submit" class="btn pill">Update</button>
                </form>
                <form method="post" style="display:inline;">
                  <input type="hidden" name="action" value="delete_campaign">
                  <input type="hidden" name="campaign_id" value="<?= (int)$c['id'] ?>">
                  <button type="submit" class="btn pill">Delete</button>
                </form>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
      </div>
    </section>

    <section class="card-plain card-horizontal" aria-label="Rewards">
      <h2 class="section-title">Rewards</h2>
      <form method="post" class="form">
        <input type="hidden" name="action" value="award_coins">
        <input name="user_id" type="number" class="input" placeholder="User ID" required>
        <input name="amount" type="number" class="input" placeholder="Amount" required min="1">
        <div class="actions"><button type="submit" class="btn pill">Award Coins</button></div>
      </form>
      <div class="listings-grid">
        <div class="card-plain">
          <strong>Wallets (latest)</strong>
          <ul class="list-clean">
            <?php foreach ($wallets as $w): ?>
              <li>#<?= (int)$w['user_id'] ?> · <?= h($w['username']) ?> · balance: <?= (int)$w['balance'] ?> · updated: <?= h($w['updated_at']) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      </div>
    </section>

    <section class="card-plain card-horizontal" aria-label="Database Tools">
      <h2 class="section-title">Database Tools</h2>
      <div class="listings-grid">
        <div class="card-plain">
          <strong>Tables</strong>
          <ul class="list-clean">
            <?php
              try {
                $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) ?: [];
              } catch (Throwable $e) { $tables = []; }
              foreach ($tables as $t): ?>
              <li><?= h($t) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      </div>
    </section>
    </div>
  </main>

  <footer class="site-footer">
    <div class="container footer-inner">
      <small>&copy; 2025 No Starve</small>
    </div>
  </footer>
</body>
</html>