<?php
// src/Contracts/LogRepositoryInterface.php
declare(strict_types=1);

namespace Tetthys\ClaimDispatch\Contracts;

/**
 * Storage/locking boundary for claiming and marking logs.
 * Implementations MUST ensure atomic claiming across concurrent workers.
 */
interface LogRepositoryInterface
{
    /**
     * Atomically claim up to $limit logs that are due by $until and not yet processed.
     * Implementations should also exclude already claimed rows.
     *
     * @return iterable<LogRecordInterface>
     */
    public function claimDue(\DateTimeInterface $until, int $limit): iterable;

    /**
     * Mark a record as processed (idempotent).
     */
    public function markProcessed(string|int $id, \DateTimeInterface $when): void;
}
