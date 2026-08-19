<?php

namespace RiseTechApps\FormRequest\Tests\Feature;

use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use RiseTechApps\FormRequest\FormRequest;
use RiseTechApps\FormRequest\Http\Requests\DynamicFormRequest;
use RiseTechApps\FormRequest\Models\FormRequest as FormRequestModel;
use RiseTechApps\FormRequest\Tests\TestCase;

class ContextualFormRequest extends DynamicFormRequest
{
    public ?string $contextId = null;

    public function authorize(): bool
    {
        return true;
    }

    #[\Override]
    protected function formKey(): string
    {
        return 'profile';
    }

    #[\Override]
    protected function validationContext(): array
    {
        return $this->contextId === null ? [] : ['id' => $this->contextId];
    }
}

class NativeInterpolationTest extends TestCase
{
    private function requestWith(?string $contextId, array $payload): ContextualFormRequest
    {
        $request = ContextualFormRequest::createFrom(
            Request::create('/profile', 'POST', $payload),
            new ContextualFormRequest()
        );

        $request->setContainer($this->app);
        $request->contextId = $contextId;

        return $request;
    }

    public function test_context_is_exposed_to_the_validation_data(): void
    {
        $request = $this->requestWith('the-id', ['form' => 'clients']);

        $this->assertSame('the-id', $request->validationData()['id'] ?? null);
    }

    public function test_context_wins_over_the_payload(): void
    {
        // O corpo da requisição não pode escolher qual registro é ignorado.
        $request = $this->requestWith('from-route', ['form' => 'clients', 'id' => 'from-body']);

        $this->assertSame('from-route', $request->validationData()['id']);
    }

    public function test_native_placeholder_ignores_the_current_record(): void
    {
        $existing = FormRequestModel::create(['form' => 'clients', 'rules' => ['a' => 'b']]);

        FormRequest::register('profile', ['form' => 'required|unique:form_requests,form,[id]']);

        $ownId = $this->requestWith($existing->id, ['form' => 'clients']);
        $otherId = $this->requestWith('01a01775-cbbb-7500-8509-01569c5ae17c', ['form' => 'clients']);

        $this->assertTrue(validator($ownId->validationData(), $ownId->rules())->passes());
        $this->assertFalse(validator($otherId->validationData(), $otherId->rules())->passes());
    }

    public function test_the_native_placeholder_survives_rule_resolution(): void
    {
        // Regressão: setIdUpdate sobrescrevia a posição 2 e destruía o [id]
        // antes que o Laravel pudesse resolvê-lo.
        FormRequest::register('profile', ['form' => 'unique:form_requests,form,[id]']);

        $this->assertSame(
            'unique:form_requests,form,[id]',
            FormRequest::resolve('profile', ['id' => 'the-id'])['rules']['form']
        );
    }

    #[DataProvider('autoAppendProvider')]
    public function test_auto_append_only_fills_an_omitted_except(string $rule, string $expected): void
    {
        FormRequest::register('profile', ['form' => $rule]);

        $this->assertSame($expected, FormRequest::resolve('profile', ['id' => 'the-id'])['rules']['form']);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function autoAppendProvider(): array
    {
        return [
            'except omitido recebe o id' => [
                'unique:authentications,email',
                'unique:authentications,email,the-id',
            ],
            'except explicito preservado' => [
                'unique:authentications,email,99',
                'unique:authentications,email,99',
            ],
            'NULL explicito preservado' => [
                'unique:users,email,NULL,id,deleted_at,NULL',
                'unique:users,email,NULL,id,deleted_at,NULL',
            ],
            'idColumn preservada ao completar' => [
                'unique:authentications,email,,uuid',
                'unique:authentications,email,the-id,uuid',
            ],
            // Sem a posição 1, o id viraria o nome da coluna.
            'coluna omitida vira NULL' => [
                'unique:authentications',
                'unique:authentications,NULL,the-id',
            ],
            'exists nunca recebe o id' => [
                'exists:authentications,id',
                'exists:authentications,id',
            ],
        ];
    }

    public function test_column_omitted_still_validates_against_the_attribute_name(): void
    {
        FormRequestModel::create(['form' => 'clients', 'rules' => ['a' => 'b']]);

        FormRequest::register('profile', ['form' => 'unique:form_requests']);

        $rules = FormRequest::resolve('profile', ['id' => '01a01775-cbbb-7500-8509-01569c5ae17c'])['rules'];

        // 'NULL' faz o Laravel usar o nome do atributo (form) como coluna.
        $this->assertFalse(validator(['form' => 'clients'], $rules)->passes());
        $this->assertTrue(validator(['form' => 'outro'], $rules)->passes());
    }
}
