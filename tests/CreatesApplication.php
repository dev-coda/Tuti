<?php

namespace Tests;

use Dotenv\Dotenv;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;

trait CreatesApplication
{
    /**
     * Creates the application.
     */
    public function createApplication(): Application
    {
        $this->configureTestDatabase();

        $app = require __DIR__.'/../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    /**
     * Use SQLite in-memory when available; otherwise PostgreSQL/MySQL so tests run on
     * servers without php-sqlite (common on stage). Set PHPUNIT_DB_* to override.
     */
    protected function configureTestDatabase(): void
    {
        $basePath = dirname(__DIR__);
        if (is_readable($basePath . '/.env.testing')) {
            Dotenv::createImmutable($basePath, '.env.testing')->safeLoad();
        } elseif (is_readable($basePath . '/.env')) {
            Dotenv::createImmutable($basePath)->safeLoad();
        }

        $set = static function (array $pairs): void {
            foreach ($pairs as $key => $value) {
                if ($value === null || $value === '') {
                    continue;
                }
                putenv("$key=$value");
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
        };

        if (extension_loaded('pdo_sqlite')) {
            $set([
                'DB_CONNECTION' => 'sqlite',
                'DB_DATABASE' => ':memory:',
            ]);

            return;
        }

        if (extension_loaded('pdo_pgsql')) {
            $dbName = getenv('PHPUNIT_DB_DATABASE');
            if ($dbName === false || $dbName === '') {
                $main = getenv('DB_DATABASE');
                $dbName = $main !== false && $main !== ''
                    ? $main . '_phpunit'
                    : 'testing';
            }

            $set([
                'DB_CONNECTION' => 'pgsql',
                'DB_HOST' => getenv('PHPUNIT_DB_HOST') ?: getenv('DB_HOST') ?: '127.0.0.1',
                'DB_PORT' => getenv('PHPUNIT_DB_PORT') ?: getenv('DB_PORT') ?: '5432',
                'DB_DATABASE' => $dbName,
                'DB_USERNAME' => getenv('PHPUNIT_DB_USERNAME') ?: getenv('DB_USERNAME') ?: 'postgres',
                'DB_PASSWORD' => getenv('PHPUNIT_DB_PASSWORD') !== false
                    ? getenv('PHPUNIT_DB_PASSWORD')
                    : (getenv('DB_PASSWORD') ?: ''),
            ]);

            return;
        }

        if (extension_loaded('pdo_mysql')) {
            $dbName = getenv('PHPUNIT_DB_DATABASE');
            if ($dbName === false || $dbName === '') {
                $main = getenv('DB_DATABASE');
                $dbName = $main !== false && $main !== ''
                    ? $main . '_phpunit'
                    : 'testing';
            }

            $set([
                'DB_CONNECTION' => 'mysql',
                'DB_HOST' => getenv('PHPUNIT_DB_HOST') ?: getenv('DB_HOST') ?: '127.0.0.1',
                'DB_PORT' => getenv('PHPUNIT_DB_PORT') ?: getenv('DB_PORT') ?: '3306',
                'DB_DATABASE' => $dbName,
                'DB_USERNAME' => getenv('PHPUNIT_DB_USERNAME') ?: getenv('DB_USERNAME') ?: 'root',
                'DB_PASSWORD' => getenv('PHPUNIT_DB_PASSWORD') !== false
                    ? getenv('PHPUNIT_DB_PASSWORD')
                    : (getenv('DB_PASSWORD') ?: ''),
            ]);

            return;
        }

        throw new \RuntimeException(
            'No PDO driver for tests: install php-sqlite3, php-pgsql, or php-mysql, or enable the PDO extension.'
        );
    }
}
