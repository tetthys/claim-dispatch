<?php

declare(strict_types=1);

namespace Tetthys\ClaimDispatch\Contracts;

/** One-pass orchestrator API: claim → map → dispatch → mark. */
interface SchedulerInterface
{
    /** @return array{claimed:int, dispatched:int, failed:int} */
    public function runOnce(\DateTimeInterface $until, int $limit): array;
}
