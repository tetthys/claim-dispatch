<?php
// src/Laravel/Repositories/EloquentLogRepository.php
declare(strict_types=1);

namespace Tetthys\ClaimDispatch\Laravel\Repositories;

use Illuminate\Support\Facades\DB;
use Tetthys\ClaimDispatch\Contracts\LogRepositoryInterface;
use Tetthys\ClaimDispatch\Laravel\Data\ActionLogRecord;
use Tetthys\ClaimDispatch\Laravel\Models\ActionLog;

/**
 * Atomic claim using SELECT ... FOR UPDATE within a transaction.
 */
class EloquentLogRepository implements LogRepositoryInterface
{
    public function claimDue(\DateTimeInterface $until, int $limit): iterable
    {
        return DB::transaction(function () use ($until, $limit) {
            $rows = ActionLog::query()
                ->whereNull('processed_at')
                ->whereNull('claimed_at')
                ->where('end_at', '<=', $until)
                ->orderBy('end_at')->orderBy('id')
                ->lockForUpdate()
                ->limit($limit)
                ->get();

            if ($rows->isEmpty()) {
                return [];
            }

            ActionLog::query()
                ->whereIn('id', $rows->pluck('id'))
                ->update(['claimed_at' => now()]);

            return $rows->map(fn($r) => new ActionLogRecord($r))->all();
        });
    }

    public function markProcessed(string|int $id, \DateTimeInterface $when): void
    {
        ActionLog::query()->whereKey($id)->update(['processed_at' => $when]);
    }
}
