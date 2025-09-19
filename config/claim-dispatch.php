<?php
// config/claim-dispatch.php

return [
    /*
     * List of processor class names (must implement LogProcessorInterface).
     * They will be resolved via the container (app()->make()).
     */
    'processors' => [
        // \App\Processors\OrderExpireProcessor::class,
        // \App\Processors\UserRemindProcessor::class,
    ],

    /*
     * Artisan command default limit per run.
     */
    'default_limit' => 1000,

    /*
     * Database table for logs.
     */
    'table' => 'action_logs',
];
