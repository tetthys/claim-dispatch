<?php

declare(strict_types=1);

namespace Tetthys\ClaimDispatch\Contracts;

/** Immutable view of a single log row. */
interface LogRecordInterface
{
    public function getId(): string|int;
    public function getType(): string;
    public function getPayload(): array;
    public function getEndAt(): \DateTimeInterface;
}
