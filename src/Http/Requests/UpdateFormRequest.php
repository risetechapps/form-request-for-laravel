<?php

namespace RiseTechApps\FormRequest\Http\Requests;

class UpdateFormRequest extends DynamicFormRequest
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

    protected function validationContext(): array
    {
        $id = $this->route('id');

        if ($id === null) {
            return [];
        }

        return ['id' => (string) $id];
    }
}
