<?php

use Tetthys\ClaimDispatch\Contracts\LogRecordInterface;

describe('LogRecordInterface', function () {
    it('defines getter signatures', function () {
        $r = new ReflectionClass(LogRecordInterface::class);
        expect($r->hasMethod('getId'))->toBeTrue()
            ->and($r->hasMethod('getType'))->toBeTrue()
            ->and($r->hasMethod('getPayload'))->toBeTrue()
            ->and($r->hasMethod('getEndAt'))->toBeTrue();
    });

    it('fake record returns correct types', function () {
        $rec = new class implements LogRecordInterface {
            public function getId(): string|int
            {
                return 1;
            }
            public function getType(): string
            {
                return 'demo';
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
        expect($rec->getId())->toBe(1)
            ->and($rec->getType())->toBe('demo')
            ->and($rec->getPayload())->toBeArray()
            ->and($rec->getEndAt())->toBeInstanceOf(DateTimeInterface::class);
    });
});
