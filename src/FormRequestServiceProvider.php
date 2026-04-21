<?php

namespace RiseTechApps\FormRequest;

use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ServiceProvider;
use RiseTechApps\FormRequest\Commands\ClearCacheCommand;
use RiseTechApps\FormRequest\Commands\ExportRulesCommand;
use RiseTechApps\FormRequest\Commands\ImportRulesCommand;
use RiseTechApps\FormRequest\Commands\ListRulesCommand;
use RiseTechApps\FormRequest\Commands\MigrateCommand;
use RiseTechApps\FormRequest\Commands\SeedCommand;
use RiseTechApps\FormRequest\Commands\StatsCommand;
use RiseTechApps\FormRequest\Commands\ValidateRulesCommand;
use RiseTechApps\FormRequest\Commands\WarmCacheCommand;
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
            ClearCacheCommand::class,
            ExportRulesCommand::class,
            ImportRulesCommand::class,
            ListRulesCommand::class,
            MigrateCommand::class,
            SeedCommand::class,
            StatsCommand::class,
            ValidateRulesCommand::class,
            WarmCacheCommand::class,
        ]);

        $this->loadTranslationsFrom(__DIR__ . '/lang');

        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        $this->app->booted(function () use ($registry) {
            $this->registerRules($registry);
            $this->registerFormRegistry($registry);
        });
    }

    /**
     * Registra o FormRegistry após as classes de regras serem registradas.
     */
    private function registerFormRegistry(RulesRegistry $registry): void
    {
        // Cria o FormRegistry com os dados atuais do RulesRegistry
        $formRegistry = new FormRegistry(
            $registry->allRules(),
            $registry->allMessages()
        );

        // Registra o singleton com a instância já criada
        $this->app->instance(FormRegistry::class, $formRegistry);
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

        // FormRegistry será registrado no boot após as regras serem carregadas
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
