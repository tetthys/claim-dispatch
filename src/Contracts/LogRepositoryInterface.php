<?php
// src/Contracts/LogRepositoryInterface.php
declare(strict_types=1);

namespace Tetthys\ClaimDispatch\Contracts;

/** Storage boundary for atomic claiming and marking. */
interface LogRepositoryInterface
{
    /** @return iterable<LogRecordInterface> */
    public function claimDue(\DateTimeInterface $until, int $limit): iterable;
    public function markProcessed(string|int $id, \DateTimeInterface $when): void;
}
