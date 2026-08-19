<?php

namespace RiseTechApps\FormRequest\Tests\Feature;

use Illuminate\Support\ServiceProvider;
use RiseTechApps\FormRequest\FormDefinitions\FormRegistry;
use RiseTechApps\FormRequest\FormRequest;
use RiseTechApps\FormRequest\FormRequestServiceProvider;
use RiseTechApps\FormRequest\Tests\TestCase;

/**
 * Provider da aplicação que registra formulários no próprio boot(), como
 * documentado no README. Roda antes do callback booted() do pacote.
 */
class RegistersFormsProvider extends ServiceProvider
{
    public function boot(): void
    {
        FormRequest::register('clients', ['name' => 'required|string|max:255']);
    }
}

class RegistrationTimingTest extends TestCase
{
    /**
     * @param \Illuminate\Foundation\Application $app
     * @return array<int, class-string>
     */
    #[\Override]
    protected function getPackageProviders($app): array
    {
        return [
            FormRequestServiceProvider::class,
            RegistersFormsProvider::class,
        ];
    }

    public function test_form_registry_is_a_singleton(): void
    {
        $this->assertSame(app(FormRegistry::class), app(FormRegistry::class));
    }

    public function test_registration_from_a_provider_boot_survives_the_booted_callback(): void
    {
        // Regressão: o callback booted() substituía o binding do FormRegistry,
        // descartando tudo que a aplicação tivesse registrado antes.
        $this->assertTrue(app(FormRegistry::class)->has('clients'));
    }

    public function test_rules_registered_from_a_provider_boot_are_resolvable(): void
    {
        $this->assertSame(
            'required|string|max:255',
            FormRequest::resolve('clients')['rules']['name'] ?? null
        );
    }

    public function test_package_own_definitions_are_still_loaded(): void
    {
        // O schema interno do CRUD vem do RulesRegistry e não pode se perder
        // ao passar de instance() para registerMany().
        $this->assertTrue(app(FormRegistry::class)->has('form_request'));
    }
}
