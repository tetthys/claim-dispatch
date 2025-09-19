<?php
// src/Contracts/LogProcessorInterface.php
declare(strict_types=1);

namespace Tetthys\ClaimDispatch\Contracts;

/**
 * Converts a log record into a queueable job, if the type is supported.
 */
interface LogProcessorInterface
{
    /** Return true if this processor can handle the given type. */
    public function supports(string $type): bool;

    /** Convert a claimed log record to a job object accepted by your dispatcher. */
    public function toJob(LogRecordInterface $record): object;
}
