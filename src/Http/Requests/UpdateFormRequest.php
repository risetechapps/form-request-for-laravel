<?php

namespace RiseTechApps\FormRequest\Http\Requests;

use RiseTechApps\FormRequest\Http\Requests\Concerns\AuthorizesByRoute;
use RiseTechApps\FormRequest\Http\Requests\Concerns\ValidatesStoredRules;

/**
 * Form request responsável por validar atualizações em formulários dinâmicos existentes.
 */
class UpdateFormRequest extends DynamicFormRequest
{
    use AuthorizesByRoute;
    use ValidatesStoredRules;

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
    #[\Override]
    protected function validationContext(): array
    {
        $id = $this->route('id');

        if ($id === null) {
            return [];
        }

        return ['id' => (string) $id];
    }
}
