<?php
declare(strict_types=1);

const RGTS_ADMIN_RELEASE = '9.7.0';
const RGTS_ADMIN_SETUP_TOKEN_HASH = '28d113a2884fed0f8918adc3f519ea796ce3fa0aefb6d9d3b631f47845ed5e46';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('RGTS_ADMIN');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/admin',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
header('Cache-Control: no-store, private');
header('X-Robots-Tag: noindex, nofollow, noarchive');

require_once dirname(__DIR__, 2) . '/includes/OperationsStore.php';

function admin_config_path(): string
{
    $configured = trim((string)(getenv('RGTS_ADMIN_CONFIG') ?: ''));
    return $configured !== '' ? $configured : dirname(__DIR__, 3) . '/rgts-admin-config.php';
}

/** @return array<string,mixed>|null */
function admin_config(): ?array
{
    $path = admin_config_path();
    if (!is_file($path)) return null;
    $config = require $path;
    return is_array($config) ? $config : null;
}

function admin_is_configured(): bool
{
    $config = admin_config();
    return is_array($config)
        && filter_var((string)($config['email'] ?? ''), FILTER_VALIDATE_EMAIL)
        && str_starts_with((string)($config['password_hash'] ?? ''), '$');
}

function admin_is_authenticated(): bool
{
    return !empty($_SESSION['rgts_admin_authenticated'])
        && !empty($_SESSION['rgts_admin_last_seen'])
        && (time() - (int)$_SESSION['rgts_admin_last_seen']) < 7200;
}

function admin_require_auth(): void
{
    if (!admin_is_configured()) {
        header('Location: setup.php', true, 303);
        exit;
    }
    if (!admin_is_authenticated()) {
        $_SESSION['rgts_admin_return_to'] = basename((string)($_SERVER['REQUEST_URI'] ?? 'index.php'));
        header('Location: login.php', true, 303);
        exit;
    }
    $_SESSION['rgts_admin_last_seen'] = time();
}

function admin_csrf_token(): string
{
    if (empty($_SESSION['rgts_admin_csrf'])) {
        $_SESSION['rgts_admin_csrf'] = bin2hex(random_bytes(24));
    }
    return (string)$_SESSION['rgts_admin_csrf'];
}

function admin_verify_csrf(?string $token): bool
{
    return is_string($token)
        && isset($_SESSION['rgts_admin_csrf'])
        && hash_equals((string)$_SESSION['rgts_admin_csrf'], $token);
}

function admin_e(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function admin_text(mixed $value, int $max = 5000): string
{
    $value = trim(strip_tags((string)$value));
    return function_exists('mb_substr') ? mb_substr($value, 0, $max) : substr($value, 0, $max);
}

function admin_store(): OperationsStore
{
    static $store = null;
    if (!$store instanceof OperationsStore) $store = new OperationsStore();
    return $store;
}

function admin_esimcard_config_path(): string
{
    $configured = trim((string)(getenv('RGTS_ESIMCARD_CONFIG') ?: ''));
    return $configured !== '' ? $configured : dirname(__DIR__, 3) . '/rgts-esimcard-config.php';
}

/** @return array<string,mixed>|null */
function admin_esimcard_config(): ?array
{
    $path = admin_esimcard_config_path();
    if (!is_file($path)) return null;
    $config = require $path;
    return is_array($config) ? $config : null;
}

function admin_flash(string $type, string $message): void
{
    $_SESSION['rgts_admin_flash'] = ['type' => $type, 'message' => $message];
}

/** @return array{type:string,message:string}|null */
function admin_take_flash(): ?array
{
    $flash = $_SESSION['rgts_admin_flash'] ?? null;
    unset($_SESSION['rgts_admin_flash']);
    return is_array($flash) ? $flash : null;
}

function admin_payment_config_path(): string
{
    $configured = trim((string)(getenv('RGTS_PAYMENT_CONFIG') ?: ''));
    return $configured !== '' ? $configured : dirname(__DIR__, 3) . '/rgts-payment-config.php';
}

/** @return array<string,mixed>|null */
function admin_payment_config(): ?array
{
    $path = admin_payment_config_path();
    if (!is_file($path)) return null;
    $config = require $path;
    return is_array($config) ? $config : null;
}
