<?php

namespace RiseTechApps\FormRequest\Http\Requests\Concerns;

/**
 * Autoriza pela ação da rota: o usuário precisa de hasPermission() para
 * "Controller@metodo" (ex.: FormController@destroy).
 */
trait AuthorizesByRoute
{
    public function authorize(): bool
    {
        $route = $this->route();

        if ($route === null) {
            return false;
        }

        $module = get_class($route->getController()) . '@' . $route->getActionMethod();

        $user = $this->user();

        // hasPermission() vem do model de usuário da aplicação; sem ele a
        // requisição é negada em vez de falhar com erro 500.
        return $user !== null
            && method_exists($user, 'hasPermission')
            && $user->hasPermission($module);
    }
}
