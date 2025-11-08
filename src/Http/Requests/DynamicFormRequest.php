<?php

namespace RiseTechApps\FormRequest\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;
use RiseTechApps\FormRequest\Traits\hasFormValidation\hasFormValidation;
use RiseTechApps\FormRequest\ValidationRuleRepository;

abstract class DynamicFormRequest extends FormRequest
{
    use hasFormValidation;

    protected array $resolvedRules = [];

    protected array $resolvedMessages = [];

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

    abstract protected function formKey(): string;

    protected function validationContext(): array
    {
        return [];
    }

    public function rules(): array
    {
        $this->resolveDefinition();

        return $this->resolvedRules;
    }

    public function messages(): array
    {
        $this->resolveDefinition();

        return $this->translateMessages($this->resolvedMessages);
    }

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
