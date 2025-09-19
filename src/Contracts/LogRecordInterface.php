<?php
// src/Contracts/LogRecordInterface.php
declare(strict_types=1);

namespace Tetthys\ClaimDispatch\Contracts;

/**
 * Immutable view of a single log row to be scheduled/processed.
 */
interface LogRecordInterface
{
    /** Unique identifier (DB PK or natural key). */
    public function getId(): string|int;

    /** Discriminator for processor selection, e.g., "order.expire". */
    public function getType(): string;

    /** Arbitrary payload necessary to build a job. */
    public function getPayload(): array;

    /** Scheduling pivot (e.g., due if end_at <= now()). */
    public function getEndAt(): \DateTimeInterface;
}
