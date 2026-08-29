<?php
declare(strict_types=1);

final class ConnectivityOrderStore
{
    private string $directory;

    public function __construct(?string $directory = null)
    {
        $this->directory = $directory ?: dirname(__DIR__, 2) . '/data/connectivity-orders';
        if (!is_dir($this->directory) && !mkdir($this->directory, 0750, true) && !is_dir($this->directory)) {
            throw new RuntimeException('Connectivity order storage is unavailable.');
        }
    }

    public function create(array $payload): array
    {
        $id = 'RGTS-CON-' . gmdate('Ymd-His') . '-' . strtoupper(bin2hex(random_bytes(2)));
        $order = [
            'id' => $id,
            'created_at' => gmdate('c'),
            'updated_at' => gmdate('c'),
            'status' => 'PAYMENT_PENDING',
            'payment' => [
                'provider' => 'pesapal',
                'status' => 'pending',
                'verified' => false,
                'provider_reference' => '',
                'merchant_reference' => $id,
                'confirmation_code' => '',
            ],
            'provisioning' => [
                'provider' => 'sandbox',
                'status' => 'NOT_STARTED',
                'provider_order_reference' => '',
            ],
            'payload' => $payload,
        ];
        $this->write($order);
        return $order;
    }

    public function get(string $id): ?array
    {
        if (!preg_match('/^RGTS-CON-[A-Za-z0-9-]+$/', $id)) return null;
        $path = $this->path($id);
        if (!is_file($path)) return null;
        $decoded = json_decode((string)file_get_contents($path), true);
        return is_array($decoded) ? $decoded : null;
    }

    public function save(array $order): array
    {
        if (empty($order['id'])) throw new InvalidArgumentException('Order ID is required.');
        $order['updated_at'] = gmdate('c');
        $this->write($order);
        return $order;
    }

    public function findByProviderReference(string $trackingId): ?array
    {
        $trackingId = trim($trackingId);
        if ($trackingId === '') return null;
        foreach (glob($this->directory . '/*.json') ?: [] as $path) {
            $decoded = json_decode((string)file_get_contents($path), true);
            if (is_array($decoded) && (($decoded['payment']['provider_reference'] ?? '') === $trackingId)) {
                return $decoded;
            }
        }
        return null;
    }

    private function write(array $order): void
    {
        $path = $this->path((string)$order['id']);
        $tmp = $path . '.tmp';
        $json = json_encode($order, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false || file_put_contents($tmp, $json, LOCK_EX) === false) {
            throw new RuntimeException('Could not save connectivity order.');
        }
        chmod($tmp, 0640);
        if (!rename($tmp, $path)) {
            @unlink($tmp);
            throw new RuntimeException('Could not finalize connectivity order.');
        }
    }

    private function path(string $id): string
    {
        return $this->directory . '/' . basename($id) . '.json';
    }
}
