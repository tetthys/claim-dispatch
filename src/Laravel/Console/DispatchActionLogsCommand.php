<?php
// src/Laravel/Console/DispatchActionLogsCommand.php
declare(strict_types=1);

namespace Tetthys\ClaimDispatch\Laravel\Console;

use Illuminate\Console\Command;
use Tetthys\ClaimDispatch\Contracts\SchedulerInterface;

/** php artisan claim-dispatch:run --limit=1000 */
class DispatchActionLogsCommand extends Command
{
    protected $signature = 'claim-dispatch:run {--limit=}';
    protected $description = 'Claim due logs and dispatch jobs in batch.';

    public function handle(SchedulerInterface $scheduler): int
    {
        $limit = (int)($this->option('limit') ?? config('claim-dispatch.default_limit', 1000));
        $report = $scheduler->runOnce(now(), $limit);
        $this->info("claimed={$report['claimed']} dispatched={$report['dispatched']} failed={$report['failed']}");
        return self::SUCCESS;
    }
}
