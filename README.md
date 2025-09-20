# tetthys/claim-dispatch

> **Atomic claim-dispatch framework for Laravel**  
> Safely schedule and dispatch jobs from a single action log table.

---

## ✨ Features

- **Single table** (`action_logs`) as a durable schedule
- **Atomic claim** – prevent duplicate dispatch across workers
- **Processors** – type-based mapping from log row → Job
- **Idempotent jobs** – designed for safe re-execution
- **Universal publisher** – domain-agnostic, higher-order & fluent
- **Configurable** – add your own processors, rules, and evaluation logic

---

## 📦 Installation

```bash
composer require tetthys/claim-dispatch
````

Publish config and migration:

```bash
php artisan vendor:publish --tag=claim-dispatch-config
php artisan vendor:publish --tag=claim-dispatch-migrations
php artisan migrate
```

---

## ⚡ Quick Start

### 1. Define a Processor

Processors map `action_logs.type` to a Job.

```php
<?php
// app/Processors/SellerTryProcessor.php

namespace App\Processors;

use Tetthys\ClaimDispatch\Contracts\LogProcessorInterface;
use Tetthys\ClaimDispatch\Contracts\LogRecordInterface;
use App\Jobs\CheckSellerTry;

final class SellerTryProcessor implements LogProcessorInterface
{
    public function supports(string $type): bool
    {
        return $type === 'seller.try';
    }

    public function toJob(LogRecordInterface $record): object
    {
        $p = $record->getPayload();
        return new CheckSellerTry((string) ($p['seller_try_id'] ?? ''));
    }
}
```

Register it in `config/claim-dispatch.php`:

```php
'processors' => [
    App\Processors\SellerTryProcessor::class,
],
```

---

### 2. Publish an Action Log

Use the **universal publisher**. Two options:

#### Higher-order builder (most flexible)

```php
use Tetthys\ClaimDispatch\Contracts\ActionLogPublisherInterface;

/** @var ActionLogPublisherInterface $publisher */
$publisher = app(ActionLogPublisherInterface::class);

$publisher->publish(function (\Tetthys\ClaimDispatch\Publishing\Draft $d) use ($sellerTry) {
    $d->type('seller.try')
      ->eligibleAt($sellerTry->end_at) // stored in end_at
      ->payload([
          'seller_try_id' => (string) $sellerTry->id,
          'user_id'       => $sellerTry->user_id,
          'state'         => $sellerTry->state,
          'amount'        => (string) $sellerTry->crypto_amount,
      ])
      ->rules([
          'eq'  => [['path' => 'state',  'value' => 'ready']],
          'gte' => [['path' => 'amount', 'value' => '0.01']],
      ])
      ->idempotency((string) $sellerTry->id);
});
```

#### Quick one-liner

```php
$publisher->quick('featured.try', $featuredTry->end_at, [
    'featured_try_id' => (string) $featuredTry->id,
    'user_id'         => $featuredTry->user_id,
], [
    'rules'       => ['gte' => [['path' => 'slot', 'value' => 1]]],
    'idempotency' => (string) $featuredTry->id,
    'when'        => fn (array $p) => !empty($p['featured_try_id']),
]);
```

---

### 3. Run the Scheduler

Add to `app/Console/Kernel.php`:

```php
protected function schedule(\Illuminate\Console\Scheduling\Schedule $schedule): void
{
    $schedule->command('claim-dispatch:run --limit=1000')->everyMinute();
}
```

Start a queue worker:

```bash
php artisan queue:work
```

---

## 🧩 How It Works

1. **Publish**
   Insert a row into `action_logs` with:

   * `type` = routing key (e.g. `seller.try`)
   * `payload` = JSON data (IDs, extra fields)
   * `end_at` = earliest eligible time

2. **Scheduler**
   Runs every minute (or via cron).
   Atomically claims rows (`end_at <= now() AND claimed_at IS NULL`) and dispatches them to processors.

3. **Processor → Job**
   Each processor transforms the record into a Laravel Job.
   Jobs must be **idempotent**.

4. **Execution**
   Queue workers handle the jobs.
   After success, the row is marked `processed_at`.

---

## ✅ Example Workflow

* A `SellerTry` model is created with an `end_at` deadline.
* Publisher writes a `seller.try` action log row with its ID.
* Scheduler claims it when due and dispatches a `CheckSellerTry` job.
* The job finalizes the attempt, checks deposits, emits events.
* Safe from duplication even under concurrency.

---

## 🔧 Advanced

* **Rules & Gates**

  * `rules([...])`: store declarative predicates in payload (`__rules`).
  * `when(fn ($payload) => ...)`: only insert if predicate passes.
  * `skipIf(fn ($payload) => ...)`: skip insert if predicate passes.
  * Processors can evaluate `__rules` as a second guard.

* **Idempotency**
  Provide a stable key with `idempotency($key)`.
  Prevents duplicate rows for the same logical event.

* **Multiple domains**
  Just add more processors (`featured.try`, `address.try`, …).

---

## 📖 License

MIT