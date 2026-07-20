<?php

namespace RiseTechApps\FormRequest\Tests;

use Orchestra\Testbench\TestCase as OrchestraTestCase;
use RiseTechApps\FormRequest\FormRequestFacade;
use RiseTechApps\FormRequest\FormRequestServiceProvider;

abstract class TestCase extends OrchestraTestCase
{
    /**
     * @param \Illuminate\Foundation\Application $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            FormRequestServiceProvider::class,
        ];
    }

    /**
     * Espelha os aliases declarados em composer.json extra.laravel.aliases.
     *
     * @param \Illuminate\Foundation\Application $app
     * @return array<string, class-string>
     */
    protected function getPackageAliases($app): array
    {
        return [
            'FormRequest' => FormRequestFacade::class,
        ];
    }

    /**
     * Usa sqlite em memória e habilita o cache de regras para os testes.
     *
     * @param \Illuminate\Foundation\Application $app
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('rules.cache.enabled', true);
        $app['config']->set('rules.cache.ttl', 300);
        $app['config']->set('cache.default', 'array');
    }

    /**
     * @param \Illuminate\Foundation\Application $app
     */
    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }
}
