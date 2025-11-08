<?php

namespace RiseTechApps\FormRequest\FormDefinitions;

use Illuminate\Support\Arr;

/**
 * In-memory registry responsible for tracking dynamic form definitions.
 */
class FormRegistry
{
    /**
     * @var array<string, FormDefinition>
     */
    private array $forms = [];

    /**
     * Seed the registry with an optional list of definitions.
     *
     * @param array<string, array<string, mixed>|array<int, mixed>> $definitions
     */
    public function __construct(array $definitions = [])
    {
        $this->registerMany($definitions);
    }

    /**
     * Register a single form definition in the registry.
     *
     * @param string $name Unique name for the form definition.
     * @param array<string, mixed> $rules Validation rules for the form.
     * @param array<string, string> $messages Custom validation messages.
     * @param array<string, mixed> $metadata Additional metadata payload.
     */
    public function register(string $name, array $rules, array $messages = [], array $metadata = []): void
    {
        $this->forms[$name] = new FormDefinition($name, $rules, $messages, $metadata);
    }

    /**
     * Register multiple form definitions at once.
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
     * Retrieve all registered form definitions.
     *
     * @return array<string, FormDefinition>
     */
    public function all(): array
    {
        return $this->forms;
    }

    /**
     * Get a specific form definition by name.
     */
    public function get(string $name): ?FormDefinition
    {
        return $this->forms[$name] ?? null;
    }

    /**
     * Determine if a form definition has been registered.
     */
    public function has(string $name): bool
    {
        return array_key_exists($name, $this->forms);
    }

    /**
     * Remove a form definition from the registry.
     */
    public function forget(string $name): void
    {
        unset($this->forms[$name]);
    }
}
