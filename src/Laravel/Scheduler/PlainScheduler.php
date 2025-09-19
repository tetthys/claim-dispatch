<?php
// src/Laravel/Scheduler/PlainScheduler.php
declare(strict_types=1);

namespace Tetthys\ClaimDispatch\Laravel\Scheduler;

use Tetthys\ClaimDispatch\Contracts\JobDispatcherInterface;
use Tetthys\ClaimDispatch\Contracts\LogProcessorInterface;
use Tetthys\ClaimDispatch\Contracts\LogRepositoryInterface;
use Tetthys\ClaimDispatch\Contracts\SchedulerInterface;

/**
 * Minimal orchestrator: claim → map → dispatch → mark.
 * Processors are provided by config('claim-dispatch.processors').
 */
class PlainScheduler implements SchedulerInterface
{
    /** @param LogProcessorInterface[] $processors */
    public function __construct(
        private readonly LogRepositoryInterface $repo,
        private readonly JobDispatcherInterface $dispatcher,
        private readonly array $processors
    ) {}

    public function runOnce(\DateTimeInterface $until, int $limit): array
    {
        $records = $this->repo->claimDue($until, $limit);
        $records = is_array($records) ? $records : iterator_to_array($records, false);

        $jobs = [];
        $failed = 0;

        foreach ($records as $r) {
            $p = $this->match($r->getType());
            if ($p) {
                $jobs[] = $p->toJob($r);
            } else {
                $failed++;
            }
        }

        $dispatched = $this->dispatcher->dispatchMany($jobs);

        $now = now();
        foreach ($records as $r) {
            $this->repo->markProcessed($r->getId(), $now);
        }

        return ['claimed' => count($records), 'dispatched' => $dispatched, 'failed' => $failed];
    }

    private function match(string $type): ?LogProcessorInterface
    {
        foreach ($this->processors as $p) {
            if ($p->supports($type)) return $p;
        }
        return null;
    }
}
