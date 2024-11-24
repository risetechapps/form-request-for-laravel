<?php

namespace RiseTechApps\FormRequest;


use Illuminate\Support\Facades\Route;
use RiseTechApps\FormRequest\Http\Controllers\FormController;

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
}
