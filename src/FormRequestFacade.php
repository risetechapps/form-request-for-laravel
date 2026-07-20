<?php

namespace RiseTechApps\FormRequest;

use Illuminate\Support\Facades\Facade;

/**
 * @method static void routes(array $options = [])
 * @method static void register(string $name, array $rules, array $messages = [], array $metadata = [])
 * @method static void forget(string $name)
 * @method static array resolve(string $name, array $context = [])
 * @method static void presenceScope(string $table, callable $scope, ?string $name = null)
 * @method static void presenceScopeAll(callable $scope, ?string $name = null)
 * @method static mixed withoutPresenceScopes(callable $callback)
 *
 * @see \RiseTechApps\FormRequest\FormRequest
 */
class FormRequestFacade extends Facade
{
    /**
     * Obtém o nome registrado do componente.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'form-request';
    }
}
