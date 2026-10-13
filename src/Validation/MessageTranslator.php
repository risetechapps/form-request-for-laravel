<?php

namespace RiseTechApps\FormRequest\Validation;

use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;

/**
 * Traduz as mensagens de um formulário dinâmico.
 *
 * As chaves seguem o formato "campo.regra" gerado por
 * ValidationRuleRepository::extractRules(). A ordem reproduz a precedência do
 * Laravel: validation.custom.{campo}.{regra}, validation.{regra} e, por último,
 * a mensagem padrão do pacote em form-request::validation.{regra}.
 */
class MessageTranslator
{
    /**
     * @param array<string, string> $messages
     * @return array<string, string|array<string, string>>
     */
    public function translate(array $messages): array
    {
        $translated = [];

        foreach ($messages as $key => $value) {
            $translated[$key] = $this->translateMessage((string) $key, $value);
        }

        return $translated;
    }

    /**
     * Regras de tamanho (min, max, size, between) traduzem para um array por
     * tipo, que o Laravel aceita como mensagem inline.
     *
     * @return string|array<string, string>
     */
    public function translateMessage(string $key, string $value): string|array
    {
        // Só a mensagem gerada por extractRules() tem o valor igual à chave:
        // é um marcador "campo.regra". Qualquer outro valor foi escrito por
        // alguém e não pode ser interpretado como marcador.
        if ($value !== $key) {
            return $this->translateCustomMessage($key, $value);
        }

        // Corta no último ponto: a regra nunca tem ponto, mas o campo pode
        // ter (cliente.documento, itens.*.documento).
        $separator = strrpos($value, '.');

        if ($separator === false) {
            return $value;
        }

        $attribute = substr($value, 0, $separator);
        $rule = substr($value, $separator + 1);

        if ($attribute === '' || $rule === '') {
            return $value;
        }

        $custom = $this->customMessage($attribute, $rule);
        if ($custom !== null) {
            return $custom;
        }

        $replace = $this->attributeReplacement($attribute);

        $fallbackKey = 'validation.' . $rule;
        if (Lang::has($fallbackKey)) {
            return __($fallbackKey, $replace);
        }

        // Padrão do pacote: chave plana por nome de regra, no namespace
        // registrado em FormRequestServiceProvider::boot().
        $packageKey = 'form-request::validation.' . $rule;
        if (Lang::has($packageKey)) {
            return __($packageKey, $replace);
        }

        return $value;
    }

    /**
     * Mensagem escrita pelo usuário: devolvida como está, a menos que seja
     * uma chave de tradução existente (ex.: 'validation.cpf'), que é
     * traduzida com o nome do campo tirado da chave da mensagem.
     *
     * @return string|array<string, string>
     */
    protected function translateCustomMessage(string $key, string $value): string|array
    {
        if (!Lang::has($value)) {
            return $value;
        }

        $separator = strrpos($key, '.');
        $attribute = $separator === false ? $key : substr($key, 0, $separator);

        return __($value, $this->attributeReplacement($attribute));
    }

    /**
     * Nome legível do campo para ':attribute'. Em campo aninhado o
     * placeholder fica para o Laravel, que conhece o índice real
     * (itens.0.documento) e validation.attributes.
     *
     * @return array<string, string>
     */
    protected function attributeReplacement(string $attribute): array
    {
        if (str_contains($attribute, '.')) {
            return [];
        }

        return ['attribute' => Str::of($attribute)->replace('_', ' ')->lower()->ucfirst()->toString()];
    }

    /**
     * Mensagem de validation.custom para o campo e a regra.
     *
     * O Laravel documenta campos aninhados como chave literal
     * ('custom' => ['cliente.documento' => [...]]), que a notação de ponto de
     * Lang::has() não alcança; por isso a busca direta vem antes.
     */
    protected function customMessage(string $attribute, string $rule): ?string
    {
        $custom = Lang::get('validation.custom');

        if (is_array($custom) && is_string($custom[$attribute][$rule] ?? null)) {
            return $custom[$attribute][$rule];
        }

        $customKey = sprintf('validation.custom.%s.%s', $attribute, $rule);

        return Lang::has($customKey) ? __($customKey) : null;
    }

}
