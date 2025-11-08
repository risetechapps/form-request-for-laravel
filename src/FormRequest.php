<?php

namespace RiseTechApps\FormRequest;


use Illuminate\Support\Facades\Route;
use RiseTechApps\FormRequest\FormDefinitions\FormRegistry;
use RiseTechApps\FormRequest\Http\Controllers\FormController;
use RiseTechApps\FormRequest\ValidationRuleRepository;

/**
 * Facade-like helper that exposes key package functionality statically.
 */
class FormRequest
{

    /**
     * Register the package API routes using an optional configuration array.
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
     * Register a new form definition within the in-memory registry.
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
     * Remove a form definition from the registry and clear cached rules.
     */
    public static function forget(string $name): void
    {
        $registry = app(FormRegistry::class);
        $registry->forget($name);

        app(ValidationRuleRepository::class)->clearCache($name);
    }

    /**
     * Resolve rules for a form definition without instantiating a request.
     *
     * @param array<string, mixed> $context
     * @return array{rules: array<string, mixed>, messages: array<string, string>}
     */
    public static function resolve(string $name, array $context = []): array
    {
        return app(ValidationRuleRepository::class)->getRules($name, $context);
    }
}
