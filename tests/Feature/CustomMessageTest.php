<?php

namespace RiseTechApps\FormRequest\Tests\Feature;

use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use RiseTechApps\FormRequest\FormRequest;
use RiseTechApps\FormRequest\Http\Requests\DynamicFormRequest;
use RiseTechApps\FormRequest\Models\FormRequest as FormRequestModel;
use RiseTechApps\FormRequest\Tests\TestCase;

/**
 * Regressão: o texto das mensagens personalizadas era lido como marcador
 * "campo.regra". 'Veja docs.url' virava "The Veja docs field must be a
 * valid URL.", trocando a mensagem escrita pelo usuário.
 */
class CustomMessageTest extends TestCase
{
    /**
     * @return array<string, array{0: string}>
     */
    public static function customMessages(): array
    {
        return [
            'termina em nome de regra' => ['Veja docs.url'],
            'formato com e-mail' => ['Formato: usuario.email'],
            'vários pontos' => ['Ex.: site.com.br/x.email'],
            'frase comum' => ['Informe um site válido.'],
        ];
    }

    #[DataProvider('customMessages')]
    public function test_code_message_is_returned_as_written(string $message): void
    {
        FormRequest::register('checkout', ['site' => 'required|url'], ['site.url' => $message]);

        $this->assertSame($message, $this->messagesFor('checkout')['site.url'] ?? null);
    }

    public function test_database_message_is_returned_as_written(): void
    {
        FormRequestModel::create([
            'form' => 'checkout',
            'rules' => ['site' => 'required|url'],
            'messages' => ['site.url' => 'Veja docs.url'],
        ]);

        $this->assertSame('Veja docs.url', $this->messagesFor('checkout')['site.url'] ?? null);
    }

    public function test_client_receives_the_custom_message_in_the_error_response(): void
    {
        FormRequest::register('checkout', ['site' => 'required|url'], ['site.url' => 'Veja docs.url']);

        Route::post('/checkout', fn(CheckoutRequest $request) => 'ok');

        $this->postJson('/checkout', ['site' => 'nao-e-url'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.site', ['Veja docs.url']);
    }

    public function test_existing_translation_key_is_translated_with_the_field_name(): void
    {
        FormRequest::register('checkout', ['documento' => 'required|cpf'], ['documento.cpf' => 'validation.required']);

        $this->assertSame('The Documento field is required.', $this->messagesFor('checkout')['documento.cpf'] ?? null);
    }

    public function test_generated_messages_are_still_translated(): void
    {
        FormRequest::register('checkout', ['site' => 'required|url']);

        $this->assertSame('The Site field must be a valid URL.', $this->messagesFor('checkout')['site.url'] ?? null);
    }

    /**
     * @return array<string, string|array<string, string>>
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

class CheckoutRequest extends DynamicFormRequest
{
    #[\Override]
    protected function formKey(): string
    {
        return 'checkout';
    }
}
