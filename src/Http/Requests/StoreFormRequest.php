<?php

namespace RiseTechApps\FormRequest\Http\Requests;

use RiseTechApps\FormRequest\Http\Requests\Concerns\AuthorizesByRoute;
use RiseTechApps\FormRequest\Http\Requests\Concerns\ValidatesStoredRules;

/**
 * Form request responsável por validar a criação de registros de formulários dinâmicos.
 */
class StoreFormRequest extends DynamicFormRequest
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
}
