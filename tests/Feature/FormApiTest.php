<?php

namespace RiseTechApps\FormRequest\Tests\Feature;

use Illuminate\Support\Facades\Log;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use RiseTechApps\FormRequest\FormRequest;
use RiseTechApps\FormRequest\Http\Controllers\FormController;
use RiseTechApps\FormRequest\Models\FormRequest as FormRequestModel;
use RiseTechApps\FormRequest\Services\FormManager;
use RiseTechApps\FormRequest\Tests\ApiTestCase;
use RuntimeException;

class FormApiTest extends ApiTestCase
{
    public function test_index_lists_stored_forms(): void
    {
        FormRequestModel::create(['form' => 'clients', 'rules' => ['name' => 'required']]);

        $this->getJson('/api/forms?per_page=0')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'clients');
    }

    public function test_index_reports_pagination_metadata(): void
    {
        foreach (range(1, 12) as $i) {
            FormRequestModel::create(['form' => "form_{$i}", 'rules' => ['name' => 'required']]);
        }

        $this->getJson('/api/forms?per_page=5&page=3')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta', [
                'current_page' => 3,
                'last_page' => 3,
                'per_page' => 5,
                'total' => 12,
                'from' => 11,
                'to' => 12,
            ]);
    }

    public function test_index_without_pagination_has_no_metadata(): void
    {
        FormRequestModel::create(['form' => 'clients', 'rules' => ['name' => 'required']]);

        $this->getJson('/api/forms?per_page=0')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonMissingPath('meta');
    }

    public function test_index_keeps_metadata_alongside_configured_forms(): void
    {
        $this->getJson('/api/forms?include_configured=1')
            ->assertOk()
            ->assertJsonPath('meta.total', 0)
            ->assertJsonStructure(['data', 'meta', 'configured']);
    }

    public function test_index_can_include_configured_forms(): void
    {
        FormRequest::register('from_code', ['name' => 'required']);

        $configured = $this->getJson('/api/forms?include_configured=1')
            ->assertOk()
            ->json('configured');

        $this->assertContains('from_code', array_column($configured, 'form'));
    }

    public function test_store_persists_a_form(): void
    {
        $this
            ->postJson('/api/forms', ['form' => 'clients', 'rules' => ['name' => 'required|max:100']])
            ->assertOk()
            ->assertJsonPath('data.name', 'clients');

        $this->assertSame(['name' => 'required|max:100'], FormRequestModel::where('form', 'clients')->first()?->rules);
    }

    public function test_store_requires_permission(): void
    {
        $this->user->granted = [];

        $this
            ->postJson('/api/forms', ['form' => 'clients', 'rules' => ['name' => 'required']])
            ->assertForbidden();

        $this->assertSame(0, FormRequestModel::count());
    }

    public function test_store_is_forbidden_when_the_user_model_has_no_permission_check(): void
    {
        $this->actingAs(new \Illuminate\Foundation\Auth\User())
            ->postJson('/api/forms', ['form' => 'clients', 'rules' => ['name' => 'required']])
            ->assertForbidden();
    }

    public function test_store_rejects_unknown_rules(): void
    {
        $this
            ->postJson('/api/forms', [
                'form' => 'clients',
                'rules' => ['name' => 'required|requird', 'email' => ['required', 'unique']],
            ])
            ->assertUnprocessable()
            ->assertJsonPath('errors', [
                'rules.name' => ['The rule "requird" is not registered.'],
                'rules.email' => ['The rule "unique" requires parameters.'],
            ]);

        $this->assertSame(0, FormRequestModel::count());
    }

    public function test_store_accepts_package_and_application_validators(): void
    {
        $this
            ->postJson('/api/forms', [
                'form' => 'clients',
                'rules' => ['doc' => 'required|cpf', 'key' => ['nullable', 'pix_key:email'], 'tags' => 'uniqueJson:t,c.k'],
            ])
            ->assertOk();
    }

    public function test_store_rejects_non_string_rule_definitions(): void
    {
        $this
            ->postJson('/api/forms', ['form' => 'clients', 'rules' => ['name' => ['required', 10]]])
            ->assertUnprocessable()
            ->assertJsonPath('errors', ['rules.name' => ['Rules must be a string or a list of strings.']]);
    }

    public function test_show_returns_a_form_or_not_found(): void
    {
        $form = FormRequestModel::create(['form' => 'clients', 'rules' => ['name' => 'required']]);

        $this->getJson("/api/forms/{$form->id}")->assertOk()->assertJsonPath('data.name', 'clients');
        $this->getJson('/api/forms/00000000-0000-0000-0000-000000000000')->assertNotFound();
    }

    public function test_update_changes_rules_and_keeps_its_own_name_unique(): void
    {
        $form = FormRequestModel::create(['form' => 'clients', 'rules' => ['name' => 'required']]);

        // O próprio nome não conflita com o unique graças ao id da rota.
        $this
            ->putJson("/api/forms/{$form->id}", ['form' => 'clients', 'rules' => ['name' => 'required|min:3']])
            ->assertOk()
            ->assertJsonPath('data.rules', ['name' => 'required|min:3']);
    }

    public function test_update_rejects_unknown_rules(): void
    {
        $form = FormRequestModel::create(['form' => 'clients', 'rules' => ['name' => 'required']]);

        $this
            ->putJson("/api/forms/{$form->id}", ['form' => 'clients', 'rules' => ['name' => 'nope']])
            ->assertUnprocessable();

        $this->assertSame(['name' => 'required'], $form->refresh()->rules);
    }

    public function test_update_invalidates_the_rule_cache(): void
    {
        $form = FormRequestModel::create(['form' => 'clients', 'rules' => ['name' => 'required']]);
        FormRequest::resolve('clients');

        $this
            ->putJson("/api/forms/{$form->id}", ['form' => 'clients', 'rules' => ['name' => 'required|min:3']])
            ->assertOk();

        $this->assertSame('required|min:3', FormRequest::resolve('clients')['rules']['name']);
    }

    public function test_destroy_removes_the_form(): void
    {
        $form = FormRequestModel::create(['form' => 'clients', 'rules' => ['name' => 'required']]);

        $this->deleteJson("/api/forms/{$form->id}")->assertOk();

        $this->assertSame(0, FormRequestModel::count());
    }

    public function test_responses_keep_the_api_envelope(): void
    {
        $form = FormRequestModel::create(['form' => 'clients', 'rules' => ['name' => 'required']]);

        $this->getJson("/api/forms/{$form->id}")
            ->assertStatus(200)
            ->assertJson(['success' => true, 'code' => 200, 'message' => 'Operation completed successfully.']);

        // Sem data, a chave é omitida, como fazia a macro jsonSuccess.
        $this->deleteJson("/api/forms/{$form->id}")
            ->assertStatus(200)
            ->assertExactJson(['success' => true, 'code' => 200, 'message' => 'Operation completed successfully.']);

        $this->getJson("/api/forms/{$form->id}")
            ->assertStatus(404)
            ->assertExactJson(['success' => false, 'code' => 404, 'message' => 'Form request not found']);
    }

    public function test_unexpected_failure_returns_gone_and_is_logged(): void
    {
        $this->app->instance(FormManager::class, Mockery::mock(FormManager::class, function ($mock) {
            $mock->shouldReceive('list')->andThrow(new RuntimeException('database down'));
        }));

        Log::shouldReceive('channel')->once()->with(null)->andReturnSelf();
        Log::shouldReceive('error')->once()->withArgs(
            fn(string $message, array $context) => $message === 'Error loading datatable'
                && $context['exception'] instanceof RuntimeException
                && $context['method'] === 'GET'
        );

        $this->getJson('/api/forms')
            ->assertStatus(410)
            ->assertExactJson(['success' => false, 'code' => 410, 'message' => 'Error loading datatable']);
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function protectedEndpoints(): array
    {
        return [
            'index' => ['index', 'get', '/api/forms'],
            'show' => ['show', 'get', '/api/forms/{id}'],
            'store' => ['store', 'post', '/api/forms'],
            'update' => ['update', 'put', '/api/forms/{id}'],
            'destroy' => ['destroy', 'delete', '/api/forms/{id}'],
            'schema' => ['schema', 'get', '/api/forms/clients/schema'],
        ];
    }

    #[DataProvider('protectedEndpoints')]
    public function test_endpoint_requires_its_own_permission(string $action, string $method, string $uri): void
    {
        $form = FormRequestModel::create(['form' => 'clients', 'rules' => ['name' => 'required']]);

        // Todas as permissões menos a da ação: nenhuma cobre a outra.
        $this->user->granted = array_values(array_diff(
            $this->user->granted,
            [FormController::class . '@' . $action]
        ));

        $this->json($method, str_replace('{id}', $form->id, $uri), ['form' => 'clients', 'rules' => ['name' => 'required']])
            ->assertForbidden()
            ->assertExactJson(['success' => false, 'message' => 'Permission denied.']);

        $this->assertSame(['name' => 'required'], $form->refresh()->rules);
    }

    #[DataProvider('protectedEndpoints')]
    public function test_endpoint_rejects_guests(string $action, string $method, string $uri): void
    {
        $form = FormRequestModel::create(['form' => 'clients', 'rules' => ['name' => 'required']]);

        $this->app['auth']->guard()->forgetUser();

        $this->json($method, str_replace('{id}', $form->id, $uri), ['form' => 'clients', 'rules' => ['name' => 'required']])
            ->assertForbidden();

        $this->assertSame(1, FormRequestModel::count());
    }
}
