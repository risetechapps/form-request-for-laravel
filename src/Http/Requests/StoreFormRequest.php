<?php

namespace RiseTechApps\FormRequest\Http\Requests;

/**
 * Form request responsável por validar a criação de registros de formulários dinâmicos.
 */
class StoreFormRequest extends DynamicFormRequest
{
    /**
     * Determina se o usuário autenticado pode criar uma nova definição de formulário.
     */
    public function authorize(): bool
    {
        $route = $this->route();

        if ($route === null) {
            return false;
        }

        $module = get_class($route->getController()) . '@' . $route->getActionMethod();

        $user = $this->user();

        return $user !== null && $user->hasPermission($module);
    }

    /**
     * Utiliza a definição padrão de formulário para persistência.
     */
    protected function formKey(): string
    {
        return 'form_request';
    }
}
