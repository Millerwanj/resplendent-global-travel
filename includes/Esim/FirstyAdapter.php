<?php
declare(strict_types=1);

namespace Resplendent\Esim;

use RuntimeException;

/** Adapter boundary reserved for the commercial/API review scheduled with Firsty. */
final class FirstyAdapter implements ProviderAdapterInterface
{
    public function identifier(): string { return 'firsty'; }
    public function available(): bool { return false; }
    public function destinations(): array { return []; }
    public function offers(string $destinationId): array
    {
        throw new RuntimeException('Firsty is not active.');
    }
}
