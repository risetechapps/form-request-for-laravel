<?php

namespace RiseTechApps\FormRequest\Http\Requests;

/**
 * Form request responsible for validating updates to existing dynamic forms.
 */
class UpdateFormRequest extends DynamicFormRequest
{
    /**
     * Determine if the authenticated user may update the form definition.
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

    /**
     * Provide the identifier for unique rule adjustments during updates.
     *
     * @return array<string, string>
     */
    protected function validationContext(): array
    {
        $id = $this->route('id');

        if ($id === null) {
            return [];
        }

        return ['id' => (string) $id];
    }
}
