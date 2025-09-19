# tetthys/claim-dispatch

Minimal **contracts + Laravel adapter** for the common **claim → process → dispatch** scheduling pipeline.

---

## ✨ What is this?

This package abstracts a very common pattern:

> A user action writes a log into DB →  
> A scheduler claims logs due at `end_at` →  
> Each log is turned into a Job →  
> Jobs are dispatched into the queue →  
> The cycle repeats safely, without duplication.

With this package you get:

- **Contracts only (framework-agnostic)** → `src/Contracts`
- **Built-in Laravel adapter** → Eloquent repository, Bus dispatcher, Artisan command, ServiceProvider, config & migration stubs

---

## 📦 Installation

```bash
composer require tetthys/claim-dispatch
````

Publish config and migration, then migrate:

```bash
php artisan vendor:publish --tag=claim-dispatch-config
php artisan vendor:publish --tag=claim-dispatch-migrations
php artisan migrate
```

---

## ⚙️ Configuration

`config/claim-dispatch.php`:

```php
return [
    'processors' => [
        \App\Processors\OrderExpireProcessor::class,
        \App\Processors\UserRemindProcessor::class,
    ],
    'default_limit' => 1000,
    'table' => 'action_logs',
];
```

* **processors** → list of classes implementing `LogProcessorInterface`
* **default\_limit** → number of logs per scheduler run
* **table** → DB table name for action logs (default: `action_logs`)

---

## 🗄️ Database

Migration creates a table like:

```sql
CREATE TABLE action_logs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    type VARCHAR(120) NOT NULL,
    payload JSON NOT NULL,
    end_at TIMESTAMP NOT NULL,
    claimed_at TIMESTAMP NULL,
    processed_at TIMESTAMP NULL,
    fail_count INT DEFAULT 0,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

---

## 🔌 Contracts

Located in `Tetthys\ClaimDispatch\Contracts`:

* `LogRecordInterface` → immutable view of a log row
* `LogRepositoryInterface` → claim due & mark processed
* `LogProcessorInterface` → supports(type) + toJob(record)
* `JobDispatcherInterface` → dispatchMany(jobs)
* `SchedulerInterface` → runOnce(until, limit): report

---

## 🚀 Laravel Usage

### 1. Create Processors

```php
namespace App\Processors;

use Tetthys\ClaimDispatch\Contracts\{LogProcessorInterface, LogRecordInterface};
use App\Jobs\ExpireOrderJob;

class OrderExpireProcessor implements LogProcessorInterface
{
    public function supports(string $type): bool
    {
        return $type === 'order.expire';
    }

    public function toJob(LogRecordInterface $record): object
    {
        return new ExpireOrderJob($record->getId(), $record->getPayload()['order_id']);
    }
}
```

### 2. Register in config

```php
'processors' => [
    \App\Processors\OrderExpireProcessor::class,
],
```

### 3. Run the Scheduler

```bash
php artisan claim-dispatch:run --limit=500
```

Or add to `App\Console\Kernel`:

```php
$schedule->command('claim-dispatch:run')->everyMinute();
```

---

## 🧪 Flow Summary

```
Scheduler::runOnce(now(), 1000)
    → EloquentLogRepository::claimDue()
    → For each record: Processor::toJob()
    → LaravelJobDispatcher::dispatchMany()
    → markProcessed()
```

---

## 📝 Example Log Lifecycle

1. Insert row into `action_logs`:

   ```sql
   INSERT INTO action_logs (type, payload, end_at, created_at, updated_at)
   VALUES ('order.expire', '{"order_id":123}', NOW() + INTERVAL 1 MINUTE, NOW(), NOW());
   ```
2. At the due time, scheduler claims row.
3. Processor builds `ExpireOrderJob`.
4. Dispatcher queues the job.
5. Repository marks the log as processed.

---

## 🔒 Guarantees

* **Atomic claiming** (using `SELECT ... FOR UPDATE`)
* **No duplicate dispatch** (claimed\_at / processed\_at markers)
* **Idempotent job handling** if you check `logId` before side effects

---

## 📄 License

MIT