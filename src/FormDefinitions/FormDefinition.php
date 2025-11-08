<?php

namespace RiseTechApps\FormRequest\FormDefinitions;

/**
 * Immutable value object representing a dynamic form definition.
 */
final class FormDefinition
{
    /**
     * Create a new form definition instance.
     *
     * @param string $name Identifier for the form definition.
     * @param array<string, mixed> $rules Validation rules associated with the form.
     * @param array<string, string> $messages Optional validation message overrides.
     * @param array<string, mixed> $metadata Arbitrary metadata stored with the form.
     */
    public function __construct(
        private readonly string $name,
        private readonly array $rules,
        private readonly array $messages = [],
        private readonly array $metadata = []
    ) {
    }

    /**
     * Retrieve the unique name of the form definition.
     */
    public function name(): string
    {
        return $this->name;
    }

    /**
     * Get the validation rules associated with the form.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->rules;
    }

    /**
     * Get the custom validation messages defined for the form.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->messages;
    }

    /**
     * Get the supplementary metadata linked to the form definition.
     *
     * @return array<string, mixed>
     */
    public function metadata(): array
    {
        return $this->metadata;
    }
}
