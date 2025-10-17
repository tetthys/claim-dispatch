<?php

use Tetthys\ClaimDispatch\Contracts\{LogProcessorInterface, LogRecordInterface};

describe('LogProcessorInterface', function () {
    it('has supports and toJob', function () {
        $r = new ReflectionClass(LogProcessorInterface::class);
        expect($r->hasMethod('supports'))->toBeTrue()
            ->and($r->hasMethod('toJob'))->toBeTrue();
    });

    it('fake processor converts record to job', function () {
        $record = new class implements LogRecordInterface {
            public function getId(): string|int
            {
                return '1';
            }
            public function getType(): string
            {
                return 'x';
            }
            public function getPayload(): array
            {
                return [];
            }
            public function getEndAt(): DateTimeInterface
            {
                return new DateTimeImmutable();
            }
        };
        $p = new class implements LogProcessorInterface {
            public function supports(string $t): bool
            {
                return true;
            }
            public function toJob(LogRecordInterface $r): object
            {
                return (object)['id' => $r->getId()];
            }
        };
        expect($p->supports('x'))->toBeTrue()
            ->and($p->toJob($record))->toBeObject();
    });
});
