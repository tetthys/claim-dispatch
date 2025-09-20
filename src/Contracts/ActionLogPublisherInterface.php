<?php

declare(strict_types=1);

namespace Tetthys\ClaimDispatch\Contracts;

use DateTimeInterface;

interface ActionLogPublisherInterface
{
    /**
     * Higher-order publish.
     * The closure receives a Draft builder and must finally call ->commit().
     * Returns inserted id (int). Returns 0 if skipped.
     */
    public function publish(callable $configure): int;

    /**
     * Minimal, domain-agnostic helper for one-liners.
     * Returns inserted id (int). Returns 0 if skipped.
     */
    public function quick(string $type, DateTimeInterface $eligibleAt, array $payload = [], array $options = []): int;
}
