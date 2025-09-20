<?php

declare(strict_types=1);

namespace Tetthys\ClaimDispatch\Publishing;

use DateTimeInterface;
use Tetthys\ClaimDispatch\Contracts\ActionLogPublisherInterface;

/**
 * Package-level, domain-agnostic ActionLog publisher.
 * - Higher-order publish(fn (Draft $d) => ...)
 * - Simple quick() API for one-liners
 */
final class ActionLogPublisher implements ActionLogPublisherInterface
{
    public function publish(callable $configure): int
    {
        $draft = new Draft(config('claim-dispatch.table', 'action_logs'));
        $configure($draft);
        return $draft->commit();
    }

    public function quick(string $type, DateTimeInterface $eligibleAt, array $payload = [], array $options = []): int
    {
        return $this->publish(function (Draft $d) use ($type, $eligibleAt, $payload, $options) {
            $d->type($type)
                ->eligibleAt($eligibleAt)
                ->payload($payload);

            if (isset($options['idempotency'])) {
                $d->idempotency((string) $options['idempotency']);
            }
            if (isset($options['rules']) && is_array($options['rules'])) {
                $d->rules($options['rules']);
            }
            if (isset($options['meta']) && is_array($options['meta'])) {
                $d->meta($options['meta']);
            }
            if (isset($options['table'])) {
                $d->table((string) $options['table']);
            }
            if (isset($options['when']) && is_callable($options['when'])) {
                $d->when($options['when']);
            }
            if (isset($options['skipIf']) && is_callable($options['skipIf'])) {
                $d->skipIf($options['skipIf']);
            }
        });
    }
}
