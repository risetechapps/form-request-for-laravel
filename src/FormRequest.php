<?php

namespace RiseTechApps\FormRequest;


use Illuminate\Support\Facades\Route;
use RiseTechApps\FormRequest\FormDefinitions\FormRegistry;
use RiseTechApps\FormRequest\Http\Controllers\FormController;
use RiseTechApps\FormRequest\ValidationRuleRepository;

/**
 * Helper semelhante a uma facade que expõe funcionalidades principais do pacote de forma estática.
 */
class FormRequest
{

    /**
     * Registra as rotas de API do pacote usando um array de configuração opcional.
     *
     * @param array<string, mixed> $options
     */
    public static function routes(array $options = []): void
    {
        Route::group($options, function () use ($options) {

            Route::apiResource('forms', FormController::class, [
                'parameters' => [
                    'forms' => 'id'
                ],
            ]);
        });
    }

    /**
     * Registra uma nova definição de formulário no registro em memória.
     *
     * @param array<string, mixed> $rules
     * @param array<string, string> $messages
     * @param array<string, mixed> $metadata
     */
    public static function register(string $name, array $rules, array $messages = [], array $metadata = []): void
    {
        $registry = app(FormRegistry::class);
        $registry->register($name, $rules, $messages, $metadata);

        app(ValidationRuleRepository::class)->clearCache($name);
    }

    /**
     * Remove uma definição de formulário do registro e limpa o cache de regras.
     */
    public static function forget(string $name): void
    {
        $registry = app(FormRegistry::class);
        $registry->forget($name);

        app(ValidationRuleRepository::class)->clearCache($name);
    }

    /**
     * Resolve regras para uma definição de formulário sem instanciar um request.
     *
     * @param array<string, mixed> $context
     * @return array{rules: array<string, mixed>, messages: array<string, string>}
     */
    public static function resolve(string $name, array $context = []): array
    {
        return app(ValidationRuleRepository::class)->getRules($name, $context);
    }
}
