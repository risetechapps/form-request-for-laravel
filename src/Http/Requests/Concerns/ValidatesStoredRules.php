<?php

namespace RiseTechApps\FormRequest\Http\Requests\Concerns;

use Illuminate\Validation\Validator;
use RiseTechApps\FormRequest\Validation\RuleInspector;

/**
 * Rejeita, antes de persistir, definições cujas regras o Validator não
 * conhece. Os erros saem em rules.{campo}, junto dos demais da requisição.
 */
trait ValidatesStoredRules
{
    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $rules = $this->input('rules');

                // O formato de 'rules' já tem regra própria; sem ele não há o
                // que inspecionar.
                if (!is_array($rules) || $validator->errors()->has('rules')) {
                    return;
                }

                foreach (app(RuleInspector::class)->problems($rules) as $field => $problems) {
                    foreach ($problems as $problem) {
                        $validator->errors()->add("rules.{$field}", $problem);
                    }
                }
            },
        ];
    }
}
