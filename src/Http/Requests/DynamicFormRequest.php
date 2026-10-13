<?php

namespace RiseTechApps\FormRequest\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use RiseTechApps\FormRequest\Traits\HasFormValidation\HasFormValidation;
use RiseTechApps\FormRequest\Validation\MessageTranslator;
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
     * @return array<string, string|array<string, string>>
     */
    #[\Override]
    public function messages(): array
    {
        $this->resolveDefinition();

        return $this->translateMessages($this->resolvedMessages);
    }

    /**
     * Traduz as chaves de mensagem usando textos da aplicação ou os padrões do
     * pacote. Sobrescreva para trocar a estratégia de um request específico.
     *
     * @see MessageTranslator
     * @param array<string, string> $messages
     * @return array<string, string|array<string, string>>
     */
    protected function translateMessages(array $messages): array
    {
        return app(MessageTranslator::class)->translate($messages);
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
