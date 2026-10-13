<?php

namespace RiseTechApps\FormRequest\Tests\Feature;

use Illuminate\Support\Facades\Validator;
use RiseTechApps\FormRequest\FormRequest;
use RiseTechApps\FormRequest\Http\Requests\DynamicFormRequest;
use RiseTechApps\FormRequest\Tests\TestCase;

/**
 * Regressão: a chave "campo.regra" era cortada no primeiro ponto, então
 * campos aninhados (cliente.documento, itens.*.documento) não encontravam
 * tradução e a resposta trazia a chave crua.
 */
class NestedFieldMessageTest extends TestCase
{
    public function test_dotted_field_reaches_the_package_default(): void
    {
        FormRequest::register('checkout', ['cliente.documento' => 'required|cpf']);

        $this->assertSame(
            'The cliente.documento field must be a valid CPF.',
            $this->errorsFor('checkout', ['cliente' => ['documento' => 'x']])['cliente.documento'][0] ?? null
        );
    }

    public function test_wildcard_field_reports_the_real_index(): void
    {
        FormRequest::register('checkout', ['itens.*.documento' => 'required|cpf']);

        $this->assertSame(
            'The itens.0.documento field must be a valid CPF.',
            $this->errorsFor('checkout', ['itens' => [['documento' => 'x']]])['itens.0.documento'][0] ?? null
        );
    }

    public function test_dotted_field_reaches_the_application_rule_message(): void
    {
        app('translator')->addLines(['validation.cpf' => 'O :attribute deve ser um CPF.'], 'en');

        FormRequest::register('checkout', ['cliente.documento' => 'required|cpf']);

        $this->assertSame(
            'O cliente.documento deve ser um CPF.',
            $this->errorsFor('checkout', ['cliente' => ['documento' => 'x']])['cliente.documento'][0] ?? null
        );
    }

    public function test_dotted_field_reaches_the_custom_message_in_laravel_format(): void
    {
        app('translator')->addLines(['validation.custom' => ['cliente.documento' => ['cpf' => 'CPF do cliente inválido.']]], 'en');

        FormRequest::register('checkout', ['cliente.documento' => 'required|cpf']);

        $this->assertSame('CPF do cliente inválido.', $this->messagesFor('checkout')['cliente.documento.cpf'] ?? null);
    }

    public function test_flat_field_keeps_the_readable_attribute(): void
    {
        FormRequest::register('checkout', ['documento' => 'required|cpf']);

        $this->assertSame('The Documento field must be a valid CPF.', $this->messagesFor('checkout')['documento.cpf'] ?? null);
    }

    public function test_explicit_message_ending_in_a_rule_name_is_kept(): void
    {
        FormRequest::register('checkout', ['site' => 'required'], ['site.required' => 'Ex.: site.com.br/x.email']);

        $this->assertSame('Ex.: site.com.br/x.email', $this->messagesFor('checkout')['site.required'] ?? null);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, array<int, string>>
     */
    private function errorsFor(string $form, array $data): array
    {
        return Validator::make($data, FormRequest::resolve($form)['rules'], $this->messagesFor($form))
            ->errors()
            ->toArray();
    }

    /**
     * @return array<string, string>
     */
    private function messagesFor(string $form): array
    {
        $request = new class extends DynamicFormRequest {
            public string $key = '';

            #[\Override]
            protected function formKey(): string
            {
                return $this->key;
            }
        };

        $request->key = $form;

        return $request->messages();
    }
}
