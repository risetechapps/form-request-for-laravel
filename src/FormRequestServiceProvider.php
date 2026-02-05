<?php

namespace RiseTechApps\FormRequest;

use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ServiceProvider;
use RiseTechApps\FormRequest\Commands\MigrateCommand;
use RiseTechApps\FormRequest\Commands\SeedCommand;
use RiseTechApps\FormRequest\Contracts\ValidatorContract;
use RiseTechApps\FormRequest\FormDefinitions\FormRegistry;
use RiseTechApps\FormRequest\Services\FormManager;

/**
 * Service provider do pacote responsável por inicializar configurações e bindings.
 */
class FormRequestServiceProvider extends ServiceProvider
{
    /**
     * Inicializa os serviços da aplicação.
     */
    public function boot(RulesRegistry $registry): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/config.php' => config_path('rules.php'),
            ], 'config');
        }

        $this->commands([
            MigrateCommand::class,
            SeedCommand::class,

        ]);

        $this->loadTranslationsFrom(__DIR__ . '/lang');

        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        $this->app->booted(function () use ($registry) {
            $this->registerRules($registry);
        });
    }

    /**
     * Registra os serviços da aplicação.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/config.php', 'rules');

        $this->app->singleton(Commands\MigrateCommand::class, function ($app) {
            return new Commands\MigrateCommand($app['migrator'], $app['events']);
        });

        $this->app->singleton(FormRequest::class, function () {
            return new FormRequest;
        });

        $this->app->singleton(FormRegistry::class, function () {
            return new FormRegistry(
                app(RulesRegistry::class)->allRules(),
            );
        });

        $this->app->singleton(ValidationRuleRepository::class);
        $this->app->singleton(FormManager::class);

        $this->app->singleton(\RiseTechApps\FormRequest\RulesRegistry::class, function ($app) {
            return new \RiseTechApps\FormRequest\RulesRegistry();
        });
    }

    /**
     * Registra regras de validação personalizadas definidas pelo pacote e pela configuração.
     */
    private function registerRules(RulesRegistry $registry): void
    {

        $validator = app(RulesRegistry::class)->allValidators();

        foreach ($validator as $rule => $className) {

            if (new $className() instanceof ValidatorContract) {
                Validator::extend($rule, function ($attribute, $value, $parameters, $validator) use ($className) {
                    return $className::validate($attribute, $value, $parameters, $validator);
                });
            }
        }
    }
}
