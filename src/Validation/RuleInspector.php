<?php

namespace RiseTechApps\FormRequest\Validation;

use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationRuleParser;
use Illuminate\Validation\Validator;

/**
 * Confere se as regras de um formulário existem antes de elas chegarem ao
 * banco. Sem isso uma regra digitada errado só falha quando alguém envia o
 * formulário, com "Method Validator::validateX does not exist".
 *
 * Regras nativas são reconhecidas pelo método validate{Regra} do Validator e
 * as customizadas pelas extensões registradas na factory — inclusive as da
 * aplicação, sem lista fixa para manter.
 */
class RuleInspector
{
    /**
     * Regras nativas que lançam exceção quando usadas sem parâmetros.
     */
    private const array REQUIRES_PARAMETERS = ['Unique', 'Exists'];

    /**
     * @var array<string, mixed>|null
     */
    private ?array $extensions = null;

    public function __construct(private readonly ValidationFactory $factory)
    {
    }

    /**
     * Problemas encontrados, agrupados por campo.
     *
     * @param array<mixed, mixed> $rules
     * @return array<string, array<int, string>>
     */
    public function problems(array $rules): array
    {
        $problems = [];

        foreach ($rules as $field => $definition) {
            $field = (string) $field;

            foreach ($this->fieldProblems($definition) as $problem) {
                $problems[$field][] = $problem;
            }
        }

        return $problems;
    }

    /**
     * Indica se a regra (no formato da string, ex.: required_if, uniqueJson)
     * está disponível para o Validator.
     */
    public function exists(string $rule): bool
    {
        $name = ValidationRuleParser::parse($rule)[0];

        if (!is_string($name) || $name === '') {
            return false;
        }

        return method_exists(Validator::class, 'validate' . $name)
            || array_key_exists(Str::snake($name), $this->extensions());
    }

    /**
     * @return array<int, string>
     */
    private function fieldProblems(mixed $definition): array
    {
        // Objetos de regra (Rule::in, closures) só existem em regras
        // registradas em código e não precisam de inspeção; da API chegam
        // apenas strings e listas decodificadas de JSON.
        $segments = match (true) {
            is_object($definition) => [],
            is_string($definition) => explode('|', $definition),
            is_array($definition) && array_is_list($definition) => $definition,
            default => null,
        };

        if ($segments === null) {
            return [__('form-request::validation.inspector.invalid_definition')];
        }

        $problems = [];

        foreach ($segments as $segment) {
            if (is_object($segment)) {
                continue;
            }

            if (!is_string($segment)) {
                $problems[] = __('form-request::validation.inspector.invalid_definition');

                continue;
            }

            $segment = trim($segment);

            if ($segment === '') {
                continue;
            }

            [$name, $parameters] = ValidationRuleParser::parse($segment);
            $display = trim(explode(':', $segment, 2)[0]);

            if (!$this->exists($segment)) {
                $problems[] = __('form-request::validation.inspector.unknown_rule', ['rule' => $display]);

                continue;
            }

            if (in_array($name, self::REQUIRES_PARAMETERS, true) && $parameters === []) {
                $problems[] = __('form-request::validation.inspector.missing_parameters', ['rule' => $display]);
            }
        }

        return $problems;
    }

    /**
     * @return array<string, mixed>
     */
    private function extensions(): array
    {
        if ($this->extensions === null) {
            $validator = $this->factory->make([], []);

            $this->extensions = $validator instanceof Validator ? $validator->extensions : [];
        }

        return $this->extensions;
    }
}
