<?php

namespace Tests;

use Dotenv\Dotenv;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;

trait CreatesApplication
{
    /**
     * Read env after Dotenv: variables live in $_ENV; getenv() is often unset.
     */
    protected function testingEnv(string $key, ?string $default = null): ?string
    {
        if (array_key_exists($key, $_ENV) && $_ENV[$key] !== '') {
            return (string) $_ENV[$key];
        }
        if (array_key_exists($key, $_SERVER) && $_SERVER[$key] !== '') {
            return (string) $_SERVER[$key];
        }
        $g = getenv($key);
        if ($g !== false && $g !== '') {
            return $g;
        }

        return $default;
    }

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
            $dbName = $this->testingEnv('PHPUNIT_DB_DATABASE');
            if ($dbName === null || $dbName === '') {
                $main = $this->testingEnv('DB_DATABASE');
                // Avoid appending repeatedly when createApplication runs multiple times
                $dbName = ($main !== null && $main !== '')
                    ? (str_ends_with($main, '_phpunit') ? $main : $main . '_phpunit')
                    : null;
            }
            if ($dbName === null || $dbName === '') {
                throw new \RuntimeException(
                    'Set PHPUNIT_DB_DATABASE in .env, or ensure DB_DATABASE is set so tests can use {DB_DATABASE}_phpunit. '
                    . 'Example: createdb apptuti_phpunit'
                );
            }

            $set([
                'DB_CONNECTION' => 'pgsql',
                'DB_HOST' => $this->testingEnv('PHPUNIT_DB_HOST') ?: $this->testingEnv('DB_HOST', '127.0.0.1'),
                'DB_PORT' => $this->testingEnv('PHPUNIT_DB_PORT') ?: $this->testingEnv('DB_PORT', '5432'),
                'DB_DATABASE' => $dbName,
                'DB_USERNAME' => $this->testingEnv('PHPUNIT_DB_USERNAME') ?: $this->testingEnv('DB_USERNAME', 'postgres'),
                'DB_PASSWORD' => $this->testingEnv('PHPUNIT_DB_PASSWORD') ?: $this->testingEnv('DB_PASSWORD', ''),
            ]);

            return;
        }

        if (extension_loaded('pdo_mysql')) {
            $dbName = $this->testingEnv('PHPUNIT_DB_DATABASE');
            if ($dbName === null || $dbName === '') {
                $main = $this->testingEnv('DB_DATABASE');
                $dbName = ($main !== null && $main !== '')
                    ? (str_ends_with($main, '_phpunit') ? $main : $main . '_phpunit')
                    : null;
            }
            if ($dbName === null || $dbName === '') {
                throw new \RuntimeException(
                    'Set PHPUNIT_DB_DATABASE in .env, or ensure DB_DATABASE is set so tests can use {DB_DATABASE}_phpunit.'
                );
            }

            $set([
                'DB_CONNECTION' => 'mysql',
                'DB_HOST' => $this->testingEnv('PHPUNIT_DB_HOST') ?: $this->testingEnv('DB_HOST', '127.0.0.1'),
                'DB_PORT' => $this->testingEnv('PHPUNIT_DB_PORT') ?: $this->testingEnv('DB_PORT', '3306'),
                'DB_DATABASE' => $dbName,
                'DB_USERNAME' => $this->testingEnv('PHPUNIT_DB_USERNAME') ?: $this->testingEnv('DB_USERNAME', 'root'),
                'DB_PASSWORD' => $this->testingEnv('PHPUNIT_DB_PASSWORD') ?: $this->testingEnv('DB_PASSWORD', ''),
            ]);

            return;
        }

        throw new \RuntimeException(
            'No PDO driver for tests: install php-sqlite3, php-pgsql, or php-mysql, or enable the PDO extension.'
        );
    }
}
