<?php

use Tetthys\ClaimDispatch\Contracts\{LogRepositoryInterface, LogRecordInterface};

describe('LogRepositoryInterface', function () {
    it('has claimDue and markProcessed', function () {
        $r = new ReflectionClass(LogRepositoryInterface::class);
        expect($r->hasMethod('claimDue'))->toBeTrue()
            ->and($r->hasMethod('markProcessed'))->toBeTrue();
    });

    it('fake repo yields record', function () {
        $rec = new class implements LogRecordInterface {
            public function getId(): string|int
            {
                return 1;
            }
            public function getType(): string
            {
                return 't';
            }
            public function getPayload(): array
            {
                return [];
            }
            public function getEndAt(): DateTimeInterface
            {
                return new DateTimeImmutable('-1 min');
            }
        };
        $repo = new class($rec) implements LogRepositoryInterface {
            public function __construct(private $r) {}
            public function claimDue(DateTimeInterface $u, int $l): iterable
            {
                yield $this->r;
            }
            public function markProcessed(string|int $id, DateTimeInterface $w): void {}
        };
        $items = iterator_to_array($repo->claimDue(new DateTimeImmutable(), 1));
        expect($items[0])->toBeInstanceOf(LogRecordInterface::class);
    });
});
