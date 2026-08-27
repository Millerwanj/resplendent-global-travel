<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

function admin_page_header(string $title, string $active = ''): void
{
    $config = admin_config() ?? [];
    $displayName = (string)($config['display_name'] ?? 'Resplendent Team');
    $flash = admin_take_flash();
    ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="robots" content="noindex,nofollow,noarchive">
    <meta name="theme-color" content="#102f32">
    <title><?= admin_e($title) ?> | Resplendent Operations</title>
    <link rel="icon" href="../favicon.ico">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/admin.css?v=9.7.0">
</head>
<body class="admin-shell">
<a class="skip-link" href="#admin-main">Skip to main content</a>
<aside class="admin-sidebar" id="admin-sidebar">
    <a class="admin-brand" href="index.php" aria-label="Resplendent Operations home">
        <img src="../assets/images/logo-r.png" alt="" width="52" height="52">
        <span><strong>RESPLENDENT</strong><small>OPERATIONS</small></span>
    </a>
    <nav aria-label="Operations navigation">
        <a class="<?= $active === 'dashboard' ? 'active' : '' ?>" href="index.php">Overview</a>
        <a class="<?= $active === 'proposal' ? 'active' : '' ?>" href="proposal.php">Proposal Generator</a>
        <a class="<?= $active === 'quotation' ? 'active' : '' ?>" href="quotation.php">Quotation Generator</a>
        <a class="<?= $active === 'invoice' ? 'active' : '' ?>" href="invoice.php">Invoice Generator</a>
        <a class="<?= $active === 'workflow' ? 'active' : '' ?>" href="workflow.php">Client Workflow</a>
        <a class="<?= $active === 'settings' ? 'active' : '' ?>" href="settings.php">Payment Settings</a>
        <a class="<?= $active === 'esim' ? 'active' : '' ?>" href="esim-sandbox.php">eSIM Sandbox</a>
    </nav>
    <div class="admin-sidebar-foot">
        <span>Signed in as</span>
        <strong><?= admin_e($displayName) ?></strong>
        <a href="logout.php">Sign out</a>
    </div>
</aside>
<div class="admin-content">
    <header class="admin-topbar">
        <button class="sidebar-toggle" type="button" data-sidebar-toggle aria-controls="admin-sidebar" aria-expanded="false">Menu</button>
        <div><span>Client Engagement Suite</span><strong>v9.7.0 Production</strong></div>
    </header>
    <main id="admin-main" class="admin-main">
        <?php if ($flash): ?>
            <div class="admin-alert <?= admin_e($flash['type']) ?>" role="status"><?= admin_e($flash['message']) ?></div>
        <?php endif; ?>
        <div class="admin-page-heading">
            <p class="eyebrow">Resplendent Operations</p>
            <h1><?= admin_e($title) ?></h1>
        </div>
<?php
}

function admin_page_footer(array $scripts = []): void
{
    ?>
    </main>
</div>
<script src="assets/admin.js?v=9.7.0" defer></script>
<?php foreach ($scripts as $script): ?>
<script src="<?= admin_e($script) ?>?v=9.7.0" defer></script>
<?php endforeach; ?>
</body>
</html>
<?php
}
