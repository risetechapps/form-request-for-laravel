<?php

namespace RiseTechApps\FormRequest\FormDefinitions;

/**
 * Objeto de valor imutável que representa uma definição de formulário dinâmico.
 */
final class FormDefinition
{
    /**
     * Cria uma nova instância de definição de formulário.
     *
     * @param string $name Identificador da definição de formulário.
     * @param array<string, mixed> $rules Regras de validação associadas ao formulário.
     * @param array<string, string> $messages Mensagens de validação opcionais.
     * @param array<string, mixed> $metadata Metadados adicionais armazenados com o formulário.
     */
    public function __construct(
        private readonly string $name,
        private readonly array $rules,
        private readonly array $messages = [],
        private readonly array $metadata = []
    ) {
    }

    /**
     * Recupera o nome único da definição de formulário.
     */
    public function name(): string
    {
        return $this->name;
    }

    /**
     * Obtém as regras de validação associadas ao formulário.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->rules;
    }

    /**
     * Obtém as mensagens de validação personalizadas definidas para o formulário.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->messages;
    }

    /**
     * Obtém os metadados complementares vinculados à definição de formulário.
     *
     * @return array<string, mixed>
     */
    public function metadata(): array
    {
        return $this->metadata;
    }
}
