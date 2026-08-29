<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/ConnectivityProviderInterface.php';

/**
 * FIRSTY ADAPTER PLACEHOLDER
 *
 * The Resplendent checkout never calls Firsty directly.
 * On integration day, map Firsty's API into these two methods only.
 * Keep API credentials outside public_html.
 */
final class FirstyProvider implements ConnectivityProviderInterface
{
    public function __construct(private array $config = []) {}

    public function providerName(): string { return 'firsty'; }

    public function provision(array $order): array
    {
        throw new RuntimeException('Firsty adapter is not connected yet.');
    }

    public function getStatus(string $providerOrderReference): array
    {
        throw new RuntimeException('Firsty adapter is not connected yet.');
    }
}
