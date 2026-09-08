<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/ConnectivityProviderInterface.php';
require_once dirname(__DIR__, 2) . '/EsimCard/EsimCardClient.php';

use Resplendent\EsimCard\EsimCardClient;

final class EsimCardConnectivityProvider implements ConnectivityProviderInterface
{
    private EsimCardClient $client;
    private bool $testPurchase;

    /** @param array<string,mixed> $config */
    public function __construct(private array $config)
    {
        $environment = strtolower(trim((string)($config['environment'] ?? 'sandbox')));
        if (!in_array($environment, ['sandbox', 'production'], true)) {
            throw new RuntimeException('Invalid eSIMCard environment.');
        }
        if ($environment === 'sandbox' && empty($config['allow_sandbox_purchase'])) {
            throw new RuntimeException('eSIMCard sandbox purchasing is disabled in private configuration.');
        }
        if ($environment === 'production' && empty($config['allow_production_purchase'])) {
            throw new RuntimeException('eSIMCard production purchasing is disabled in private configuration.');
        }
        $this->client = new EsimCardClient($config);
        $this->testPurchase = $environment !== 'production';
    }

    public function providerName(): string { return 'esimcard'; }

    public function provision(array $order): array
    {
        if (($order['status'] ?? '') !== 'PAID' || empty($order['payment']['verified']) || empty($order['payment']['matches_order'])) {
            throw new RuntimeException('Provisioning is blocked until payment is independently verified.');
        }
        $packageId = trim((string)($order['payload']['plan']['provider_package_id'] ?? ''));
        if ($packageId === '') throw new RuntimeException('The supplier package reference is missing.');

        $response = $this->client->purchaseDataPackage($packageId, '', $this->testPurchase);
        $providerOrderReference = $this->findScalar($response, ['order_id','orderId','order_reference','orderReference','id']);
        if ($providerOrderReference === '') throw new RuntimeException('eSIMCard purchase completed without an order reference.');

        return [
            'provider' => 'esimcard',
            'status' => 'PROVISIONED',
            'provider_order_reference' => $providerOrderReference,
            'activation' => $this->activationData($response),
            'test_purchase' => $this->testPurchase,
        ];
    }

    public function getStatus(string $providerOrderReference): array
    {
        $response = $this->client->order($providerOrderReference);
        $rawStatus = strtolower($this->findScalar($response, ['status','order_status','orderStatus']));
        $status = in_array($rawStatus, ['completed','complete','success','successful','provisioned','active'], true)
            ? 'PROVISIONED'
            : (in_array($rawStatus, ['failed','failure','cancelled','canceled','refunded'], true) ? 'FAILED' : 'PENDING');
        return [
            'provider' => 'esimcard',
            'provider_order_reference' => $providerOrderReference,
            'status' => $status,
            'activation' => $this->activationData($response),
        ];
    }

    /** @param array<string,mixed> $response @return array<string,mixed> */
    private function activationData(array $response): array
    {
        $map = [
            'qr_code' => ['qr_code','qrCode','qrcode','qr'],
            'qr_code_url' => ['qr_code_url','qrCodeUrl','qr_url','qrUrl'],
            'iccid' => ['iccid','ICCID'],
            'smdp_address' => ['smdp_address','smdpAddress','smdp_plus_address','smdpPlusAddress'],
            'activation_code' => ['activation_code','activationCode','matching_id','matchingId'],
            'manual_code' => ['manual_code','manualCode','lpa','lpa_code','lpaCode'],
        ];
        $activation = [];
        foreach ($map as $target => $keys) {
            $value = $this->findScalar($response, $keys);
            if ($value !== '') $activation[$target] = $value;
        }
        return $activation;
    }

    /** @param array<string,mixed> $node @param array<int,string> $keys */
    private function findScalar(array $node, array $keys): string
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $node) && is_scalar($node[$key]) && trim((string)$node[$key]) !== '') {
                return trim((string)$node[$key]);
            }
        }
        foreach ($node as $value) {
            if (!is_array($value)) continue;
            $found = $this->findScalar($value, $keys);
            if ($found !== '') return $found;
        }
        return '';
    }
}
