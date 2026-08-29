<?php
declare(strict_types=1);

interface ConnectivityProviderInterface
{
    public function providerName(): string;
    public function provision(array $order): array;
    public function getStatus(string $providerOrderReference): array;
}
