<?php

namespace App\Core;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Central database connection class.
 *
 * This class gives the whole app one shared PDO connection.
 * Repositories and services should use Database::connection()
 * instead of creating their own PDO instances.
 */
class Database
{
    private static ?PDO $connection = null;

    /**
     * Return the shared PDO connection.
     */
    public static function connection(): PDO
    {
        if (self::$connection === null) {
            self::$connection = self::createConnection();
        }

        return self::$connection;
    }

    /**
     * Create a fresh PDO connection from config/database.php.
     */
    private static function createConnection(): PDO
    {
        $configPath = dirname(__DIR__, 2) . '/config/database.php';

        if (!file_exists($configPath)) {
            throw new RuntimeException('Database config file not found: ' . $configPath);
        }

        $config = require $configPath;
        $driver = $config['driver'] ?? 'sqlite';
        $options = $config['options'] ?? [];

        try {
            if ($driver === 'sqlite') {
                return self::createSqliteConnection($config, $options);
            }

            if ($driver === 'pgsql') {
                return self::createPostgresConnection($config, $options);
            }

            throw new RuntimeException('Unsupported database driver: ' . $driver);
        } catch (PDOException $exception) {
            throw new RuntimeException(
                'Database connection failed: ' . $exception->getMessage(),
                (int) $exception->getCode(),
                $exception
            );
        }
    }

    /**
     * Create a SQLite PDO connection.
     */
    private static function createSqliteConnection(array $config, array $options): PDO
    {
        $sqlitePath = $config['sqlite_path'] ?? null;

        if (!$sqlitePath) {
            throw new RuntimeException('SQLite path is missing from database config.');
        }

        $databaseDirectory = dirname($sqlitePath);

        if (!is_dir($databaseDirectory)) {
            mkdir($databaseDirectory, 0775, true);
        }

        return new PDO('sqlite:' . $sqlitePath, null, null, $options);
    }

    /**
     * Create a PostgreSQL PDO connection.
     */
    private static function createPostgresConnection(array $config, array $options): PDO
    {
        $pgsql = $config['pgsql'] ?? [];

        $host = $pgsql['host'] ?? '127.0.0.1';
        $port = $pgsql['port'] ?? '5432';
        $database = $pgsql['database'] ?? 'bank_erp_aggregator';
        $username = $pgsql['username'] ?? 'postgres';
        $password = $pgsql['password'] ?? '';

        $dsn = "pgsql:host={$host};port={$port};dbname={$database}";

        return new PDO($dsn, $username, $password, $options);
    }

    /**
     * Close the current shared connection.
     * Useful in tests or long-running scripts.
     */
    public static function disconnect(): void
    {
        self::$connection = null;
    }
}
