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
- **Publisher Facade** – simple static API: `Publisher::quick(...)`
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

Suppose you want to schedule **welcome emails**.
Each log row with `type = email.welcome` should become a `SendWelcomeEmail` Job.

```php
<?php
// app/Processors/WelcomeEmailProcessor.php

namespace App\Processors;

use Tetthys\ClaimDispatch\Contracts\LogProcessorInterface;
use Tetthys\ClaimDispatch\Contracts\LogRecordInterface;
use App\Jobs\SendWelcomeEmail;

final class WelcomeEmailProcessor implements LogProcessorInterface
{
    public function supports(string $type): bool
    {
        return $type === 'email.welcome';
    }

    public function toJob(LogRecordInterface $record): object
    {
        $p = $record->getPayload();
        return new SendWelcomeEmail((string) ($p['user_email'] ?? ''));
    }
}
```

Register it in `config/claim-dispatch.php`:

```php
'processors' => [
    App\Processors\WelcomeEmailProcessor::class,
],
```

---

### 2. Publish an Action Log

#### 👉 Using the Facade (recommended)

```php
use Publisher;

// One-liner (quick)
Publisher::quick('email.welcome', now()->addMinutes(5), [
    'user_email' => $user->email,
    'user_name'  => $user->name,
], [
    'idempotency' => 'welcome:' . $user->id,
]);

// Higher-order builder
Publisher::publish(function (\Tetthys\ClaimDispatch\Publishing\Draft $d) use ($user) {
    $d->type('email.welcome')
      ->eligibleAt(now()->addMinutes(5))
      ->payload([
          'user_email' => $user->email,
          'user_name'  => $user->name,
      ])
      ->idempotency('welcome:' . $user->id);
});
```

> Facade `Publisher` is available if you add
>
> ```php
> 'Publisher' => \Tetthys\ClaimDispatch\Laravel\Facades\Publisher::class,
> ```
>
> to your `config/app.php` aliases (or rely on auto-discovery).

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
   Insert a row into `action_logs`:

   * `type` = routing key (e.g. `email.welcome`)
   * `payload` = JSON data (email address, etc.)
   * `end_at` = earliest eligible time

2. **Scheduler**
   Runs every minute (or via cron).
   Atomically claims rows (`end_at <= now() AND claimed_at IS NULL`) and dispatches them to processors.

3. **Processor → Job**
   Each processor transforms the record into a Laravel Job.
   Jobs must be **idempotent**.

4. **Execution**
   Queue workers run the jobs.
   After success, the row is marked `processed_at`.

---

## ✅ Example Workflow

* A new user signs up.
* The app publishes an `email.welcome` log with their email and a 5-minute delay.
* After 5 minutes, the scheduler claims it and dispatches `SendWelcomeEmail`.
* The Job sends the email.
* Even with multiple workers, it is **dispatched exactly once**.

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
  Just add more processors (`email.newsletter`, `report.generate`, …).

---

## 📖 License

MIT