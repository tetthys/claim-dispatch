<?php
// src/Contracts/JobDispatcherInterface.php
declare(strict_types=1);

namespace Tetthys\ClaimDispatch\Contracts;

/**
 * Minimal boundary for pushing jobs to any queue/executor.
 */
interface JobDispatcherInterface
{
    /**
     * Dispatch many jobs at once (batch-friendly).
     *
     * @return int Number of jobs accepted by the backend.
     */
    public function dispatchMany(iterable $jobs): int;
}
