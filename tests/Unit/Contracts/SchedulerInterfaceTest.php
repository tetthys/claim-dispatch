<?php

use Tetthys\ClaimDispatch\Contracts\SchedulerInterface;

describe('SchedulerInterface', function () {
    it('has runOnce(DateTimeInterface,int): array', function () {
        $r = new ReflectionClass(SchedulerInterface::class);
        expect($r->hasMethod('runOnce'))->toBeTrue();
    });

    it('fake scheduler returns correct shape', function () {
        $s = new class implements SchedulerInterface {
            public function runOnce(DateTimeInterface $u, int $l): array
            {
                return ['claimed' => 1, 'dispatched' => 1, 'failed' => 0];
            }
        };
        $res = $s->runOnce(new DateTimeImmutable(), 1);
        expect($res)->toHaveKeys(['claimed', 'dispatched', 'failed']);
    });
});
