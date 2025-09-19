<?php
// src/Laravel/Queue/LaravelJobDispatcher.php
declare(strict_types=1);

namespace Tetthys\ClaimDispatch\Laravel\Queue;

use Illuminate\Support\Facades\Bus;
use Tetthys\ClaimDispatch\Contracts\JobDispatcherInterface;

/** Bridges dispatcher to Laravel's Bus::batch. */
class LaravelJobDispatcher implements JobDispatcherInterface
{
    public function dispatchMany(iterable $jobs): int
    {
        $arr = is_array($jobs) ? $jobs : iterator_to_array($jobs, false);
        if (!$arr) return 0;

        Bus::batch($arr)
            ->name('claim-dispatch')
            ->allowFailures()
            ->dispatch();

        return count($arr);
    }
}
