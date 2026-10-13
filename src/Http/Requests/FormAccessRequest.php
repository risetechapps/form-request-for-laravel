<?php

namespace RiseTechApps\FormRequest\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use RiseTechApps\FormRequest\Http\Requests\Concerns\AuthorizesByRoute;
use RiseTechApps\FormRequest\Traits\HasFormValidation\HasFormValidation;

/**
 * Request das ações sem corpo (index, show, destroy, schema): só autoriza,
 * com a mesma regra e a mesma resposta 403 de store e update.
 */
class FormAccessRequest extends FormRequest
{
    use AuthorizesByRoute;
    use HasFormValidation;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
