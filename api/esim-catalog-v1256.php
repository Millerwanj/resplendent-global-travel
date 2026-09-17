<?php
declare(strict_types=1);

use Resplendent\Esim\EsimCardAdapter;
use Resplendent\Esim\FirstyAdapter;
use Resplendent\Esim\OfferRouter;
use Resplendent\EsimCard\EsimCardClient;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0, private');
header('Pragma: no-cache');
header('Expires: 0');
header('X-Content-Type-Options: nosniff');
header('Access-Control-Allow-Origin: https://www.resplendentglobaltravel.com');
ini_set('serialize_precision', '-1');

const RGTS_CATALOG_REFRESH_SECONDS = 900;

function catalogue_response(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

/** @param array<int,array<string,mixed>> $offers */
function catalogue_has_suspicious_uniform_pricing(array $offers): bool
{
    if (count($offers) < 3) return false;
    $prices = [];
    $profiles = [];
    foreach ($offers as $offer) {
        $price = round((float)($offer['retail_price'] ?? 0), 2);
        if ($price > 0) $prices[number_format($price, 2, '.', '')] = true;
        $profiles[(string)($offer['data_gb'] ?? '') . ':' . (string)($offer['validity_days'] ?? '')] = true;
    }
    return count($prices) === 1 && count($profiles) >= 3;
}

function catalogue_cache_read(string $path): ?array
{
    if (!is_file($path)) return null;
    $raw = file_get_contents($path);
    if (!is_string($raw) || $raw === '') return null;
    $decoded = json_decode($raw, true);
    return is_array($decoded) && !empty($decoded['ok']) ? $decoded : null;
}

function catalogue_cache_write(string $path, array $payload): void
{
    $directory = dirname($path);
    if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) return;
    $temporary = $path . '.tmp-' . bin2hex(random_bytes(4));
    $encoded = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    if (file_put_contents($temporary, $encoded, LOCK_EX) === false) return;
    chmod($temporary, 0640);
    if (!rename($temporary, $path)) @unlink($temporary);
}

try {
    $configPath = dirname(__DIR__, 2) . '/rgts-esimcard-config.php';
    if (!is_file($configPath)) catalogue_response(503, ['ok' => false, 'message' => 'Package catalogue is temporarily unavailable.']);
    $config = require $configPath;
    if (!is_array($config)) catalogue_response(503, ['ok' => false, 'message' => 'Package catalogue is temporarily unavailable.']);

    require_once dirname(__DIR__) . '/includes/EsimCard/EsimCardClient.php';
    require_once dirname(__DIR__) . '/includes/Esim/ProviderAdapterInterface.php';
    require_once dirname(__DIR__) . '/includes/Esim/EsimCardAdapter.php';
    require_once dirname(__DIR__) . '/includes/Esim/FirstyAdapter.php';
    require_once dirname(__DIR__) . '/includes/Esim/OfferRouter.php';

    $action = strtolower(trim((string)($_GET['action'] ?? 'destinations')));
    $client = new EsimCardClient($config);
    $markup = (float)($config['retail_markup_percent'] ?? 25);
    $router = new OfferRouter([new EsimCardAdapter($client), new FirstyAdapter()], $markup);
    if ($action === 'destinations') catalogue_response(200, ['ok' => true, 'destinations' => $router->destinations()]);
    if ($action === 'offers') {
        $destination = trim((string)($_GET['destination'] ?? ''));
        if (!preg_match('/^[a-f0-9]{32}$/', $destination)) catalogue_response(422, ['ok' => false, 'message' => 'Choose a valid destination.']);
        $cachePath = dirname(__DIR__) . '/data/esim-catalog-cache/' . $destination . '.json';
        $cachedPayload = catalogue_cache_read($cachePath);
        $refresh = (string)($_GET['refresh'] ?? '') === '1';

        // Normal catalogue views always use the latest safe snapshot. A
        // separate, throttled background request refreshes supplier pricing.
        if (!$refresh && $cachedPayload !== null) catalogue_response(200, $cachedPayload);

        if ($refresh) {
            $lockPath = dirname(__DIR__, 2) . '/rgts-esimcard-catalog-refresh.lock';
            $lock = fopen($lockPath, 'c+');
            if (is_resource($lock) && flock($lock, LOCK_EX | LOCK_NB)) {
                $lastRefresh = (int)trim((string)stream_get_contents($lock));
                if ($lastRefresh < time() - RGTS_CATALOG_REFRESH_SECONDS) {
                    ftruncate($lock, 0);
                    rewind($lock);
                    fwrite($lock, (string)time());
                    fflush($lock);
                    try { $client->refreshPricing(); } catch (Throwable $refreshError) {
                        error_log('[RGTS eSIM catalogue refresh] ' . $refreshError->getMessage());
                    }
                }
                flock($lock, LOCK_UN);
                fclose($lock);
            }
        }

        $offers = $router->offers($destination);
        if (catalogue_has_suspicious_uniform_pricing($offers)) {
            error_log('[RGTS eSIM catalogue] Suppressed suspicious uniform supplier pricing.');
            if ($cachedPayload !== null) catalogue_response(200, $cachedPayload);
            catalogue_response(502, ['ok' => false, 'message' => 'Live package pricing is being refreshed. Please try again shortly.']);
        }
        $payload = ['ok' => true, 'offers' => $offers, 'cached_at' => gmdate('c')];
        if ($offers !== []) catalogue_cache_write($cachePath, $payload);
        catalogue_response(200, $payload);
    }
    catalogue_response(404, ['ok' => false, 'message' => 'Unknown catalogue action.']);
} catch (Throwable $error) {
    error_log('[RGTS eSIM catalogue] ' . $error->getMessage());
    catalogue_response(502, ['ok' => false, 'message' => 'We could not load packages just now. Please try again shortly.']);
}
