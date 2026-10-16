<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use LogicException;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function createApplication(): Application
    {
        $app = parent::createApplication();
        $connection = $app->make('db')->connection();
        if (! $app->environment('testing') || $connection->getDriverName() !== 'pgsql' ||
            $connection->getDatabaseName() !== 'prematricula_test') {
            throw new LogicException('Las pruebas solo pueden ejecutarse sobre PostgreSQL prematricula_test con APP_ENV=testing.');
        }

        return $app;
    }
}
