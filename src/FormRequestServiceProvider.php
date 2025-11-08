<?php

namespace RiseTechApps\FormRequest;

use Illuminate\Routing\ResponseFactory;
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
    public function boot(): void
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

        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'formrequest');

        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        $this->registerRules();

        $this->registerMacros();
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
            return new FormRegistry(Rules::defaultRules());
        });

        $this->app->singleton(ValidationRuleRepository::class);
        $this->app->singleton(FormManager::class);
    }

    /**
     * Registra regras de validação personalizadas definidas pelo pacote e pela configuração.
     */
    private function registerRules(): void
    {
        $validatorConfig = config('rules.validators') ?? [];
        $validatorDefault = Rules::defaultValidators();

        foreach ($validatorConfig as $rule => $className) {

            if (new $className() instanceof ValidatorContract) {
                Validator::extend($rule, function ($attribute, $value, $parameters, $validator) use ($className) {
                    return $className::validate($attribute, $value, $parameters, $validator);
                });
            }
        }

        foreach ($validatorDefault as $rule => $className) {

            if (new $className() instanceof ValidatorContract) {
                Validator::extend($rule, function ($attribute, $value, $parameters, $validator) use ($className) {
                    return $className::validate($attribute, $value, $parameters, $validator);
                });
            }
        }
    }

    /**
     * Registra macros de resposta utilizadas para padronizar retornos JSON.
     */
    protected function registerMacros(): void
    {
        if (!ResponseFactory::hasMacro('jsonSuccess')) {
            ResponseFactory::macro('jsonSuccess', function ($data = []) {
                $response = ['success' => true];
                if (!empty($data)) {
                    $response['data'] = $data;
                }
                return response()->json($response);
            });
        }

        if (!ResponseFactory::hasMacro('jsonError')) {
            ResponseFactory::macro('jsonError', function ($data = null) {
                $response = ['success' => false];
                if (!is_null($data)) {
                    $response['message'] = $data;
                }
                return response()->json($response, 412);
            });
        }

        if (!ResponseFactory::hasMacro('jsonGone')) {
            ResponseFactory::macro('jsonGone', function ($data = null) {
                $response = ['success' => false];
                if (!is_null($data)) {
                    $response['message'] = $data;
                }
                return response()->json($response, 410);
            });
        }

        if (!ResponseFactory::hasMacro('jsonNotFound')) {
            ResponseFactory::macro('jsonNotFound', function ($data = null) {
                $response = ['success' => false];
                if (!is_null($data)) {
                    $response['message'] = $data;
                }

                return response()->json($response, 404);
            });
        }

        if (!ResponseFactory::hasMacro('jsonNotValidated')) {
            ResponseFactory::macro('jsonNotValidated', function ($message = null, $errors = null, array $extras = []) {
                $response = array_merge(['success' => false], $extras);

                if (!is_null($message)) {
                    $response['message'] = $message;
                }

                if (!is_null($errors)) {
                    $response['errors'] = $errors;
                }

                return response()->json($response, 422);
            });
        }
    }
}
