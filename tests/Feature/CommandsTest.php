<?php

namespace RiseTechApps\FormRequest\Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use RiseTechApps\FormRequest\FormRequest;
use RiseTechApps\FormRequest\Models\FormRequest as FormRequestModel;
use RiseTechApps\FormRequest\Tests\TestCase;
use RiseTechApps\FormRequest\ValidationRuleRepository;

class CommandsTest extends TestCase
{
    public function test_list_shows_database_and_configured_forms(): void
    {
        FormRequest::register('from_code', ['name' => 'required']);
        FormRequestModel::create(['form' => 'from_db', 'rules' => ['email' => 'required|email']]);

        $this->artisan('form-request:list')
            ->expectsOutputToContain('from_code config')
            ->expectsOutputToContain('from_db database')
            ->expectsOutputToContain('3 formulários')
            ->assertExitCode(0);
    }

    public function test_list_fails_for_an_unknown_form(): void
    {
        $this->artisan('form-request:list', ['--form' => 'missing'])->assertExitCode(1);
    }

    public function test_export_writes_both_sources_and_import_reads_them_back(): void
    {
        FormRequest::register('from_code', ['name' => 'required'], [], ['description' => 'em código']);
        FormRequestModel::create(['form' => 'from_db', 'rules' => ['email' => 'required'], 'description' => 'no banco']);

        $file = tempnam(sys_get_temp_dir(), 'form-request-export');

        try {
            $this->artisan('form-request:export', ['--file' => $file])->assertExitCode(0);

            $exported = json_decode((string) file_get_contents($file), true);

            $this->assertSame('database', $exported['from_db']['source']);
            $this->assertSame('config', $exported['from_code']['source']);

            FormRequestModel::query()->delete();

            $this->artisan('form-request:import', ['file' => $file])->assertExitCode(0);
        } finally {
            unlink($file);
        }

        $this->assertSame('no banco', FormRequestModel::where('form', 'from_db')->value('description'));
        $this->assertSame('em código', FormRequestModel::where('form', 'from_code')->value('description'));
    }

    public function test_export_fails_when_there_is_nothing_to_export(): void
    {
        $this->artisan('form-request:export', ['--database' => true])->assertExitCode(1);
    }

    public function test_import_skips_existing_forms_unless_forced(): void
    {
        FormRequestModel::create(['form' => 'clients', 'rules' => ['name' => 'required']]);

        $file = tempnam(sys_get_temp_dir(), 'form-request-import');
        file_put_contents($file, json_encode(['clients' => ['rules' => ['name' => 'required|string']]]));

        try {
            $this->artisan('form-request:import', ['file' => $file])->assertExitCode(0);
            $this->assertSame(['name' => 'required'], FormRequestModel::where('form', 'clients')->first()?->rules);

            $this->artisan('form-request:import', ['file' => $file, '--force' => true])->assertExitCode(0);
            $this->assertSame(['name' => 'required|string'], FormRequestModel::where('form', 'clients')->first()?->rules);
        } finally {
            unlink($file);
        }
    }

    public function test_import_rejects_missing_and_invalid_files(): void
    {
        $this->artisan('form-request:import', ['file' => '/nonexistent/forms.json'])->assertExitCode(1);

        $file = tempnam(sys_get_temp_dir(), 'form-request-import');
        file_put_contents($file, '{invalid');

        try {
            $this->artisan('form-request:import', ['file' => $file])->assertExitCode(1);
        } finally {
            unlink($file);
        }
    }

    public function test_stats_counts_both_sources(): void
    {
        FormRequestModel::create(['form' => 'from_db', 'rules' => ['name' => 'required']]);

        $this->artisan('form-request:stats', ['--detailed' => true])
            ->expectsOutputToContain('Formulários no banco: 1')
            ->expectsOutputToContain('from_db')
            ->assertExitCode(0);
    }

    public function test_validate_rules_accepts_valid_rules_without_false_positives(): void
    {
        FormRequest::register('valid', [
            'a' => 'sometimes|required|string',
            'b' => 'nullable|required_if:a,x',
            'c' => 'required|decimal:2|lowercase|ulid',
            'd' => ['required', 'pix_key', 'ncm', 'existsJson:t,c.k'],
        ]);

        $this->artisan('form-request:validate-rules')
            ->expectsOutputToContain('Todas as regras estão válidas')
            ->assertExitCode(0);
    }

    public function test_validate_rules_reports_unknown_rules_and_missing_parameters(): void
    {
        FormRequestModel::create(['form' => 'broken', 'rules' => ['a' => 'required|requird', 'b' => 'unique']]);

        $this->artisan('form-request:validate-rules', ['--form' => 'broken'])
            ->expectsOutputToContain('broken.a: The rule "requird" is not registered.')
            ->expectsOutputToContain('broken.b: The rule "unique" requires parameters.')
            ->assertExitCode(1);
    }

    public function test_validate_rules_warns_about_nullable_with_required(): void
    {
        FormRequest::register('contradictory', ['a' => 'nullable|required']);

        $this->artisan('form-request:validate-rules')
            ->expectsOutputToContain("contradictory.a: 'nullable' com 'required' são contraditórios")
            ->assertExitCode(0);
    }

    public function test_warm_cache_resolves_every_form(): void
    {
        FormRequestModel::create(['form' => 'from_db', 'rules' => ['name' => 'required']]);

        $this->artisan('form-request:warm-cache')
            ->expectsOutputToContain('from_db (database)')
            ->expectsOutputToContain('form_request (config)')
            ->assertExitCode(0);

        // Com o cache quente, mudar o banco sem invalidar não altera o resolvido.
        FormRequestModel::where('form', 'from_db')->update(['rules' => json_encode(['name' => 'changed'])]);

        $this->assertSame('required', FormRequest::resolve('from_db')['rules']['name']);
    }

    public function test_clear_cache_invalidates_one_or_all_forms(): void
    {
        FormRequestModel::create(['form' => 'one', 'rules' => ['name' => 'required']]);
        FormRequestModel::create(['form' => 'two', 'rules' => ['name' => 'required']]);
        FormRequest::resolve('one');
        FormRequest::resolve('two');

        FormRequestModel::query()->update(['rules' => json_encode(['name' => 'changed'])]);

        $this->artisan('form-request:clear-cache', ['form' => 'one'])->assertExitCode(0);
        $this->assertSame('changed', FormRequest::resolve('one')['rules']['name']);
        $this->assertSame('required', FormRequest::resolve('two')['rules']['name']);

        $this->artisan('form-request:clear-cache', ['--all' => true])->assertExitCode(0);
        $this->assertSame('changed', FormRequest::resolve('two')['rules']['name']);
    }

    public function test_clear_cache_requires_a_target(): void
    {
        $this->artisan('form-request:clear-cache')->assertExitCode(1);
    }

    public function test_migrate_runs_the_package_migrations(): void
    {
        $this->artisan('migrate:reset')->assertExitCode(0);

        $this->artisan('form-request:migrate')->assertExitCode(0);

        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasTable('form_requests'));
    }

    public function test_list_shows_a_database_override_once_with_its_real_source(): void
    {
        FormRequest::register('clients', ['name' => 'required']);
        FormRequestModel::create(['form' => 'clients', 'rules' => ['email' => 'required|email']]);

        $forms = $this->artisanJson('form-request:list', ['--form' => 'clients', '--json' => true]);

        $this->assertCount(1, $forms);
        $this->assertSame('database', $forms[0]['source']);
        $this->assertTrue($forms[0]['overrides_config']);
        $this->assertSame(['email'], array_keys($forms[0]['fields']));

        $this->artisan('form-request:list', ['--form' => 'clients'])
            ->expectsOutputToContain('clients database (sobrescreve config)')
            ->assertExitCode(0);
    }

    public function test_list_shows_the_translated_message(): void
    {
        FormRequestModel::create(['form' => 'clients', 'rules' => ['email' => 'required|email']]);

        $forms = $this->artisanJson('form-request:list', ['--form' => 'clients', '--json' => true]);

        $this->assertSame(
            ['rule' => 'required', 'custom' => false, 'message' => 'The Email field is required.'],
            $forms[0]['fields']['email'][0]
        );
    }

    public function test_list_shows_messages_only_when_asked(): void
    {
        FormRequestModel::create([
            'form' => 'clients',
            'rules' => ['document' => 'nullable|cpf'],
            'messages' => ['document.cpf' => 'CPF inválido.'],
        ]);

        $this->artisan('form-request:list', ['--form' => 'clients'])
            ->doesntExpectOutputToContain('CPF inválido.')
            ->assertExitCode(0);

        // nullable não gera mensagem e não aparece como marcador cru.
        $this->artisan('form-request:list', ['--form' => 'clients', '--messages' => true])
            ->expectsOutputToContain('↳ cpf  CPF inválido.')
            ->doesntExpectOutputToContain('document.nullable')
            ->assertExitCode(0);

        $forms = $this->artisanJson('form-request:list', ['--form' => 'clients', '--json' => true]);
        $this->assertTrue($forms[0]['fields']['document'][1]['custom']);
        $this->assertSame('', $forms[0]['fields']['document'][0]['message']);
    }

    public function test_list_missing_shows_only_rules_without_custom_message(): void
    {
        FormRequestModel::create([
            'form' => 'clients',
            'rules' => ['name' => 'required|min:3', 'document' => 'nullable|cpf'],
            'messages' => ['name.required' => 'Informe o nome.', 'document.cpf' => 'CPF inválido.'],
        ]);

        $forms = $this->artisanJson('form-request:list', ['--form' => 'clients', '--missing' => true, '--json' => true], 1);

        // document: cpf é personalizada e nullable não gera mensagem.
        $this->assertSame(['name'], array_keys($forms[0]['fields']));
        $this->assertSame(['min:3'], array_column($forms[0]['fields']['name'], 'rule'));

        $this->artisan('form-request:list', ['--form' => 'clients', '--missing' => true])
            ->expectsOutputToContain('1 regra sem mensagem personalizada')
            ->assertExitCode(1);
    }

    public function test_list_missing_passes_when_every_rule_has_a_custom_message(): void
    {
        FormRequestModel::create([
            'form' => 'clients',
            'rules' => ['name' => 'bail|required'],
            'messages' => ['name.required' => 'Informe o nome.'],
        ]);

        $this->artisan('form-request:list', ['--form' => 'clients', '--missing' => true])
            ->expectsOutputToContain('Todas as regras têm mensagem personalizada.')
            ->assertExitCode(0);

        $this->artisan('form-request:list', ['--form' => 'missing', '--missing' => true])->assertExitCode(1);
    }

    public function test_list_missing_ignores_the_package_form_unless_named(): void
    {
        $this->artisan('form-request:list', ['--missing' => true])
            ->expectsOutputToContain('Todas as regras têm mensagem personalizada.')
            ->assertExitCode(0);

        $this->artisan('form-request:list', ['--missing' => true, '--form' => 'form_request'])
            ->expectsOutputToContain('form_request config')
            ->assertExitCode(1);
    }

    public function test_list_fills_size_messages_for_the_field_type(): void
    {
        FormRequestModel::create(['form' => 'clients', 'rules' => ['name' => 'required|min:3', 'age' => 'numeric|max:120']]);

        $forms = $this->artisanJson('form-request:list', ['--form' => 'clients', '--json' => true]);

        $this->assertSame('The Name field must be at least 3 characters.', $forms[0]['fields']['name'][1]['message']);
        $this->assertSame('The Age field must not be greater than 120.', $forms[0]['fields']['age'][1]['message']);
    }

    public function test_list_field_filter_matches_exact_names_and_wildcards(): void
    {
        FormRequestModel::create(['form' => 'orders', 'rules' => [
            'name' => 'required',
            'items.*.name' => 'required',
            'company' => 'required',
        ]]);

        $exact = $this->artisanJson('form-request:list', ['--field' => 'name', '--json' => true]);
        $this->assertSame(['name'], array_keys($exact[0]['fields']));

        $wildcard = $this->artisanJson('form-request:list', ['--field' => 'items.*', '--json' => true]);
        $this->assertSame(['items.*.name'], array_keys($wildcard[0]['fields']));

        $this->artisan('form-request:list', ['--field' => 'missing'])->assertExitCode(1);
    }

    public function test_export_with_both_source_options_exports_everything_to_stdout(): void
    {
        FormRequest::register('from_code', ['name' => 'required'], [], ['description' => 'em código', 'owner' => 'team']);
        FormRequestModel::create(['form' => 'from_db', 'rules' => ['email' => 'required']]);

        $exported = $this->artisanJson('form-request:export', ['--database' => true, '--config' => true]);

        $this->assertSame('database', $exported['from_db']['source']);
        $this->assertSame(['description' => 'em código', 'data' => ['owner' => 'team']], [
            'description' => $exported['from_code']['description'],
            'data' => $exported['from_code']['data'],
        ]);
    }

    public function test_import_rejects_the_whole_file_when_a_form_has_unknown_rules(): void
    {
        $file = $this->jsonFile([
            'valid_form' => ['rules' => ['name' => 'required']],
            'broken_form' => ['rules' => ['name' => 'required|requird']],
        ]);

        try {
            $this->artisan('form-request:import', ['file' => $file])
                ->expectsOutputToContain('broken_form: name: The rule "requird" is not registered.')
                ->assertExitCode(1);
        } finally {
            unlink($file);
        }

        $this->assertSame(0, FormRequestModel::count());
    }

    public function test_import_rejects_a_json_list(): void
    {
        $file = $this->jsonFile([['rules' => ['name' => 'required']]]);

        try {
            $this->artisan('form-request:import', ['file' => $file])->assertExitCode(1);
        } finally {
            unlink($file);
        }

        $this->assertSame(0, FormRequestModel::count());
    }

    public function test_import_dry_run_writes_nothing(): void
    {
        FormRequestModel::create(['form' => 'clients', 'rules' => ['name' => 'required']]);

        $file = $this->jsonFile([
            'clients' => ['rules' => ['name' => 'required|string']],
            'orders' => ['rules' => ['total' => 'required|numeric']],
        ]);

        try {
            $this->artisan('form-request:import', ['file' => $file, '--dry-run' => true, '--force' => true])
                ->expectsOutputToContain("'clients' - Seria atualizado")
                ->expectsOutputToContain("'orders' - Seria criado")
                ->assertExitCode(0);
        } finally {
            unlink($file);
        }

        $this->assertSame(1, FormRequestModel::count());
        $this->assertSame(['name' => 'required'], FormRequestModel::where('form', 'clients')->first()?->rules);
    }

    public function test_validate_rules_checks_a_form_defined_only_in_code(): void
    {
        FormRequest::register('code_only', ['a' => 'requird']);

        $this->artisan('form-request:validate-rules', ['--form' => 'code_only'])
            ->expectsOutputToContain('[config] code_only.a: The rule "requird" is not registered.')
            ->assertExitCode(1);

        $this->artisan('form-request:validate-rules', ['--form' => 'missing'])->assertExitCode(1);
    }

    public function test_warm_cache_accepts_a_form_defined_only_in_code(): void
    {
        FormRequest::register('code_only', ['name' => 'required']);

        $this->artisan('form-request:warm-cache', ['--form' => 'code_only'])
            ->expectsOutputToContain('code_only (config)')
            ->assertExitCode(0);
    }

    public function test_warm_cache_replaces_stale_entries(): void
    {
        FormRequestModel::create(['form' => 'clients', 'rules' => ['name' => 'required']]);
        FormRequest::resolve('clients');

        FormRequestModel::where('form', 'clients')->update(['rules' => json_encode(['name' => 'required|string'])]);

        $this->artisan('form-request:warm-cache', ['--form' => 'clients'])->assertExitCode(0);

        $this->assertSame('required|string', FormRequest::resolve('clients')['rules']['name']);
    }

    public function test_cache_commands_report_when_the_cache_is_disabled(): void
    {
        config(['rules.cache.enabled' => false]);
        $this->app->forgetInstance(ValidationRuleRepository::class);

        $this->artisan('form-request:warm-cache')
            ->expectsOutputToContain('Cache desabilitado')
            ->assertExitCode(0);

        $this->artisan('form-request:clear-cache', ['--all' => true])
            ->expectsOutputToContain('Cache desabilitado')
            ->assertExitCode(0);
    }

    public function test_stats_lists_every_registered_validator_and_cached_forms(): void
    {
        config(['rules.validators' => ['my_document' => \RiseTechApps\FormRequest\Services\Validator\validateCPF::class]]);
        FormRequestModel::create(['form' => 'clients', 'rules' => ['name' => 'required']]);
        FormRequest::resolve('clients');

        $this->artisan('form-request:stats', ['--detailed' => true])
            ->expectsOutputToContain('Formulários em cache: 1 de 2')
            ->expectsOutputToContain('pix_key')
            ->expectsOutputToContain('my_document')
            ->assertExitCode(0);
    }

    /**
     * Roda o comando e decodifica a saída JSON.
     *
     * @param array<string, mixed> $parameters
     * @return array<mixed>
     */
    private function artisanJson(string $command, array $parameters, int $exitCode = 0): array
    {
        $this->assertSame($exitCode, Artisan::call($command, $parameters));

        $decoded = json_decode(Artisan::output(), true);
        $this->assertIsArray($decoded, Artisan::output());

        return $decoded;
    }

    /**
     * @param array<mixed> $data
     */
    private function jsonFile(array $data): string
    {
        $file = (string) tempnam(sys_get_temp_dir(), 'form-request-import');
        file_put_contents($file, json_encode($data));

        return $file;
    }
}
