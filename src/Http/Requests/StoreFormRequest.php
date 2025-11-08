<?php

namespace RiseTechApps\FormRequest\Http\Requests;

/**
 * Form request responsible for validating creation of dynamic form records.
 */
class StoreFormRequest extends DynamicFormRequest
{
    /**
     * Determine if the authenticated user may create a new form definition.
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
     * Use the default form request definition for persistence.
     */
    protected function formKey(): string
    {
        return 'form_request';
    }
}
