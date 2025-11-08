<?php

namespace RiseTechApps\FormRequest\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;
use RiseTechApps\FormRequest\Traits\HasFormValidation\HasFormValidation;
use RiseTechApps\FormRequest\ValidationRuleRepository;

/**
 * Base form request capable of resolving rules dynamically from storage or configuration.
 */
abstract class DynamicFormRequest extends FormRequest
{
    use HasFormValidation;

    /**
     * @var array<string, mixed>
     */
    protected array $resolvedRules = [];

    /**
     * @var array<string, string>
     */
    protected array $resolvedMessages = [];

    /**
     * Inject the rule repository while preserving the default FormRequest constructor signature.
     */
    public function __construct(
        protected ValidationRuleRepository $validatorRuleRepository,
        array $query = [],
        array $request = [],
        array $attributes = [],
        array $cookies = [],
        array $files = [],
        array $server = [],
        $content = null
    ) {
        parent::__construct($query, $request, $attributes, $cookies, $files, $server, $content);
    }

    /**
     * The registry key used to resolve the form definition.
     */
    abstract protected function formKey(): string;

    /**
     * Additional context passed to rule resolution.
     *
     * @return array<string, mixed>
     */
    protected function validationContext(): array
    {
        return [];
    }

    /**
     * Resolve the dynamic validation rules at runtime.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $this->resolveDefinition();

        return $this->resolvedRules;
    }

    /**
     * Resolve translated validation messages for the request.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        $this->resolveDefinition();

        return $this->translateMessages($this->resolvedMessages);
    }

    /**
     * Translate message keys using package, application, or default validation strings.
     *
     * @param array<string, string> $messages
     * @return array<string, string>
     */
    protected function translateMessages(array $messages): array
    {
        return array_map(function (string $value) {
            $packageKey = 'formrequest::validation.' . $value;
            if (Lang::has($packageKey)) {
                return __($packageKey);
            }

            [$attribute, $rule] = array_pad(explode('.', $value, 2), 2, null);

            $customKey = sprintf('validation.custom.%s.%s', $attribute, $rule);
            if ($attribute && $rule && Lang::has($customKey)) {
                return __($customKey);
            }

            $fallbackKey = 'validation.' . $rule;
            if ($rule && Lang::has($fallbackKey)) {
                $readableAttribute = Str::of((string) $attribute)
                    ->replace('_', ' ')
                    ->lower()
                    ->ucfirst()
                    ->toString();

                return __($fallbackKey, ['attribute' => $readableAttribute]);
            }

            return $value;
        }, $messages);
    }

    /**
     * Cache the resolved rule definition for subsequent calls.
     */
    protected function resolveDefinition(): void
    {
        if (!empty($this->resolvedRules)) {
            return;
        }

        $definition = $this->validatorRuleRepository->getRules($this->formKey(), $this->validationContext());
        $this->resolvedRules = $definition['rules'];
        $this->resolvedMessages = $definition['messages'];
    }
}
