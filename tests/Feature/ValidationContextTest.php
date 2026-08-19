<?php

namespace RiseTechApps\FormRequest\Tests\Feature;

use RiseTechApps\FormRequest\FormRequest;
use RiseTechApps\FormRequest\Models\FormRequest as FormRequestModel;
use RiseTechApps\FormRequest\Tests\TestCase;

/**
 * O contexto de validação é dado de interpolação das regras, não filtro de
 * consulta. Regressão: suas chaves eram mescladas no WHERE de form_requests,
 * o que quebrava a resolução de qualquer formulário do banco resolvido com um
 * contexto diferente de 'id'.
 */
class ValidationContextTest extends TestCase
{
    public function test_context_does_not_filter_the_database_lookup(): void
    {
        FormRequestModel::create([
            'form' => 'from_db',
            'rules' => ['ref' => 'required|in:{tenant},other'],
        ]);

        $resolved = FormRequest::resolve('from_db', ['tenant' => 7]);

        $this->assertSame('required|in:7,other', $resolved['rules']['ref'] ?? null);
    }

    public function test_database_and_configuration_agree_on_the_same_context(): void
    {
        $rules = ['ref' => 'required|in:{tenant},other'];

        FormRequest::register('from_config', $rules);
        FormRequestModel::create(['form' => 'from_db', 'rules' => $rules]);

        $this->assertSame(
            FormRequest::resolve('from_config', ['tenant' => 7])['rules'],
            FormRequest::resolve('from_db', ['tenant' => 7])['rules']
        );
    }

    public function test_multiple_context_keys_are_all_interpolated(): void
    {
        FormRequestModel::create([
            'form' => 'multi',
            'rules' => ['ref' => 'required|in:{tenant},{scope}'],
        ]);

        $resolved = FormRequest::resolve('multi', ['tenant' => 7, 'scope' => 'admin']);

        $this->assertSame('required|in:7,admin', $resolved['rules']['ref'] ?? null);
    }

    public function test_id_context_still_adjusts_unique_rules_from_the_database(): void
    {
        FormRequestModel::create([
            'form' => 'with_unique',
            'rules' => ['email' => 'required|unique:clients,email'],
        ]);

        $resolved = FormRequest::resolve('with_unique', ['id' => 5]);

        $this->assertSame('required|unique:clients,email,5', $resolved['rules']['email'] ?? null);
    }

    public function test_database_rules_win_over_configuration_for_the_same_name(): void
    {
        FormRequest::register('shared', ['ref' => 'from-config']);
        FormRequestModel::create(['form' => 'shared', 'rules' => ['ref' => 'from-database']]);

        $this->assertSame('from-database', FormRequest::resolve('shared', ['tenant' => 7])['rules']['ref'] ?? null);
    }
}
