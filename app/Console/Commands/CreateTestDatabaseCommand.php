<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Create the PHPUnit test database (apptuti_phpunit) using the same
 * host/port/credentials as the main DB. Run once on stage when tests fail
 * with "database does not exist".
 */
class CreateTestDatabaseCommand extends Command
{
    protected $signature = 'test:create-db';

    protected $description = 'Create the PHPUnit test database (DB_DATABASE_phpunit)';

    public function handle(): int
    {
        $driver = config('database.default');
        if ($driver !== 'pgsql') {
            $this->warn('Command only supports PostgreSQL. Your default is: ' . $driver);

            return 1;
        }

        $main = config('database.connections.pgsql.database');
        $testDb = $main . '_phpunit';

        try {
            $pdo = new \PDO(
                sprintf(
                    'pgsql:host=%s;port=%s;dbname=postgres',
                    config('database.connections.pgsql.host'),
                    config('database.connections.pgsql.port')
                ),
                config('database.connections.pgsql.username'),
                config('database.connections.pgsql.password')
            );
        } catch (\PDOException $e) {
            $this->error('Cannot connect to PostgreSQL: ' . $e->getMessage());

            return 1;
        }

        $exists = $pdo->query("SELECT 1 FROM pg_database WHERE datname = " . $pdo->quote($testDb))->fetch();

        if ($exists) {
            $this->info("Database {$testDb} already exists.");

            return 0;
        }

        $pdo->exec("CREATE DATABASE {$testDb}");
        $this->info("Database {$testDb} created.");

        return 0;
    }
}
