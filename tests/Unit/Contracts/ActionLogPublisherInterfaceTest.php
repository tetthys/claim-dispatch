<?php

use Tetthys\ClaimDispatch\Contracts\ActionLogPublisherInterface;

describe('ActionLogPublisherInterface', function () {
    it('has publish and quick methods', function () {
        $i = new ReflectionClass(ActionLogPublisherInterface::class);
        expect($i->hasMethod('publish'))->toBeTrue();
        expect($i->hasMethod('quick'))->toBeTrue();
    });

    it('can be implemented minimally', function () {
        $impl = new class implements ActionLogPublisherInterface {
            public function publish(callable $f): int
            {
                $f(new class {
                    function commit() {}
                });
                return 1;
            }
            public function quick(string $t, DateTimeInterface $e, array $p = [], array $o = []): int
            {
                return 2;
            }
        };
        expect($impl->publish(fn($d) => $d->commit()))->toBe(1)
            ->and($impl->quick('x', new DateTimeImmutable()))->toBe(2);
    });
});
