<?php

declare(strict_types=1);

namespace Tetthys\ClaimDispatch\Publishing;

use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Draft: fluent builder for inserting one row into action_logs.
 * - Required: type(), eligibleAt()
 * - Optional: payload(), meta(), rules(), idempotency(), when(), skipIf(), table()
 * - Returns auto-increment id on commit(). Returns 0 if skipped.
 */
final class Draft
{
    private string $table;
    private ?string $type = null;
    private ?DateTimeInterface $eligibleAt = null; // written to end_at

    /** @var array<string,mixed> */
    private array $payload = [];

    /** @var array<string,mixed> */
    private array $meta = [];

    /** @var array<string,mixed> */
    private array $rules = [];

    private ?string $idempotencyKey = null;

    /** @var callable(array $payload, array $rules): bool|null */
    private $whenPredicate = null;

    /** @var callable(array $payload, array $rules): bool|null */
    private $skipPredicate = null;

    /** @var callable(): \DateTimeInterface */
    private $nowFn;

    public function __construct(string $table)
    {
        $this->table = $table;
        $this->nowFn = static fn() => Carbon::now();
    }

    /** Per-call table override. */
    public function table(string $table): self
    {
        $this->table = $table;
        return $this;
    }

    /** Set action log type (e.g., 'seller.try', 'featured.try'). */
    public function type(string $type): self
    {
        $this->type = trim($type);
        return $this;
    }

    /** Set eligible time; this maps to "end_at" (runner minimal gate). */
    public function eligibleAt(DateTimeInterface|callable $when): self
    {
        $this->eligibleAt = is_callable($when) ? $when() : $when;
        return $this;
    }

    /** Arbitrary JSON-encodable payload. */
    public function payload(array $payload): self
    {
        $this->payload = $payload;
        return $this;
    }

    /** Merge additional meta under payload['__meta']. */
    public function meta(array $meta): self
    {
        $this->meta = array_merge($this->meta, $meta);
        return $this;
    }

    /**
     * Store declarative rules under payload['__rules'].
     * Processors may evaluate these as a second guard before dispatching a Job.
     */
    public function rules(array $rules): self
    {
        $this->rules = $rules;
        return $this;
    }

    /** Best-effort idempotency using an embedded key in payload.__meta. */
    public function idempotency(string $key): self
    {
        $this->idempotencyKey = $key;
        return $this;
    }

    /** Positive gate; insert only if predicate($payload, $rules) === true. */
    public function when(callable $predicate): self
    {
        $this->whenPredicate = $predicate;
        return $this;
    }

    /** Negative gate; skip insert if predicate($payload, $rules) === true. */
    public function skipIf(callable $predicate): self
    {
        $this->skipPredicate = $predicate;
        return $this;
    }

    /** Insert row and return auto-increment id (0 if skipped). */
    public function commit(): int
    {
        if (!$this->type) {
            throw new \RuntimeException('Draft: type is required.');
        }
        if (!$this->eligibleAt) {
            throw new \RuntimeException('Draft: eligibleAt is required.');
        }

        // Build payload with meta/rules/idempotency
        $payload = $this->payload;

        if (!empty($this->meta)) {
            $payload['__meta'] = array_merge($payload['__meta'] ?? [], $this->meta);
        }
        if (!empty($this->rules)) {
            $payload['__rules'] = $this->rules;
        }
        if ($this->idempotencyKey) {
            $payload['__meta']['idempotency_key'] = $this->idempotencyKey;

            // Naive best-effort dedupe by JSON query
            $existing = DB::table($this->table)
                ->where('type', $this->type)
                ->whereJsonContains('payload->__meta->idempotency_key', $this->idempotencyKey)
                ->orderByDesc('id')
                ->value('id');

            if ($existing) {
                return (int) $existing;
            }
        }

        // Evaluate gates
        if ($this->whenPredicate && !($this->whenPredicate)($payload, $this->rules)) {
            return 0;
        }
        if ($this->skipPredicate && ($this->skipPredicate)($payload, $this->rules)) {
            return 0;
        }

        $now = ($this->nowFn)();

        // action_logs migration uses auto-increment id → insertGetId
        return (int) DB::table($this->table)->insertGetId([
            'type'       => $this->type,
            'payload'    => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'end_at'     => $this->eligibleAt,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
