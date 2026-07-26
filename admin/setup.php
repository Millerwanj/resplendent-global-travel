<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

if (admin_is_configured()) {
    header('Location: login.php', true, 303);
    exit;
}

$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $token = trim((string)($_POST['setup_token'] ?? ''));
    $name = admin_text($_POST['display_name'] ?? '', 80);
    $email = filter_var(trim((string)($_POST['email'] ?? '')), FILTER_VALIDATE_EMAIL);
    $password = (string)($_POST['password'] ?? '');
    $confirm = (string)($_POST['password_confirm'] ?? '');

    if (!hash_equals(RGTS_ADMIN_SETUP_TOKEN_HASH, hash('sha256', $token))) {
        $error = 'The setup token is not correct.';
    } elseif ($name === '' || !$email) {
        $error = 'Enter a name and a valid email address.';
    } elseif (strlen($password) < 12 || !preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/\d/', $password)) {
        $error = 'Use at least 12 characters with upper-case, lower-case and a number.';
    } elseif ($password !== $confirm) {
        $error = 'The passwords do not match.';
    } else {
        $config = [
            'display_name' => $name,
            'email' => strtolower((string)$email),
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'created_at' => gmdate('c'),
        ];
        $contents = "<?php\nreturn " . var_export($config, true) . ";\n";
        $path = admin_config_path();
        $directory = dirname($path);
        if ((!is_dir($directory) && !@mkdir($directory, 0750, true)) || @file_put_contents($path, $contents, LOCK_EX) === false) {
            $error = 'The private admin configuration could not be written. Please follow the manual configuration note in ADMIN-SETUP.md.';
        } else {
            @chmod($path, 0640);
            session_regenerate_id(true);
            $_SESSION['rgts_admin_authenticated'] = true;
            $_SESSION['rgts_admin_last_seen'] = time();
            $_SESSION['rgts_admin_email'] = strtolower((string)$email);
            admin_flash('success', 'Admin access is configured. Welcome to Resplendent Operations.');
            header('Location: index.php', true, 303);
            exit;
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="robots" content="noindex,nofollow,noarchive">
    <title>Secure Setup | Resplendent Operations</title>
    <link rel="icon" href="../favicon.ico">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/admin.css?v=9.6.3">
</head>
<body class="auth-page">
<main class="auth-card">
    <img class="auth-logo" src="../assets/images/logo-r.png" alt="Resplendent Global Travel Solutions" width="74" height="74">
    <p class="eyebrow">Private Operations Suite</p>
    <h1>Secure first-time setup</h1>
    <p>Create the administrator account for this deployment. You will only complete this step once.</p>
    <?php if ($error !== ''): ?><div class="admin-alert error" role="alert"><?= admin_e($error) ?></div><?php endif; ?>
    <form method="post" class="admin-form auth-form">
        <label>Setup token<input type="password" name="setup_token" required autocomplete="one-time-code"></label>
        <label>Your name<input type="text" name="display_name" required autocomplete="name" value="<?= admin_e($_POST['display_name'] ?? '') ?>"></label>
        <label>Admin email<input type="email" name="email" required autocomplete="username" value="<?= admin_e($_POST['email'] ?? '') ?>"></label>
        <label>Password<input type="password" name="password" required minlength="12" autocomplete="new-password"></label>
        <label>Confirm password<input type="password" name="password_confirm" required minlength="12" autocomplete="new-password"></label>
        <button class="admin-button primary" type="submit">Create Secure Access</button>
    </form>
    <p class="auth-help">Your setup token is in the private <strong>ADMIN-SETUP.md</strong> file included with the production package.</p>
</main>
</body>
</html>
