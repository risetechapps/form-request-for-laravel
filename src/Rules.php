<?php

namespace RiseTechApps\FormRequest;

use RiseTechApps\FormRequest\Services\Validator;

class Rules
{
    public static function defaultRules(): array
    {
        $form = config('rules.forms') ?? [];

        return array_merge($form,
            [
                'form_request' => [
                    'form' => 'bail|required|min:3|unique:form_requests,form',
                    'rules' => 'bail|required|array',
                ]
            ]
        );
    }

    public static function defaultValidators(): array
    {

        return [
            'cpf' => Validator\validateCPF::class,
            'cnpj' => Validator\validateCNPJ::class,
            'cellphone' => Validator\validateCellphone::class,
            'uniqueAuthenticationCpf' => Validator\validateUniqueAuthenticationCpf::class,
            'uniqueJson' => Validator\validateUniqueJson::class,
        ];
    }
}
