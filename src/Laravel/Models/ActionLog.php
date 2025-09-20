<?php

declare(strict_types=1);

namespace Tetthys\ClaimDispatch\Laravel\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent model for the logs table.
 * Table name is configurable via config('claim-dispatch.table').
 */
class ActionLog extends Model
{
    protected $table;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->table = config('claim-dispatch.table', 'action_logs');
    }

    protected $guarded = [];

    protected $casts = [
        'payload'      => 'array',
        'end_at'       => 'datetime',
        'claimed_at'   => 'datetime',
        'processed_at' => 'datetime',
    ];
}
