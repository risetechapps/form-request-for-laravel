<?php

namespace RiseTechApps\FormRequest\FormDefinitions;

use Illuminate\Support\Arr;

class FormRegistry
{
    /**
     * @var array<string, FormDefinition>
     */
    private array $forms = [];

    public function __construct(array $definitions = [])
    {
        $this->registerMany($definitions);
    }

    public function register(string $name, array $rules, array $messages = [], array $metadata = []): void
    {
        $this->forms[$name] = new FormDefinition($name, $rules, $messages, $metadata);
    }

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

    public function all(): array
    {
        return $this->forms;
    }

    public function get(string $name): ?FormDefinition
    {
        return $this->forms[$name] ?? null;
    }

    public function has(string $name): bool
    {
        return array_key_exists($name, $this->forms);
    }

    public function forget(string $name): void
    {
        unset($this->forms[$name]);
    }
}
