<?php
declare(strict_types=1);

use Resplendent\Connectivity\ConnectivityOrchestrator;
use Resplendent\Connectivity\Providers\MockConnectivityProvider;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require_once dirname(__DIR__, 2) . '/includes/Connectivity/ConnectivityOrchestrator.php';
require_once dirname(__DIR__, 2) . '/includes/Connectivity/Providers/MockConnectivityProvider.php';

try {
    // Development only. Replace mock registrations with Carlos / Firsty / 2Sky adapters.
    $engine = new ConnectivityOrchestrator([
        'default_margin_percent' => 25,
        'providers' => ['mock-a' => ['enabled' => true], 'mock-b' => ['enabled' => true]],
    ]);
    $engine->register(new MockConnectivityProvider('mock-a'));
    $engine->register(new MockConnectivityProvider('mock-b'));
    $offers = $engine->rankedOffers([
        'country' => (string)($_GET['country'] ?? ''),
        'days' => (int)($_GET['days'] ?? 30),
        'data_gb' => (float)($_GET['data_gb'] ?? 0),
    ]);
    echo json_encode(['ok' => true, 'offers' => $offers], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
