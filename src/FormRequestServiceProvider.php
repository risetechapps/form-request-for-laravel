<?php

namespace RiseTechApps\FormRequest;

use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\DatabasePresenceVerifier;
use RiseTechApps\FormRequest\Validation\PresenceScopeRegistry;
use RiseTechApps\FormRequest\Validation\ScopedPresenceVerifier;
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

            $this->publishes([
                __DIR__ . '/lang' => $this->app->langPath('vendor/form-request'),
            ], 'lang');
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

        // Namespace próprio para que as mensagens do package não sejam
        // mescladas nos arquivos de tradução da aplicação.
        $this->loadTranslationsFrom(__DIR__ . '/lang', 'form-request');

        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        $this->app->booted(function () use ($registry) {
            $this->registerRules($registry);
            $this->registerFormRegistry($registry);
            $this->registerConfiguredPresenceScopes($this->app->make(PresenceScopeRegistry::class));
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
    #[\Override]
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/config.php', 'rules');

        $this->app->singleton(Commands\MigrateCommand::class, fn($app) => new Commands\MigrateCommand($app['migrator'], $app['events']));

        $this->app->singleton(FormRequest::class, fn() => new FormRequest);

        // Accessor usado por FormRequestFacade; sem o alias a facade não resolve.
        $this->app->alias(FormRequest::class, 'form-request');

        // FormRegistry será registrado no boot após as regras serem carregadas
        $this->app->singleton(ValidationRuleRepository::class);
        $this->app->singleton(FormManager::class);

        $this->app->singleton(\RiseTechApps\FormRequest\RulesRegistry::class, fn($app) => new \RiseTechApps\FormRequest\RulesRegistry());

        $this->registerPresenceVerifier();
    }

    /**
     * Substitui o presence verifier padrão por um que aplica os escopos
     * registrados a todas as consultas das regras unique e exists.
     */
    private function registerPresenceVerifier(): void
    {
        $this->app->singleton(PresenceScopeRegistry::class);

        $this->app->extend('validation.presence', function ($verifier, $app) {
            // Um verifier totalmente customizado da aplicação tem precedência.
            if (!$verifier instanceof DatabasePresenceVerifier || $verifier instanceof ScopedPresenceVerifier) {
                return $verifier;
            }

            return new ScopedPresenceVerifier($app['db'], $app[PresenceScopeRegistry::class]);
        });
    }

    /**
     * Registra os escopos declarados na configuração. Cada entrada é uma classe
     * invocável resolvida pelo container, no formato tabela => [Classe::class].
     */
    private function registerConfiguredPresenceScopes(PresenceScopeRegistry $registry): void
    {
        foreach (config('rules.presence_scopes', []) as $table => $scopes) {
            foreach ((array)$scopes as $scope) {
                $registry->scope(
                    $table,
                    is_string($scope) ? $this->app->make($scope)(...) : $scope,
                    is_string($scope) ? $scope : null
                );
            }
        }
    }

    /**
     * Registra regras de validação personalizadas definidas pelo pacote e pela configuração.
     */
    private function registerRules(RulesRegistry $registry): void
    {

        $validator = app(RulesRegistry::class)->allValidators();

        foreach ($validator as $rule => $className) {

            if (new $className() instanceof ValidatorContract) {
                Validator::extend(
                    $rule,
                    fn($attribute, $value, $parameters, $validator) => $className::validate($attribute, $value, $parameters, $validator),
                    $this->defaultMessage($rule)
                );
            }
        }
    }

    /**
     * Mensagem padrão da regra, usada apenas quando a aplicação não define a
     * sua própria em validation.{regra} ou validation.custom.{campo}.{regra}.
     */
    private function defaultMessage(string $rule): ?string
    {
        $key = 'form-request::validation.' . Str::snake($rule);

        $message = trans($key);

        return $message === $key ? null : $message;
    }
}
