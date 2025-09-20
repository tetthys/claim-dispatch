<?php

declare(strict_types=1);

namespace Tetthys\ClaimDispatch\Laravel\Facades;

use Illuminate\Support\Facades\Facade;
use Tetthys\ClaimDispatch\Contracts\ActionLogPublisherInterface;

/**
 * @method static int publish(callable $configure)
 * @method static int quick(string $type, \DateTimeInterface $eligibleAt, array $payload = [], array $options = [])
 *
 * @see ActionLogPublisherInterface
 */
final class Publisher extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        // Bound in ClaimDispatchServiceProvider
        return ActionLogPublisherInterface::class;
    }
}
