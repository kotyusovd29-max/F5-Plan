<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $this->forceTestingDatabaseEnv();

        return parent::createApplication();
    }

    protected function setUp(): void
    {
        parent::setUp();

        if ($this->usesRefreshDatabase() && config('database.default') === 'mysql') {
            $this->fail('RefreshDatabase must not run against MySQL. Tests are misconfigured.');
        }
    }

    private function forceTestingDatabaseEnv(): void
    {
        foreach ([
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => ':memory:',
            'DB_URL' => '',
        ] as $key => $value) {
            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }

    private function usesRefreshDatabase(): bool
    {
        return in_array(RefreshDatabase::class, class_uses_recursive(static::class), true);
    }
}
