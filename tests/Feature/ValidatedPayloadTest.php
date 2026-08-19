<?php

namespace RiseTechApps\FormRequest\Tests\Feature;

use Illuminate\Http\Request;
use RiseTechApps\FormRequest\Http\Requests\DynamicFormRequest;
use RiseTechApps\FormRequest\Models\FormRequest as FormRequestModel;
use RiseTechApps\FormRequest\Services\FormManager;
use RiseTechApps\FormRequest\Tests\TestCase;

/**
 * Form request equivalente ao Store/Update do pacote, sem a checagem de
 * permissão — FormController não é carregável fora de uma aplicação real,
 * porque estende App\Http\Controllers\Controller.
 */
class SchemaFormRequest extends DynamicFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    #[\Override]
    protected function formKey(): string
    {
        return 'form_request';
    }
}

class ValidatedPayloadTest extends TestCase
{
    /**
     * @param array<string, mixed> $payload
     */
    private function requestFor(array $payload): SchemaFormRequest
    {
        $this->app->instance('request', Request::create('/forms', 'POST', $payload));

        return $this->app->make(SchemaFormRequest::class);
    }

    public function test_validated_drops_input_no_rule_covers(): void
    {
        $request = $this->requestFor([
            'form' => 'clients',
            'rules' => ['name' => 'required'],
            'id' => '00000000-0000-4000-8000-000000000000',
            'created_at' => '1999-01-01 00:00:00',
            'unexpected' => 'x',
        ]);

        $this->assertSame(['form', 'rules'], array_keys($request->validated()));
    }

    public function test_validation_data_still_carries_everything(): void
    {
        // Contraste explícito: é por isso que o controller não pode usá-lo.
        $request = $this->requestFor([
            'form' => 'clients',
            'rules' => ['name' => 'required'],
            'unexpected' => 'x',
        ]);

        $this->assertContains('unexpected', array_keys($request->validationData()));
        $this->assertNotContains('unexpected', array_keys($request->validated()));
    }

    public function test_a_weakened_schema_stops_persisting_unvalidated_columns(): void
    {
        // O CRUD pode reescrever o próprio schema: uma linha 'form_request' no
        // banco vence as regras declaradas em código. Com validated(), perder a
        // regra passa a significar perder a escrita.
        app(FormManager::class)->create([
            'form' => 'form_request',
            'rules' => ['form' => 'required|string'],
        ]);

        $request = $this->requestFor([
            'form' => 'clients',
            'rules' => ['name' => 'required'],
            'description' => 'sem regra',
            'data' => ['sem' => 'regra'],
        ]);

        $validated = $request->validated();

        $this->assertSame(['form'], array_keys($validated));

        $created = app(FormManager::class)->create($validated);

        $this->assertNull($created->description);
        $this->assertNull($created->data);
    }

    public function test_validated_payload_persists_the_expected_columns(): void
    {
        $request = $this->requestFor([
            'form' => 'clients',
            'rules' => ['name' => 'required'],
            'description' => 'descricao valida',
            'unexpected' => 'x',
        ]);

        $created = app(FormManager::class)->create($request->validated());

        $this->assertSame('clients', $created->form);
        $this->assertSame(['name' => 'required'], $created->rules);
        $this->assertSame('descricao valida', $created->description);
        $this->assertFalse(array_key_exists('unexpected', $created->getAttributes()));
        $this->assertSame(1, FormRequestModel::query()->where('form', 'clients')->count());
    }
}
