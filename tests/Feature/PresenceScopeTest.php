<?php

namespace RiseTechApps\FormRequest\Tests\Feature;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Validator;
use RiseTechApps\FormRequest\FormRequest;
use RiseTechApps\FormRequest\Tests\TestCase;
use RiseTechApps\FormRequest\Validation\PresenceScopeRegistry;
use RiseTechApps\FormRequest\Validation\ScopedPresenceVerifier;

class PresenceScopeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('authentications', function (Blueprint $table) {
            $table->increments('id');
            $table->string('email');
            $table->unsignedInteger('tenant_id');
            $table->timestamp('deleted_at')->nullable();
        });

        DB::table('authentications')->insert([
            ['email' => 'user@teste.com', 'tenant_id' => 1, 'deleted_at' => null],
            ['email' => 'other@teste.com', 'tenant_id' => 2, 'deleted_at' => null],
            ['email' => 'removed@teste.com', 'tenant_id' => 1, 'deleted_at' => '2026-01-01 00:00:00'],
        ]);
    }

    protected function tearDown(): void
    {
        $this->app->make(PresenceScopeRegistry::class)->flush();

        parent::tearDown();
    }

    public function test_package_replaces_the_default_presence_verifier(): void
    {
        $this->assertInstanceOf(ScopedPresenceVerifier::class, $this->app['validation.presence']);
    }

    public function test_unique_without_scope_sees_every_tenant(): void
    {
        $this->assertTrue($this->fails('unique:authentications,email', 'user@teste.com'));
    }

    public function test_scope_narrows_unique_to_the_current_tenant(): void
    {
        FormRequest::presenceScope('authentications', fn(Builder $query) => $query->where('tenant_id', 2));

        // O e-mail existe, mas pertence ao tenant 1, então continua disponível.
        $this->assertFalse($this->fails('unique:authentications,email', 'user@teste.com'));

        // Já o e-mail do próprio tenant permanece bloqueado.
        $this->assertTrue($this->fails('unique:authentications,email', 'other@teste.com'));
    }

    public function test_scope_also_applies_to_exists(): void
    {
        FormRequest::presenceScope('authentications', fn(Builder $query) => $query->where('tenant_id', 2));

        $this->assertTrue($this->fails('exists:authentications,email', 'user@teste.com'));
        $this->assertFalse($this->fails('exists:authentications,email', 'other@teste.com'));
    }

    public function test_wildcard_scope_reaches_every_table(): void
    {
        FormRequest::presenceScopeAll(fn(Builder $query) => $query->whereNull('deleted_at'));

        // O registro existe, mas está removido logicamente.
        $this->assertFalse($this->fails('unique:authentications,email', 'removed@teste.com'));
    }

    public function test_scopes_stack_from_wildcard_and_table(): void
    {
        FormRequest::presenceScopeAll(fn(Builder $query) => $query->whereNull('deleted_at'));
        FormRequest::presenceScope('authentications', fn(Builder $query) => $query->where('tenant_id', 1));

        $this->assertTrue($this->fails('unique:authentications,email', 'user@teste.com'));
        $this->assertFalse($this->fails('unique:authentications,email', 'other@teste.com'));
        $this->assertFalse($this->fails('unique:authentications,email', 'removed@teste.com'));
    }

    public function test_scope_receives_the_table_name(): void
    {
        $seen = null;

        FormRequest::presenceScopeAll(function (Builder $query, string $table) use (&$seen) {
            $seen = $table;
        });

        $this->fails('unique:authentications,email', 'user@teste.com');

        $this->assertSame('authentications', $seen);
    }

    public function test_scope_is_evaluated_per_query(): void
    {
        $tenant = 2;

        FormRequest::presenceScope('authentications', function (Builder $query) use (&$tenant) {
            $query->where('tenant_id', $tenant);
        });

        $this->assertFalse($this->fails('unique:authentications,email', 'user@teste.com'));

        $tenant = 1;

        $this->assertTrue($this->fails('unique:authentications,email', 'user@teste.com'));
    }

    public function test_named_scope_can_be_replaced_and_forgotten(): void
    {
        FormRequest::presenceScope('authentications', fn(Builder $query) => $query->where('tenant_id', 2), 'tenant');
        $this->assertFalse($this->fails('unique:authentications,email', 'user@teste.com'));

        FormRequest::presenceScope('authentications', fn(Builder $query) => $query->where('tenant_id', 1), 'tenant');
        $this->assertTrue($this->fails('unique:authentications,email', 'user@teste.com'));

        $this->app->make(PresenceScopeRegistry::class)->forget('authentications', 'tenant');
        $this->assertTrue($this->fails('unique:authentications,email', 'user@teste.com'));
    }

    public function test_scopes_can_be_bypassed(): void
    {
        FormRequest::presenceScope('authentications', fn(Builder $query) => $query->where('tenant_id', 2));

        $bypassed = FormRequest::withoutPresenceScopes(
            fn() => $this->fails('unique:authentications,email', 'user@teste.com')
        );

        $this->assertTrue($bypassed);

        // O estado anterior é restaurado depois do callback.
        $this->assertFalse($this->fails('unique:authentications,email', 'user@teste.com'));
    }

    public function test_bypass_restores_state_when_callback_throws(): void
    {
        FormRequest::presenceScope('authentications', fn(Builder $query) => $query->where('tenant_id', 2));

        try {
            FormRequest::withoutPresenceScopes(function () {
                throw new \RuntimeException('boom');
            });
        } catch (\RuntimeException) {
            // esperado
        }

        $this->assertFalse($this->fails('unique:authentications,email', 'user@teste.com'));
    }

    public function test_unique_ignore_id_still_works_with_scope(): void
    {
        FormRequest::presenceScope('authentications', fn(Builder $query) => $query->where('tenant_id', 1));

        // Ignorando o próprio registro, o e-mail deixa de conflitar.
        $this->assertFalse($this->fails('unique:authentications,email,1', 'user@teste.com'));
        $this->assertTrue($this->fails('unique:authentications,email,3', 'user@teste.com'));
    }

    /**
     * Roda a regra isolada e informa se a validação falhou.
     */
    private function fails(string $rule, mixed $value): bool
    {
        return Validator::make(['email' => $value], ['email' => $rule])->fails();
    }
}

