<?php

use Tetthys\ClaimDispatch\Contracts\JobDispatcherInterface;

describe('JobDispatcherInterface', function () {
    it('has dispatchMany(iterable): int', function () {
        $r = new ReflectionClass(JobDispatcherInterface::class);
        expect($r->hasMethod('dispatchMany'))->toBeTrue();
    });

    it('fake dispatcher counts jobs', function () {
        $impl = new class implements JobDispatcherInterface {
            public function dispatchMany(iterable $jobs): int
            {
                return iterator_count((function () use ($jobs) {
                    yield from $jobs;
                })());
            }
        };
        expect($impl->dispatchMany([1, 2, 3]))->toBe(3);
    });
});
