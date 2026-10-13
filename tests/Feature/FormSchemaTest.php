<?php

namespace RiseTechApps\FormRequest\Tests\Feature;

use Illuminate\Validation\Rule;
use RiseTechApps\FormRequest\FormRequest;
use RiseTechApps\FormRequest\Models\FormRequest as FormRequestModel;
use RiseTechApps\FormRequest\Tests\ApiTestCase;

class FormSchemaTest extends ApiTestCase
{
    public function test_describes_each_rule_with_parameters_and_message(): void
    {
        FormRequest::register('clients', ['name' => 'required|string|max:100']);

        $this->getJson('/api/forms/clients/schema')
            ->assertOk()
            ->assertJsonPath('data.form', 'clients')
            ->assertJsonPath('data.fields.name.required', true)
            ->assertJsonPath('data.fields.name.rules.0', [
                'rule' => 'required',
                'parameters' => [],
                'server_only' => false,
                'message' => 'The Name field is required.',
            ])
            ->assertJsonPath('data.fields.name.rules.2.rule', 'max')
            ->assertJsonPath('data.fields.name.rules.2.parameters', ['100']);
    }

    public function test_reads_forms_stored_in_the_database(): void
    {
        FormRequestModel::create(['form' => 'clients', 'rules' => ['doc' => ['nullable', 'cpf']]]);

        $this->getJson('/api/forms/clients/schema')
            ->assertOk()
            ->assertJsonPath('data.fields.doc.required', false)
            ->assertJsonPath('data.fields.doc.rules.1.message', 'The Doc field must be a valid CPF.');
    }

    public function test_database_rules_hide_their_parameters(): void
    {
        FormRequest::register('clients', [
            'email' => 'required|unique:clients,email',
            'tag' => 'exists:tags,id',
            'meta' => 'uniqueJson:clients,data.key',
        ]);

        $fields = $this->getJson('/api/forms/clients/schema')->assertOk()->json('data.fields');

        $this->assertSame(['rule' => 'unique', 'parameters' => [], 'server_only' => true], array_diff_key($fields['email']['rules'][1], ['message' => 0]));
        $this->assertSame([], $fields['tag']['rules'][0]['parameters']);
        $this->assertTrue($fields['meta']['rules'][0]['server_only']);
    }

    public function test_regex_parameters_are_not_split_on_commas(): void
    {
        FormRequest::register('clients', ['code' => ['regex:/^[a-z]{2,4}$/']]);

        $this->getJson('/api/forms/clients/schema')
            ->assertJsonPath('data.fields.code.rules.0.parameters', ['/^[a-z]{2,4}$/']);
    }

    public function test_rule_objects_are_described_when_they_have_a_textual_form(): void
    {
        FormRequest::register('clients', ['kind' => ['required', Rule::in(['a', 'b'])], 'other' => [fn() => true]]);

        $fields = $this->getJson('/api/forms/clients/schema')->assertOk()->json('data.fields');

        $this->assertSame('in', $fields['kind']['rules'][1]['rule']);
        $this->assertSame(['a', 'b'], $fields['kind']['rules'][1]['parameters']);
        $this->assertSame(['rule' => 'custom', 'parameters' => [], 'server_only' => true, 'message' => null], $fields['other']['rules'][0]);
    }

    public function test_unknown_form_is_not_found(): void
    {
        $this->getJson('/api/forms/missing/schema')->assertNotFound();
    }
}
