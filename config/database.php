<?php

/**
 * Database configuration for the Bank ERP Aggregator.
 *
 * The project starts with SQLite for local-first development.
 * Later, this can be swapped to PostgreSQL by changing the DSN
 * and credentials here instead of rewriting the application code.
 */

return [
    'driver' => 'sqlite',

    // Absolute path to the SQLite database file.
    // __DIR__ points to /config, so this resolves to /database/bank_erp.sqlite
    'sqlite_path' => dirname(__DIR__) . '/database/bank_erp.db',

    // Future PostgreSQL settings.
    'pgsql' => [
        'host' => getenv('DB_HOST') ?: '127.0.0.1',
        'port' => getenv('DB_PORT') ?: '5432',
        'database' => getenv('DB_NAME') ?: 'bank_erp_aggregator',
        'username' => getenv('DB_USER') ?: 'postgres',
        'password' => getenv('DB_PASSWORD') ?: '',
    ],

    // PDO options used by every database connection.
    'options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ],
];
