<?php

namespace RiseTechApps\FormRequest\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;
use RiseTechApps\FormRequest\Traits\hasFormValidation\hasFormValidation;
use RiseTechApps\FormRequest\ValidationRuleRepository;

class UpdateFormRequest extends FormRequest
{
    use hasFormValidation;
    protected ValidationRuleRepository $validatorRuleRepository;

    protected array $rules = [];
    protected array $messages = [];

    public function __construct(ValidationRuleRepository $validatorRuleRepository, array $query = [], array $request = [], array $attributes = [], array $cookies = [], array $files = [], array $server = [], $content = null)
    {
        parent::__construct($query, $request, $attributes, $cookies, $files, $server, $content);

        $this->validatorRuleRepository = $validatorRuleRepository;

        $data = $this->validatorRuleRepository->getRules('form_request', [
            'id' => request()->id
        ] );

        $this->rules = $data['rules'];
        $this->messages = $data['messages'];
    }

    public function authorize(): bool
    {
        $module = get_class(request()->route()->getController()) . '@' . request()->route()->getActionMethod();
        return auth()->check() && auth()->user()->hasPermission($module);
    }

    public function rules(): array
    {
        return $this->rules;
    }

    public function messages(): array
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
        }, $this->messages);
    }
}
