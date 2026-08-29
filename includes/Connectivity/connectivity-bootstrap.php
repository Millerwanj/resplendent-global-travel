<?php
declare(strict_types=1);

require_once __DIR__ . '/ConnectivityOrderStore.php';

function rgts_connectivity_catalog(): array
{
    // Server-authoritative test catalogue. Browser-supplied prices are never trusted.
    return [
        'sandbox-usd-1' => [
            'id' => 'sandbox-usd-1',
            'name' => 'Resplendent Connectivity Sandbox Test',
            'destination' => 'Test destination',
            'data' => '1 GB',
            'validity' => '7 days',
            'currency' => 'USD',
            'price' => '1.00',
            'provider' => 'sandbox',
            'provider_package_id' => 'sandbox-usd-1',
            'test_only' => true,
        ],
    ];
}

function rgts_connectivity_base_url(): string
{
    $host = $_SERVER['HTTP_HOST'] ?? 'www.resplendentglobaltravel.com';
    return 'https://' . preg_replace('/[^A-Za-z0-9.\-:]/', '', $host);
}

function rgts_connectivity_json(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}
