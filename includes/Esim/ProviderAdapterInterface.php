<?php
declare(strict_types=1);

namespace Resplendent\Esim;

interface ProviderAdapterInterface
{
    public function identifier(): string;
    public function available(): bool;

    /** @return array<int,array<string,mixed>> */
    public function destinations(): array;

    /** @return array<int,array<string,mixed>> */
    public function offers(string $destinationId): array;
}
