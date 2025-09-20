<?php

declare(strict_types=1);

namespace Tetthys\ClaimDispatch\Contracts;

/** Minimal dispatcher boundary. */
interface JobDispatcherInterface
{
    /** Dispatch many jobs at once; return accepted count. */
    public function dispatchMany(iterable $jobs): int;
}
