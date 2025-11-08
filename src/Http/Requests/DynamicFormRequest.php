<?php

namespace RiseTechApps\FormRequest\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;
use RiseTechApps\FormRequest\Traits\HasFormValidation\HasFormValidation;
use RiseTechApps\FormRequest\ValidationRuleRepository;

/**
 * Form request base capaz de resolver regras dinamicamente a partir do banco ou da configuração.
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
     * Injeta o repositório de regras preservando a assinatura padrão do construtor de FormRequest.
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
     * Chave do registro utilizada para resolver a definição do formulário.
     */
    abstract protected function formKey(): string;

    /**
     * Contexto adicional repassado para a resolução das regras.
     *
     * @return array<string, mixed>
     */
    protected function validationContext(): array
    {
        return [];
    }

    /**
     * Resolve dinamicamente as regras de validação em tempo de execução.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $this->resolveDefinition();

        return $this->resolvedRules;
    }

    /**
     * Resolve as mensagens de validação traduzidas para o request.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        $this->resolveDefinition();

        return $this->translateMessages($this->resolvedMessages);
    }

    /**
     * Traduz as chaves de mensagem usando textos do pacote, da aplicação ou padrões do Laravel.
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
     * Armazena em cache a definição de regras resolvida para chamadas subsequentes.
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
