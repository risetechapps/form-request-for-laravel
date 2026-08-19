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
     * Resolvido sob demanda, e não pelo construtor: a fábrica estática do
     * Symfony chama new static() com a assinatura de Request, então declarar
     * uma dependência ali quebra Request::create() e qualquer código que a use.
     */
    protected ?ValidationRuleRepository $validatorRuleRepository = null;

    /**
     * Chave do registro utilizada para resolver a definição do formulário.
     */
    abstract protected function formKey(): string;

    protected function validationRuleRepository(): ValidationRuleRepository
    {
        return $this->validatorRuleRepository ??= app(ValidationRuleRepository::class);
    }

    // validationContext() e validationData() vêm de HasFormValidation, para
    // que os form requests que usam apenas o trait tenham o mesmo comportamento.

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
    #[\Override]
    public function messages(): array
    {
        $this->resolveDefinition();

        return $this->translateMessages($this->resolvedMessages);
    }

    /**
     * Traduz as chaves de mensagem usando textos da aplicação ou os padrões do pacote.
     *
     * As chaves recebidas seguem o formato "campo.regra" gerado por
     * ValidationRuleRepository::extractRules(). A ordem reproduz a precedência do
     * Laravel: validation.custom.{campo}.{regra}, validation.{regra} e, por último,
     * a mensagem padrão do pacote em form-request::validation.{regra}.
     *
     * @param array<string, string> $messages
     * @return array<string, string>
     */
    protected function translateMessages(array $messages): array
    {
        return array_map(function (string $value) {
            [$attribute, $rule] = array_pad(explode('.', $value, 2), 2, null);

            if ($attribute === null || $attribute === '' || $rule === null || $rule === '') {
                return $value;
            }

            $customKey = sprintf('validation.custom.%s.%s', $attribute, $rule);
            if (Lang::has($customKey)) {
                return __($customKey);
            }

            $readableAttribute = Str::of($attribute)
                ->replace('_', ' ')
                ->lower()
                ->ucfirst()
                ->toString();

            $fallbackKey = 'validation.' . $rule;
            if (Lang::has($fallbackKey)) {
                return __($fallbackKey, ['attribute' => $readableAttribute]);
            }

            // Padrão do pacote: chave plana por nome de regra, no namespace
            // registrado em FormRequestServiceProvider::boot().
            $packageKey = 'form-request::validation.' . $rule;
            if (Lang::has($packageKey)) {
                return __($packageKey, ['attribute' => $readableAttribute]);
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

        $definition = $this->validationRuleRepository()->getRules($this->formKey(), $this->validationContext());
        $this->resolvedRules = $definition['rules'];
        $this->resolvedMessages = $definition['messages'];
    }
}
