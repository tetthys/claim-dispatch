<?php

declare(strict_types=1);

use Tetthys\ClaimDispatch\Contracts\{
    LogRecordInterface,
    LogProcessorInterface,
    JobDispatcherInterface,
    LogRepositoryInterface,
    SchedulerInterface
};

/**
 * Story-driven usage test for the whole pipeline:
 *   claim → map(to job) → dispatch → markProcessed
 *
 * We build tiny in-memory fakes to demonstrate how each boundary is meant to be used.
 */
describe('Claim→Process→Dispatch pipeline (narrative)', function () {
    /**
     * Small immutable record for the test.
     */
    $record = new class('1', 'email.send', ['to' => 'a@b.com'], new DateTimeImmutable('-1 minute')) implements LogRecordInterface {
        public function __construct(
            private string|int $id,
            private string $type,
            private array $payload,
            private DateTimeInterface $endAt,
        ) {}
        public function getId(): string|int
        {
            return $this->id;
        }
        public function getType(): string
        {
            return $this->type;
        }
        public function getPayload(): array
        {
            return $this->payload;
        }
        public function getEndAt(): DateTimeInterface
        {
            return $this->endAt;
        }
    };

    /**
     * In-memory repository that:
     *  - claims "due" records once (no duplicate claiming)
     *  - marks processed ids
     */
    $repo = new class([$record]) implements LogRepositoryInterface {
        /** @var array<string|int, LogRecordInterface> */
        private array $pool = [];
        /** @var array<string|int, bool> */
        private array $claimed = [];
        /** @var array<string|int, DateTimeInterface> */
        public array $processed = [];

        public function __construct(iterable $initial)
        {
            foreach ($initial as $r) {
                $this->pool[$r->getId()] = $r;
            }
        }

        public function claimDue(DateTimeInterface $until, int $limit): iterable
        {
            $out = [];
            foreach ($this->pool as $id => $r) {
                if (count($out) >= $limit) break;
                if (($this->claimed[$id] ?? false) === true) continue;
                if ($r->getEndAt() <= $until) {
                    $this->claimed[$id] = true; // simulate atomic claim
                    $out[] = $r;
                }
            }
            yield from $out;
        }

        public function markProcessed(string|int $id, DateTimeInterface $when): void
        {
            $this->processed[$id] = $when;
        }
    };

    /**
     * A processor for "email.send" that maps a LogRecord to a simple Job object.
     */
    $emailProcessor = new class implements LogProcessorInterface {
        public function supports(string $type): bool
        {
            return $type === 'email.send';
        }
        public function toJob(LogRecordInterface $record): object
        {
            // This could be a real Queueable job in a framework adapter.
            return (object)[
                'name' => 'SendEmail',
                'payload' => $record->getPayload(),
                'meta' => ['source_id' => $record->getId()],
            ];
        }
    };

    /**
     * A dispatcher that collects jobs and returns the accepted count.
     */
    $dispatcher = new class implements JobDispatcherInterface {
        /** @var array<int, object> */
        public array $sent = [];
        public function dispatchMany(iterable $jobs): int
        {
            $n = 0;
            foreach ($jobs as $j) {
                $this->sent[] = $j;
                $n++;
            }
            return $n;
        }
    };

    /**
     * Minimal scheduler implementation:
     *  - claims up to $limit due logs
     *  - finds a processor that supports the type
     *  - dispatches jobs as a batch
     *  - marks successfully dispatched logs as processed
     *  - returns summary counts
     */
    $scheduler = new class($repo, [$emailProcessor], $dispatcher) implements SchedulerInterface {
        public function __construct(
            private LogRepositoryInterface $repo,
            /** @var array<int, LogProcessorInterface> */
            private array $processors,
            private JobDispatcherInterface $bus,
        ) {}

        public function runOnce(DateTimeInterface $until, int $limit): array
        {
            $claimed = [];
            foreach ($this->repo->claimDue($until, $limit) as $r) {
                $claimed[] = $r;
            }

            $jobs = [];
            $mapped = [];
            $failed = 0;

            foreach ($claimed as $r) {
                $proc = $this->findProcessor($r->getType());
                if (!$proc) {
                    $failed++;
                    continue;
                }
                try {
                    $jobs[] = $proc->toJob($r);
                    $mapped[] = $r;
                } catch (Throwable) {
                    $failed++;
                }
            }

            $dispatched = 0;
            if ($jobs) {
                $dispatched = $this->bus->dispatchMany($jobs);
                // Mark only what we attempted to dispatch; a real impl might be more granular
                foreach ($mapped as $idx => $r) {
                    if ($idx < $dispatched) {
                        $this->repo->markProcessed($r->getId(), new DateTimeImmutable());
                    }
                }
            }

            return [
                'claimed' => count($claimed),
                'dispatched' => $dispatched,
                'failed' => $failed,
            ];
        }

        private function findProcessor(string $type): ?LogProcessorInterface
        {
            foreach ($this->processors as $p) {
                if ($p->supports($type)) return $p;
            }
            return null;
        }
    };

    it('claims a due record, maps to a job, dispatches, and marks processed', function () use ($scheduler, $repo, $dispatcher) {
        $summary = $scheduler->runOnce(new DateTimeImmutable('now'), 10);

        expect($summary['claimed'])->toBe(1)
            ->and($summary['dispatched'])->toBe(1)
            ->and($summary['failed'])->toBe(0);

        // Dispatcher actually received the job
        expect($dispatcher->sent)->toHaveCount(1)
            ->and($dispatcher->sent[0])->toHaveProperty('name', 'SendEmail');

        // Repository marked the record as processed
        expect($repo->processed)->toHaveCount(1);
    });

    it('does not claim twice (idempotent claim)', function () use ($scheduler) {
        $again = $scheduler->runOnce(new DateTimeImmutable('now'), 10);
        expect($again['claimed'])->toBe(0)
            ->and($again['dispatched'])->toBe(0);
    });

    it('skips when no supporting processor exists (counts as failed)', function () {
        // Prepare unsupported record and fresh repo/dispatcher/scheduler
        $unsupported = new class('99', 'sms.send', [], new DateTimeImmutable('-1 min')) implements LogRecordInterface {
            public function __construct(
                private string|int $id,
                private string $type,
                private array $payload,
                private DateTimeInterface $endAt,
            ) {}
            public function getId(): string|int
            {
                return $this->id;
            }
            public function getType(): string
            {
                return $this->type;
            }
            public function getPayload(): array
            {
                return $this->payload;
            }
            public function getEndAt(): DateTimeInterface
            {
                return $this->endAt;
            }
        };

        $repo2 = new class([$unsupported]) implements LogRepositoryInterface {
            private array $pool;
            public array $processed = [];
            public function __construct(array $pool)
            {
                $this->pool = $pool;
            }
            public function claimDue(DateTimeInterface $u, int $l): iterable
            {
                yield from $this->pool;
                $this->pool = [];
            }
            public function markProcessed(string|int $id, DateTimeInterface $w): void
            {
                $this->processed[(string)$id] = $w;
            }
        };

        $dispatcher2 = new class implements JobDispatcherInterface {
            public int $count = 0;
            public function dispatchMany(iterable $jobs): int
            {
                foreach ($jobs as $_) {
                    $this->count++;
                }
                return $this->count;
            }
        };

        // No processors registered → everything is "failed"
        $scheduler2 = new class($repo2, [], $dispatcher2) implements SchedulerInterface {
            public function __construct(private LogRepositoryInterface $r, private array $p, private JobDispatcherInterface $b) {}
            public function runOnce(DateTimeInterface $u, int $l): array
            {
                $claimed = iterator_to_array($this->r->claimDue($u, $l));
                $failed = count($claimed);
                return ['claimed' => count($claimed), 'dispatched' => 0, 'failed' => $failed];
            }
        };

        $sum = $scheduler2->runOnce(new DateTimeImmutable(), 5);
        expect($sum)->toMatchArray(['claimed' => 1, 'dispatched' => 0, 'failed' => 1]);
    });
});
