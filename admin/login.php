<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

if (!admin_is_configured()) {
    header('Location: setup.php', true, 303);
    exit;
}
if (admin_is_authenticated()) {
    header('Location: index.php', true, 303);
    exit;
}

$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $waitUntil = (int)($_SESSION['rgts_login_wait_until'] ?? 0);
    if ($waitUntil > time()) {
        $error = 'Too many attempts. Please wait briefly and try again.';
    } elseif (!admin_verify_csrf($_POST['csrf'] ?? null)) {
        $error = 'The secure session expired. Please refresh and try again.';
    } else {
        $config = admin_config() ?? [];
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        $password = (string)($_POST['password'] ?? '');
        $valid = hash_equals(strtolower((string)($config['email'] ?? '')), $email)
            && password_verify($password, (string)($config['password_hash'] ?? ''));

        if ($valid) {
            session_regenerate_id(true);
            $_SESSION['rgts_admin_authenticated'] = true;
            $_SESSION['rgts_admin_last_seen'] = time();
            $_SESSION['rgts_admin_email'] = $email;
            $_SESSION['rgts_login_attempts'] = 0;
            $returnTo = (string)($_SESSION['rgts_admin_return_to'] ?? 'index.php');
            unset($_SESSION['rgts_admin_return_to']);
            if (!preg_match('/^[a-z0-9-]+\.php(?:\?[a-z0-9=&_-]+)?$/i', $returnTo)) $returnTo = 'index.php';
            header('Location: ' . $returnTo, true, 303);
            exit;
        }

        $attempts = ((int)($_SESSION['rgts_login_attempts'] ?? 0)) + 1;
        $_SESSION['rgts_login_attempts'] = $attempts;
        if ($attempts >= 5) {
            $_SESSION['rgts_login_wait_until'] = time() + 300;
            $_SESSION['rgts_login_attempts'] = 0;
        }
        usleep(350000);
        $error = 'The email or password is not correct.';
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="robots" content="noindex,nofollow,noarchive">
    <title>Sign In | Resplendent Operations</title>
    <link rel="icon" href="../favicon.ico">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/admin.css?v=9.6.1">
</head>
<body class="auth-page">
<main class="auth-card">
    <img class="auth-logo" src="../assets/images/logo-r.png" alt="Resplendent Global Travel Solutions" width="74" height="74">
    <p class="eyebrow">Private Operations Suite</p>
    <h1>Welcome back.</h1>
    <p>Sign in to manage proposals, quotations and client progress.</p>
    <?php if ($error !== ''): ?><div class="admin-alert error" role="alert"><?= admin_e($error) ?></div><?php endif; ?>
    <form method="post" class="admin-form auth-form">
        <input type="hidden" name="csrf" value="<?= admin_e(admin_csrf_token()) ?>">
        <label>Admin email<input type="email" name="email" required autocomplete="username"></label>
        <label>Password<input type="password" name="password" required autocomplete="current-password"></label>
        <button class="admin-button primary" type="submit">Sign In</button>
    </form>
    <a class="auth-back" href="../index.html">Return to website</a>
</main>
</body>
</html>
