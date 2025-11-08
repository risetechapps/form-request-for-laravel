<?php

namespace RiseTechApps\FormRequest;


use Illuminate\Support\Facades\Route;
use RiseTechApps\FormRequest\FormDefinitions\FormRegistry;
use RiseTechApps\FormRequest\Http\Controllers\FormController;
use RiseTechApps\FormRequest\ValidationRuleRepository;

class FormRequest
{

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

    public static function register(string $name, array $rules, array $messages = [], array $metadata = []): void
    {
        $registry = app(FormRegistry::class);
        $registry->register($name, $rules, $messages, $metadata);

        app(ValidationRuleRepository::class)->clearCache($name);
    }

    public static function forget(string $name): void
    {
        $registry = app(FormRegistry::class);
        $registry->forget($name);

        app(ValidationRuleRepository::class)->clearCache($name);
    }

    public static function resolve(string $name, array $context = []): array
    {
        return app(ValidationRuleRepository::class)->getRules($name, $context);
    }
}
