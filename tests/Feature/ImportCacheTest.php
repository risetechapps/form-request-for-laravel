<?php

namespace RiseTechApps\FormRequest\Tests\Feature;

use RiseTechApps\FormRequest\FormRequest;
use RiseTechApps\FormRequest\Tests\TestCase;

/**
 * Regressão: form-request:import só limpava o cache ao atualizar. Um
 * formulário novo no banco, mas já em cache com as regras da configuração,
 * continuava servindo a versão antiga até o TTL expirar.
 */
class ImportCacheTest extends TestCase
{
    public function test_imported_form_replaces_cached_configuration_rules(): void
    {
        FormRequest::register('clients', ['name' => 'required']);

        $this->assertSame('required', FormRequest::resolve('clients')['rules']['name']);

        $file = tempnam(sys_get_temp_dir(), 'form-request-import');
        file_put_contents($file, json_encode(['clients' => ['rules' => ['name' => 'required|string']]]));

        try {
            $this->artisan('form-request:import', ['file' => $file])->assertExitCode(0);
        } finally {
            unlink($file);
        }

        $this->assertSame('required|string', FormRequest::resolve('clients')['rules']['name']);
    }
}
