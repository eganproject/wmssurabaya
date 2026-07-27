<?php

return [
    'enabled' => (bool) env('STOCK_API_ENABLED', true),
    'token' => env('STOCK_API_TOKEN'),
    'default_warehouse_code' => env('STOCK_API_DEFAULT_WAREHOUSE_CODE', 'WH-SMALL'),
    'rate_limit_per_minute' => (int) env('STOCK_API_RATE_LIMIT_PER_MINUTE', 60),
];
