<?php

namespace RiseTechApps\FormRequest;

use RiseTechApps\FormRequest\Contracts\RulesContract;
use RiseTechApps\FormRequest\Services\Validator;

class RulesRegistry
{

    protected array $registeredClasses = [];

    public function register(string $className): void
    {

        if (is_subclass_of($className, RulesContract::class)) {
            $this->registeredClasses[] = $className;
        }
    }

    public function allRules(): array
    {
        $all = [];

        foreach ($this->registeredClasses as $class) {
            $all = array_merge($all, $class::Rules());
        }

        return array_merge($all,
            [
                'form_request' => [
                    'form' => 'bail|required|min:3|unique:form_requests,form',
                    'rules' => 'bail|required|array',
                    'messages' => 'nullable|array',
                    'description' => 'nullable|string',
                    'data' => 'nullable|array',
                ]
            ]
        );
    }

    public function allValidators(): array
    {
        $all = [];

        foreach ($this->registeredClasses as $class) {
            $all = array_merge($all, $class::Validator());
        }

        return array_merge($all, [
            'cpf' => Validator\validateCPF::class,
            'cnpj' => Validator\validateCNPJ::class,
            'uniqueJson' => Validator\validateUniqueJson::class,
            'required_if_any' => Validator\validatorRequiredIfAny::class
        ]);
    }
}
