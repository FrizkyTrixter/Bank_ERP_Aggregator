<?php

/**
 * Agentic trading dashboard configuration.
 *
 * TRADING_API_URL points at the Stock_Trading_Pipeline read-only API
 * (started with `trading-system api`). All values have safe local defaults;
 * no secrets are stored here.
 */

return [
    // Base URL of the read-only trading API (no trailing slash).
    'backend_url' => getenv('TRADING_API_URL') ?: 'http://127.0.0.1:8000',

    // Seconds to wait for the backend before falling back to the local ledger.
    'timeout' => 5,

    // Seconds a successful backend response is served from the file cache.
    'cache_ttl' => 30,
];
