<?php

namespace RiseTechApps\FormRequest\Tests\Feature;

use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use RiseTechApps\FormRequest\Tests\TestCase;

class ValidationMessageTest extends TestCase
{
    #[DataProvider('ruleProvider')]
    public function test_every_rule_has_a_default_message(string $rule, string $expected): void
    {
        $this->assertSame($expected, $this->messageFor($rule));
    }

    public function test_application_message_overrides_the_default(): void
    {
        // A aplicação define a chave que o Laravel procura para regras custom.
        app('translator')->addLines(['validation.cpf' => 'CPF inválido.'], 'en');

        $this->assertSame('CPF inválido.', $this->messageFor('cpf'));
    }

    public function test_attribute_specific_message_overrides_the_default(): void
    {
        app('translator')->addLines(['validation.custom.document.cpf' => 'Documento inválido.'], 'en');

        $this->assertSame('Documento inválido.', $this->messageFor('cpf', 'document'));
    }

    public function test_inline_message_overrides_the_default(): void
    {
        $validator = Validator::make(
            ['field' => 'x'],
            ['field' => 'cpf'],
            ['field.cpf' => 'Mensagem inline.']
        );

        $this->assertSame('Mensagem inline.', $validator->errors()->first('field'));
    }

    public function test_attribute_placeholder_is_replaced(): void
    {
        $this->assertStringContainsString('document', $this->messageFor('cpf', 'document'));
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function ruleProvider(): array
    {
        return [
            'cpf' => ['cpf', 'The field field must be a valid CPF.'],
            'cnpj' => ['cnpj', 'The field field must be a valid CNPJ.'],
            'cnae' => ['cnae', 'The field field must be a valid CNAE code.'],
            'ncm' => ['ncm', 'The field field must be a valid NCM code.'],
            'credit_card' => ['credit_card', 'The field field must be a valid card number.'],
            'pix_key' => ['pix_key', 'The field field must be a valid Pix key.'],
            'bank_barcode' => ['bank_barcode', 'The field field must be a valid bank barcode.'],
            'digitable_line' => ['digitable_line', 'The field field must be a valid digitable line.'],
            'bank_slip' => ['bank_slip', 'The field field must be a valid bank slip.'],
            'strong_password' => ['strong_password', 'The field field is not strong enough.'],
            'required_if_any' => ['required_if_any:other', 'The field field is required.'],
        ];
    }

    /**
     * Roda a regra com um valor inválido e devolve a mensagem gerada.
     */
    private function messageFor(string $rule, string $attribute = 'field'): string
    {
        $data = [$attribute => 'invalid-value', 'other' => 'filled'];

        return Validator::make($data, [$attribute => $rule])->errors()->first($attribute);
    }
}
