<?php

namespace RiseTechApps\FormRequest\Http\Requests;

class StoreFormRequest extends DynamicFormRequest
{
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

    protected function formKey(): string
    {
        return 'form_request';
    }

}
