<?php

namespace RiseTechApps\FormRequest\Http\Requests;

/**
 * Form request responsável por validar atualizações em formulários dinâmicos existentes.
 */
class UpdateFormRequest extends DynamicFormRequest
{
    /**
     * Determina se o usuário autenticado pode atualizar a definição do formulário.
     */
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

    /**
     * Utiliza a definição padrão de formulário para persistência.
     */
    protected function formKey(): string
    {
        return 'form_request';
    }

    /**
     * Fornece o identificador para ajustar regras de unique durante atualizações.
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
