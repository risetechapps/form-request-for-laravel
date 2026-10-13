<?php

namespace RiseTechApps\FormRequest\Catalog;

use RiseTechApps\FormRequest\FormDefinitions\FormDefinition;
use RiseTechApps\FormRequest\Models\FormRequest as FormRequestModel;

/**
 * Um formulário conhecido pelo pacote, com as duas origens possíveis.
 *
 * A origem efetiva segue ValidationRuleRepository::getRules(): o registro do
 * banco vence quando tem regras; sem elas, vale a definição em código.
 */
final class CatalogEntry
{
    public const string DATABASE = 'database';

    public const string CONFIG = 'config';

    /**
     * Formulário do próprio pacote, que valida POST/PUT /forms.
     */
    public const string INTERNAL_FORM = 'form_request';

    public function __construct(
        public readonly string $name,
        public readonly ?FormRequestModel $stored = null,
        public readonly ?FormDefinition $configured = null,
    ) {
    }

    /**
     * Origem das regras que a validação usa de fato.
     */
    public function source(): string
    {
        if ($this->stored !== null && (!empty($this->stored->rules) || $this->configured === null)) {
            return self::DATABASE;
        }

        return self::CONFIG;
    }

    /**
     * Formulário do pacote, e não da aplicação.
     */
    public function isInternal(): bool
    {
        return $this->name === self::INTERNAL_FORM;
    }

    /**
     * O registro do banco está substituindo uma definição em código.
     */
    public function overridesConfig(): bool
    {
        return $this->source() === self::DATABASE && $this->configured !== null;
    }

    /**
     * Origem para exibição: "database", "config" ou
     * "database (sobrescreve config)".
     */
    public function sourceLabel(): string
    {
        return $this->overridesConfig() ? 'database (sobrescreve config)' : $this->source();
    }

    /**
     * Regras da origem efetiva, sem cache nem contexto.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->source() === self::DATABASE
            ? (array) $this->stored?->rules
            : (array) $this->configured?->rules();
    }

    /**
     * Mensagens personalizadas da origem efetiva.
     *
     * @return array<string, mixed>
     */
    public function messages(): array
    {
        return $this->source() === self::DATABASE
            ? (array) ($this->stored->messages ?? [])
            : (array) $this->configured?->messages();
    }

    /**
     * Descrição e dados extras, no formato da tabela form_requests.
     *
     * @return array{description: string|null, data: array<string, mixed>}
     */
    public function details(): array
    {
        if ($this->source() === self::DATABASE) {
            return [
                'description' => $this->stored?->description,
                'data' => (array) ($this->stored->data ?? []),
            ];
        }

        $metadata = (array) $this->configured?->metadata();
        $description = $metadata['description'] ?? null;
        unset($metadata['description']);

        return [
            'description' => is_string($description) ? $description : null,
            'data' => $metadata,
        ];
    }
}
