<?php
declare(strict_types=1);

use Resplendent\Esim\EsimCardAdapter;
use Resplendent\Esim\FirstyAdapter;
use Resplendent\Esim\OfferRouter;
use Resplendent\EsimCard\EsimCardClient;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('Access-Control-Allow-Origin: https://www.resplendentglobaltravel.com');
ini_set('serialize_precision', '-1');

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

    $markup = (float)($config['retail_markup_percent'] ?? 25);
    $router = new OfferRouter([new EsimCardAdapter(new EsimCardClient($config)), new FirstyAdapter()], $markup);
    $action = strtolower(trim((string)($_GET['action'] ?? 'destinations')));
    if ($action === 'destinations') catalogue_response(200, ['ok' => true, 'destinations' => $router->destinations()]);
    if ($action === 'offers') {
        $destination = trim((string)($_GET['destination'] ?? ''));
        if (!preg_match('/^[a-f0-9]{32}$/', $destination)) catalogue_response(422, ['ok' => false, 'message' => 'Choose a valid destination.']);
        $offers = $router->offers($destination);
        if (catalogue_has_suspicious_uniform_pricing($offers)) {
            error_log('[RGTS eSIM catalogue] Suppressed suspicious uniform supplier pricing.');
            catalogue_response(502, ['ok' => false, 'message' => 'Live package pricing is being refreshed. Please try again shortly.']);
        }
        catalogue_response(200, ['ok' => true, 'offers' => $offers]);
    }
    catalogue_response(404, ['ok' => false, 'message' => 'Unknown catalogue action.']);
} catch (Throwable $error) {
    error_log('[RGTS eSIM catalogue] ' . $error->getMessage());
    catalogue_response(502, ['ok' => false, 'message' => 'We could not load packages just now. Please try again shortly.']);
}
