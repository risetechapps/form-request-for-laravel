<?php

namespace RiseTechApps\FormRequest\Tests\Feature;

use Illuminate\Validation\Rule;
use RiseTechApps\FormRequest\FormRequest;
use RiseTechApps\FormRequest\Tests\TestCase;

/**
 * Regressão: o contexto só era aplicado a regras em string. Um campo
 * declarado como array ficava sem o id no unique e sem os placeholders.
 */
class ArrayRuleContextTest extends TestCase
{
    public function test_id_is_applied_to_unique_in_array_rules(): void
    {
        FormRequest::register('clients', ['email' => ['required', 'unique:clients,email']]);

        $this->assertSame(
            ['required', 'unique:clients,email,5'],
            FormRequest::resolve('clients', ['id' => 5])['rules']['email'] ?? null
        );
    }

    public function test_explicit_except_is_preserved_in_array_rules(): void
    {
        FormRequest::register('clients', ['email' => ['required', 'unique:clients,email,[id]']]);

        $this->assertSame(
            ['required', 'unique:clients,email,[id]'],
            FormRequest::resolve('clients', ['id' => 5])['rules']['email'] ?? null
        );
    }

    public function test_placeholders_are_resolved_in_array_rules(): void
    {
        FormRequest::register('clients', ['ref' => ['required', 'in:{tenant},other']]);

        $this->assertSame(
            ['required', 'in:7,other'],
            FormRequest::resolve('clients', ['tenant' => 7])['rules']['ref'] ?? null
        );
    }

    public function test_rule_objects_in_arrays_are_kept_intact(): void
    {
        $rule = Rule::in(['a', 'b']);

        FormRequest::register('clients', ['ref' => ['required', $rule]]);

        $this->assertSame($rule, FormRequest::resolve('clients', ['id' => 5])['rules']['ref'][1] ?? null);
    }
}
