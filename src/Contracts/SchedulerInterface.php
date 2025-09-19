<?php
// src/Contracts/SchedulerInterface.php
declare(strict_types=1);

namespace Tetthys\ClaimDispatch\Contracts;

/**
 * One-pass orchestrator API: claim → map → dispatch → mark.
 * Implementation is intentionally left to the host application.
 */
interface SchedulerInterface
{
    /**
     * Run a single cycle.
     *
     * @param \DateTimeInterface $until Claim records due at or before this time.
     * @param int $limit Maximum records to claim per pass.
     * @return array{claimed:int,dispatched:int,failed:int}
     */
    public function runOnce(\DateTimeInterface $until, int $limit): array;
}
