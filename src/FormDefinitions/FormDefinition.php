<?php

namespace RiseTechApps\FormRequest\FormDefinitions;

final class FormDefinition
{
    public function __construct(
        private readonly string $name,
        private readonly array $rules,
        private readonly array $messages = [],
        private readonly array $metadata = []
    ) {
    }

    public function name(): string
    {
        return $this->name;
    }

    public function rules(): array
    {
        return $this->rules;
    }

    public function messages(): array
    {
        return $this->messages;
    }

    public function metadata(): array
    {
        return $this->metadata;
    }
}
