<?php

namespace RiseTechApps\FormRequest\FormDefinitions;

use Illuminate\Support\Arr;

/**
 * Registro em memória responsável por controlar definições de formulários dinâmicos.
 */
class FormRegistry
{
    /**
     * @var array<string, FormDefinition>
     */
    private array $forms = [];

    /**
     * Inicializa o registro com uma lista opcional de definições.
     *
     * @param array<string, array<string, mixed>|array<int, mixed>> $definitions
     */
    public function __construct(array $definitions = [])
    {
        $this->registerMany($definitions);
    }

    /**
     * Registra uma única definição de formulário no registro.
     *
     * @param string $name Nome único da definição de formulário.
     * @param array<string, mixed> $rules Regras de validação do formulário.
     * @param array<string, string> $messages Mensagens de validação personalizadas.
     * @param array<string, mixed> $metadata Metadados adicionais.
     */
    public function register(string $name, array $rules, array $messages = [], array $metadata = []): void
    {
        $this->forms[$name] = new FormDefinition($name, $rules, $messages, $metadata);
    }

    /**
     * Registra várias definições de formulário de uma só vez.
     *
     * @param array<string, mixed> $definitions
     */
    public function registerMany(array $definitions): void
    {
        foreach ($definitions as $name => $definition) {
            if (
                is_array($definition)
                && Arr::isAssoc($definition)
                && array_key_exists('rules', $definition)
                && is_array($definition['rules'])
            ) {
                $rules = $definition['rules'];
                $messages = (array) ($definition['messages'] ?? []);
                $metadata = (array) ($definition['metadata'] ?? []);
            } else {
                $rules = (array) $definition;
                $messages = [];
                $metadata = [];
            }

            $this->register((string) $name, $rules, $messages, $metadata);
        }
    }

    /**
     * Recupera todas as definições de formulário registradas.
     *
     * @return array<string, FormDefinition>
     */
    public function all(): array
    {
        return $this->forms;
    }

    /**
     * Obtém uma definição de formulário específica pelo nome.
     */
    public function get(string $name): ?FormDefinition
    {
        return $this->forms[$name] ?? null;
    }

    /**
     * Verifica se uma definição de formulário foi registrada.
     */
    public function has(string $name): bool
    {
        return array_key_exists($name, $this->forms);
    }

    /**
     * Remove uma definição de formulário do registro.
     */
    public function forget(string $name): void
    {
        unset($this->forms[$name]);
    }
}
