<?php

declare(strict_types=1);

namespace Tetthys\ClaimDispatch\Laravel;

use Illuminate\Support\ServiceProvider;
use Tetthys\ClaimDispatch\Contracts\JobDispatcherInterface;
use Tetthys\ClaimDispatch\Contracts\LogRepositoryInterface;
use Tetthys\ClaimDispatch\Contracts\SchedulerInterface;
use Tetthys\ClaimDispatch\Contracts\LogProcessorInterface;
use Tetthys\ClaimDispatch\Laravel\Console\DispatchActionLogsCommand;
use Tetthys\ClaimDispatch\Laravel\Queue\LaravelJobDispatcher;
use Tetthys\ClaimDispatch\Laravel\Repositories\EloquentLogRepository;
use Tetthys\ClaimDispatch\Laravel\Scheduler\PlainScheduler;

// ✨ NEW: publisher contract & implementation
use Tetthys\ClaimDispatch\Contracts\ActionLogPublisherInterface;
use Tetthys\ClaimDispatch\Publishing\ActionLogPublisher;

class ClaimDispatchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/claim-dispatch.php', 'claim-dispatch');

        // Core bindings
        $this->app->singleton(LogRepositoryInterface::class, EloquentLogRepository::class);
        $this->app->singleton(JobDispatcherInterface::class, LaravelJobDispatcher::class);

        // ✨ NEW: domain-agnostic action log publisher
        // Expose as interface and add an alias for convenience.
        $this->app->singleton(ActionLogPublisherInterface::class, ActionLogPublisher::class);
        $this->app->alias(ActionLogPublisherInterface::class, 'claim-dispatch.publisher');

        // Resolve processors from config via container
        $this->app->singleton(SchedulerInterface::class, function ($app) {
            $processorClasses = (array) config('claim-dispatch.processors', []);
            $processors = array_map(fn($cls) => $app->make($cls), $processorClasses);

            // Type-safety guard (optional)
            foreach ($processors as $p) {
                if (!$p instanceof LogProcessorInterface) {
                    throw new \InvalidArgumentException(
                        'Processor must implement LogProcessorInterface: ' . get_debug_type($p)
                    );
                }
            }

            return new PlainScheduler(
                $app->make(LogRepositoryInterface::class),
                $app->make(JobDispatcherInterface::class),
                $processors
            );
        });
    }

    public function boot(): void
    {
        // Publish config and migration
        $this->publishes([
            __DIR__ . '/../../config/claim-dispatch.php' => config_path('claim-dispatch.php'),
        ], 'claim-dispatch-config');

        $this->publishes([
            __DIR__ . '/../../database/migrations/2025_09_19_000000_create_action_logs_table.php.stub'
            => database_path('migrations/' . date('Y_m_d_His') . '_create_action_logs_table.php'),
        ], 'claim-dispatch-migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([DispatchActionLogsCommand::class]);
        }
    }
}
