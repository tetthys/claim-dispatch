<?php

declare(strict_types=1);

namespace Tetthys\ClaimDispatch\Contracts;

/** Maps a log record to a queueable job. */
interface LogProcessorInterface
{
    public function supports(string $type): bool;
    public function toJob(LogRecordInterface $record): object;
}
