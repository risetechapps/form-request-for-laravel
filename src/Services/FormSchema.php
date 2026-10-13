<?php

namespace RiseTechApps\FormRequest\Services;

use Illuminate\Support\Str;
use Illuminate\Validation\ValidationRuleParser;
use RiseTechApps\FormRequest\Validation\MessageTranslator;
use RiseTechApps\FormRequest\ValidationRuleRepository;
use Stringable;

/**
 * Descreve as regras de um formulário num formato simples para validação no
 * cliente: cada campo traz a lista de regras, já separada em nome e
 * parâmetros, e a mensagem traduzida de cada uma.
 *
 * Regras que consultam o banco são marcadas como server_only e perdem os
 * parâmetros, que revelariam nomes de tabelas e colunas.
 */
class FormSchema
{
    /**
     * Nomes normalizados (snake_case) das regras resolvidas só no servidor.
     */
    private const array SERVER_ONLY = ['unique', 'exists', 'unique_json', 'exists_json'];

    public function __construct(
        private readonly ValidationRuleRepository $rules,
        private readonly MessageTranslator $translator
    ) {
    }

    /**
     * Schema do formulário, ou null quando ele não existe.
     *
     * @return array{form: string, fields: array<string, array{required: bool, rules: array<int, array{rule: string, parameters: array<int, string>, server_only: bool, message: string|array<string, string>|null}>}>}|null
     */
    public function build(string $form): ?array
    {
        $resolved = $this->rules->getRules($form);

        if (empty($resolved['rules'])) {
            return null;
        }

        $messages = $this->translator->translate($resolved['messages']);
        $fields = [];

        foreach ($resolved['rules'] as $field => $definition) {
            $field = (string) $field;
            $rules = [];

            foreach ($this->segments($definition) as $segment) {
                $rules[] = $this->describe($field, $segment, $messages);
            }

            $fields[$field] = [
                'required' => in_array('required', array_column($rules, 'rule'), true),
                'rules' => $rules,
            ];
        }

        return ['form' => $form, 'fields' => $fields];
    }

    /**
     * @param array<string, string|array<string, string>> $messages
     * @return array{rule: string, parameters: array<int, string>, server_only: bool, message: string|array<string, string>|null}
     */
    private function describe(string $field, ?string $segment, array $messages): array
    {
        // Objeto de regra sem forma textual (closure, ValidationRule): só o
        // servidor sabe executá-lo.
        if ($segment === null) {
            return ['rule' => 'custom', 'parameters' => [], 'server_only' => true, 'message' => null];
        }

        $name = trim(explode(':', $segment, 2)[0]);
        $parameters = array_map(strval(...), ValidationRuleParser::parse($segment)[1]);

        // Mesma normalização de ValidationRuleRepository::extractRules(),
        // que gera as chaves "campo.regra" das mensagens.
        $messageKey = strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $name));
        $serverOnly = in_array(Str::snake($name), self::SERVER_ONLY, true);

        return [
            'rule' => $name,
            'parameters' => $serverOnly ? [] : $parameters,
            'server_only' => $serverOnly,
            'message' => $messages["{$field}.{$messageKey}"] ?? null,
        ];
    }

    /**
     * Segmentos textuais da definição; null representa um objeto opaco.
     *
     * @return array<int, string|null>
     */
    private function segments(mixed $definition): array
    {
        $segments = match (true) {
            is_string($definition) => explode('|', $definition),
            is_array($definition) => array_values($definition),
            default => [$definition],
        };

        $result = [];

        foreach ($segments as $segment) {
            if ($segment instanceof Stringable) {
                $segment = (string) $segment;
            }

            if (is_string($segment)) {
                $segment = trim($segment);

                if ($segment !== '') {
                    $result[] = $segment;
                }

                continue;
            }

            $result[] = null;
        }

        return $result;
    }
}
