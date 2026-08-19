<?php

namespace RiseTechApps\FormRequest\Tests\Feature;

use RiseTechApps\FormRequest\FormRequest;
use RiseTechApps\FormRequest\Http\Requests\DynamicFormRequest;
use RiseTechApps\FormRequest\Tests\TestCase;

class DynamicFormRequestMessageTest extends TestCase
{
    public function test_package_default_is_used_when_the_application_defines_nothing(): void
    {
        FormRequest::register('checkout', ['documento' => 'required|cpf']);

        $this->assertSame(
            'The Documento field must be a valid CPF.',
            $this->messagesFor('checkout')['documento.cpf'] ?? null
        );
    }

    public function test_snake_case_rule_names_reach_the_package_defaults(): void
    {
        FormRequest::register('checkout', ['chave' => 'required|pix_key']);

        $this->assertSame(
            'The Chave field must be a valid Pix key.',
            $this->messagesFor('checkout')['chave.pix_key'] ?? null
        );
    }

    public function test_application_message_takes_precedence_over_the_package_default(): void
    {
        app('translator')->addLines(['validation.cpf' => 'CPF inválido.'], 'en');

        FormRequest::register('checkout', ['documento' => 'required|cpf']);

        $this->assertSame('CPF inválido.', $this->messagesFor('checkout')['documento.cpf'] ?? null);
    }

    public function test_attribute_specific_message_takes_precedence_over_everything(): void
    {
        app('translator')->addLines([
            'validation.cpf' => 'CPF inválido.',
            'validation.custom.documento.cpf' => 'Documento inválido.',
        ], 'en');

        FormRequest::register('checkout', ['documento' => 'required|cpf']);

        $this->assertSame('Documento inválido.', $this->messagesFor('checkout')['documento.cpf'] ?? null);
    }

    public function test_unknown_rule_keeps_the_raw_key(): void
    {
        FormRequest::register('checkout', ['campo' => 'required|some_unknown_rule']);

        $this->assertSame('campo.some_unknown_rule', $this->messagesFor('checkout')['campo.some_unknown_rule'] ?? null);
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
