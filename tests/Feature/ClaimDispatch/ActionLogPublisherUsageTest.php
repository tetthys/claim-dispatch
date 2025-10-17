<?php

declare(strict_types=1);

use Tetthys\ClaimDispatch\Contracts\ActionLogPublisherInterface;

/**
 * Story-driven usage for ActionLogPublisherInterface:
 *  - publish(closure $configure): the closure receives a Draft and must call ->commit()
 *  - quick(type, eligibleAt, payload, options): one-line helper
 */
describe('ActionLogPublisher (narrative)', function () {
    /**
     * In-memory publisher that simulates an auto-increment id on commit().
     */
    $publisher = new class implements ActionLogPublisherInterface {
        private int $seq = 100;
        public function publish(callable $configure): int
        {
            $committed = false;

            // A tiny "draft" that the closure can configure and commit.
            $draft = new class($committed) {
                public bool $committed = false;
                public string $type = '';
                public DateTimeInterface $eligibleAt;
                public array $payload = [];
                public array $options = [];
                public function __construct(bool $dummy)
                {
                    $this->eligibleAt = new DateTimeImmutable();
                }
                public function type(string $t): self
                {
                    $this->type = $t;
                    return $this;
                }
                public function eligibleAt(DateTimeInterface $at): self
                {
                    $this->eligibleAt = $at;
                    return $this;
                }
                public function payload(array $p): self
                {
                    $this->payload = $p;
                    return $this;
                }
                public function options(array $o): self
                {
                    $this->options = $o;
                    return $this;
                }
                public function commit(): void
                {
                    $this->committed = true;
                }
            };

            $configure($draft);

            if (!$draft->committed) return 0; // skipped if not committed
            // Pretend insert and return new id
            return ++$this->seq;
        }

        public function quick(string $type, DateTimeInterface $eligibleAt, array $payload = [], array $options = []): int
        {
            // Minimal policy: skip if type is empty; otherwise "insert"
            return $type === '' ? 0 : 1;
        }
    };

    it('publishes when the draft is committed', function () use ($publisher) {
        $id = $publisher->publish(function ($draft) {
            // Typical domain fill-in before commit
            $draft->type('order.expire')
                ->eligibleAt(new DateTimeImmutable('+1 minute'))
                ->payload(['order_id' => 123])
                ->options(['retry' => 0])
                ->commit(); // Must call commit() to actually insert
        });

        expect($id)->toBeInt()->toBeGreaterThan(100);
    });

    it('skips when commit() is not called', function () use ($publisher) {
        $id = $publisher->publish(function ($draft) {
            // Forgot to call commit() → will be skipped
            $draft->type('order.expire');
        });

        expect($id)->toBe(0);
    });

    it('quick helper inserts with minimal inputs', function () use ($publisher) {
        $ok = $publisher->quick('order.expire', new DateTimeImmutable('+30 seconds'), ['order_id' => 456]);
        $skip = $publisher->quick('', new DateTimeImmutable('+30 seconds'));

        expect($ok)->toBe(1)->and($skip)->toBe(0);
    });
});
