<?php

declare(strict_types=1);

return [
    'heartbeat_max_age_seconds' => (int) env('OPERATIONS_HEARTBEAT_MAX_AGE_SECONDS', 180),
    'queue_lag_max_seconds' => (int) env('OPERATIONS_QUEUE_LAG_MAX_SECONDS', 120),
    'critical_queues' => [
        'tenant-provisioning',
        'ai',
        'exports',
        'notifications',
        'default',
    ],
];
