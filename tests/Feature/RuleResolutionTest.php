<?php

namespace RiseTechApps\FormRequest\Tests\Feature;

use RiseTechApps\FormRequest\FormRequest;
use RiseTechApps\FormRequest\Tests\TestCase;

class RuleResolutionTest extends TestCase
{
    public function test_id_placeholder_is_replaced(): void
    {
        FormRequest::register('with_placeholder', [
            'ref' => 'required|in:{id},other',
        ]);

        $resolved = FormRequest::resolve('with_placeholder', ['id' => 5]);

        $this->assertSame('required|in:5,other', $resolved['rules']['ref']);
    }

    public function test_substring_id_in_table_name_is_not_corrupted(): void
    {
        // Regressão: str_replace cru de "id" corrompia nomes como "paid_users".
        FormRequest::register('with_substring', [
            'account' => 'required|unique:paid_users,email',
        ]);

        $resolved = FormRequest::resolve('with_substring', ['id' => 5]);

        // setIdUpdate anexa o id ao unique; o nome da tabela permanece intacto.
        $this->assertSame('required|unique:paid_users,email,5', $resolved['rules']['account']);
    }

    public function test_resolve_without_params_keeps_rules_untouched(): void
    {
        FormRequest::register('plain', [
            'name' => 'required|string|max:255',
        ]);

        $resolved = FormRequest::resolve('plain');

        $this->assertSame('required|string|max:255', $resolved['rules']['name']);
    }

    public function test_forget_clears_definition_and_cache(): void
    {
        FormRequest::register('temp', ['name' => 'required']);
        $this->assertNotEmpty(FormRequest::resolve('temp')['rules']);

        FormRequest::forget('temp');

        $this->assertSame([], FormRequest::resolve('temp')['rules']);
    }
}
