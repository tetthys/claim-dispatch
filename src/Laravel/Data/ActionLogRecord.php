<?php

declare(strict_types=1);

namespace Tetthys\ClaimDispatch\Laravel\Data;

use Tetthys\ClaimDispatch\Contracts\LogRecordInterface;
use Tetthys\ClaimDispatch\Laravel\Models\ActionLog;

/** Eloquent → Contract adapter. */
class ActionLogRecord implements LogRecordInterface
{
    public function __construct(private readonly ActionLog $row) {}

    public function getId(): string|int
    {
        return $this->row->id;
    }
    public function getType(): string
    {
        return $this->row->type;
    }
    public function getPayload(): array
    {
        return (array) $this->row->payload;
    }
    public function getEndAt(): \DateTimeInterface
    {
        return $this->row->end_at;
    }

    public function row(): ActionLog
    {
        return $this->row;
    }
}
