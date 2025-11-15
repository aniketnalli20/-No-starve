<?php
require_once __DIR__ . '/app.php';

$next = '';
if (isset($_GET['next'])) {
    $next = trim((string)$_GET['next']);
} elseif (isset($_POST['next'])) {
    $next = trim((string)$_POST['next']);
}

$errors = [];
$message = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string)($_POST['username'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $confirm = (string)($_POST['confirm'] ?? '');
    $phone = trim((string)($_POST['phone'] ?? ''));
    $address = trim((string)($_POST['address'] ?? ''));

    if ($password !== $confirm) {
        $errors[] = 'Passwords do not match';
    }

    if (!$errors) {
        try {
            $userId = register_user($username, $email, $password, $phone !== '' ? $phone : null, $address !== '' ? $address : null);
            $_SESSION['user_id'] = $userId;
            // Redirect after successful registration
            $dest = 'profile.php';
            if ($next !== '' && preg_match('/^[A-Za-z0-9_\-]+(\.php)?(\?.*)?$/', $next)) {
                $dest = $next;
            }
            header('Location: ' . $BASE_PATH . $dest);
            exit;
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Register · No Starve</title>
    <link rel="stylesheet" href="<?= h($BASE_PATH) ?>style.css">
</head>
<body class="page-login">
    <header class="site-header" role="banner">
        <div class="container header-inner">
            <a href="<?= h($BASE_PATH) ?>index.php#hero" class="brand" aria-label="No Starve home">No Starve</a>
            <?php $currentPath = basename($_SERVER['SCRIPT_NAME'] ?? ''); ?>
            <nav id="primary-navigation" class="nav-links" role="navigation" aria-label="Primary">
                <a href="<?= h($BASE_PATH) ?>index.php#hero"<?= $currentPath === 'index.php' ? ' class="active"' : '' ?>>Home</a>
                <a href="<?= h(is_logged_in() ? ($BASE_PATH . 'create_campaign.php') : ($BASE_PATH . 'login.php?next=create_campaign.php')) ?>"<?= $currentPath === 'create_campaign.php' ? ' class="active"' : '' ?>>Create Campaign</a>
                <a href="<?= h(is_logged_in() ? ($BASE_PATH . 'profile.php') : ($BASE_PATH . 'login.php?next=profile.php')) ?>"<?= $currentPath === 'profile.php' ? ' class="active"' : '' ?>>Profile</a>
                <?php if (is_logged_in()): ?>
                    <a href="<?= h($BASE_PATH) ?>logout.php">Logout</a>
                <?php else: ?>
                    <a href="<?= h($BASE_PATH) ?>login.php"<?= $currentPath === 'login.php' ? ' class="active"' : '' ?>>Login</a>
                    <a href="<?= h($BASE_PATH) ?>register.php"<?= $currentPath === 'register.php' ? ' class="active"' : '' ?>>Register</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>

    <main class="container login-page" style="max-width: var(--content-max); padding: var(--content-pad);">
        <div class="container" style="max-width: 420px;">
            <div class="heading">Create Your Account</div>
            <?php if (!empty($errors)): ?>
                <div class="alert error" role="alert" style="margin-top:12px;">
                    <strong>Error:</strong>
                    <ul class="list-clean">
                        <?php foreach ($errors as $err): ?>
                            <li><?= h($err) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            <form class="form" method="post" action="<?= h($BASE_PATH) ?>register.php">
                <?php if ($next !== ''): ?>
                    <input type="hidden" name="next" value="<?= h($next) ?>">
                <?php endif; ?>
                <input placeholder="Username" id="username" name="username" type="text" class="input" required />
                <input placeholder="E-mail" id="email" name="email" type="email" class="input" required />
                <input placeholder="Password" id="password" name="password" type="password" class="input" required minlength="6" />
                <input placeholder="Confirm Password" id="confirm" name="confirm" type="password" class="input" required minlength="6" />
                <input placeholder="Phone (optional)" id="phone" name="phone" type="text" class="input" pattern="[0-9+\-\s]{7,30}" />
                <textarea placeholder="Address (optional)" id="address" name="address" class="input" rows="3"></textarea>
                <button type="submit" class="login-button">Register</button>
            </form>
        </div>
    </main>

    <footer class="site-footer">
        <div class="container footer-inner">
            <small>&copy; 2025 No Starve</small>
        </div>
    </footer>
</body>
</html>